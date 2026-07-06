<?php

namespace App\Services\Facturacion;

use SoapClient;
use SoapFault;
use SoapParam;
use SoapVar;

class SiatSoapTransportService
{
    public function call(array $endpoint, string $operation, mixed $payload = null, array $auth = []): array
    {
        if (! class_exists(SoapClient::class)) {
            return [
                'success' => false,
                'code' => 'SOAP_EXTENSION_MISSING',
                'message' => 'La extension SOAP de PHP no esta habilitada en el servidor.',
                'endpoint' => $endpoint,
            ];
        }

        if (! ($endpoint['configured'] ?? false) || blank($endpoint['wsdl'] ?? null)) {
            return [
                'success' => false,
                'code' => 'SIAT_ENDPOINT_NOT_CONFIGURED',
                'message' => 'No existe un endpoint SIAT configurado para este modulo y ambiente.',
                'endpoint' => $endpoint,
            ];
        }

        try {
            $client = new SoapClient($endpoint['wsdl'], [
                'stream_context' => $this->buildStreamContext($auth),
                'cache_wsdl' => WSDL_CACHE_NONE,
                'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
                'exceptions' => true,
                'trace' => true,
                'connection_timeout' => 20,
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            ]);

            $response = $this->invoke($client, $operation, $payload);

            return [
                'success' => true,
                'raw' => $this->normalize($response),
                'endpoint' => $endpoint,
                'operation' => $operation,
                'debug' => $this->extractSoapTrace($client),
            ];
        } catch (SoapFault $exception) {
            return [
                'success' => false,
                'code' => 'SOAP_FAULT',
                'message' => $exception->getMessage(),
                'endpoint' => $endpoint,
                'operation' => $operation,
                'debug' => isset($client) ? $this->extractSoapTrace($client) : [],
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'code' => 'SOAP_CLIENT_ERROR',
                'message' => $exception->getMessage(),
                'endpoint' => $endpoint,
                'operation' => $operation,
                'debug' => isset($client) ? $this->extractSoapTrace($client) : [],
            ];
        }
    }

    private function buildStreamContext(array $auth)
    {
        $headers = [];
        $token = (string) ($auth['token'] ?? '');

        if ($token !== '') {
            $tokenPrefix = (string) ($auth['token_prefix'] ?? 'Token');
            $tokenHeader = (string) ($auth['token_header'] ?? 'Authorization');

            $headers[] = "{$tokenHeader}: {$tokenPrefix} {$token}";
            // Algunos ejemplos oficiales legacy usan apikey/TokenApi; se envia tambien como compatibilidad.
            $headers[] = "apikey: TokenApi {$token}";
        }

        return stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        if (! is_object($value)) {
            return $value;
        }

        return array_map(
            fn ($item) => $this->normalize($item),
            get_object_vars($value),
        );
    }

    private function buildArguments(mixed $payload): array
    {
        if ($payload === null) {
            return [];
        }

        if (
            is_array($payload)
            && $payload !== []
            && array_is_list($payload)
            && collect($payload)->every(fn ($item) => $item instanceof SoapParam || $item instanceof SoapVar)
        ) {
            return $payload;
        }

        return [$payload];
    }

    private function invoke(SoapClient $client, string $operation, mixed $payload): mixed
    {
        if (
            is_array($payload)
            && $payload !== []
            && array_is_list($payload)
            && collect($payload)->every(fn ($item) => $item instanceof SoapParam || $item instanceof SoapVar)
        ) {
            return $client->__soapCall($operation, $payload);
        }

        try {
            return $payload === null
                ? $client->{$operation}()
                : $client->{$operation}($payload);
        } catch (SoapFault $exception) {
            $message = strtolower($exception->getMessage());

            if (
                ! str_contains($message, 'encoding: object has no')
                && ! str_contains($message, 'looks like we got no xml document')
            ) {
                throw $exception;
            }
        }

        return $client->__soapCall($operation, $this->buildArguments($payload));
    }

    private function extractSoapTrace(SoapClient $client): array
    {
        return [
            'last_request_headers' => $this->sanitizeHeaders((string) $client->__getLastRequestHeaders()),
            'last_request' => (string) $client->__getLastRequest(),
            'last_response_headers' => $this->sanitizeHeaders((string) $client->__getLastResponseHeaders()),
            'last_response' => (string) $client->__getLastResponse(),
        ];
    }

    private function sanitizeHeaders(string $headers): string
    {
        if ($headers === '') {
            return $headers;
        }

        return preg_replace('/(Authorization|apikey):\s*[^\r\n]+/i', '$1: [REDACTED]', $headers) ?? $headers;
    }
}

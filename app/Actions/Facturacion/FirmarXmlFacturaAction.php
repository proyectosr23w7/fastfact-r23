<?php

namespace App\Actions\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Models\Configuracion\Configuracion;

class FirmarXmlFacturaAction
{
    public function __invoke(string $xml, int $ventaId, int $tipoFacturacion): array
    {
        $signedXml = $tipoFacturacion === TipoFacturacionEnum::ELECTRONICA->value
            ? $this->signXml($xml)
            : $xml;

        return [
            'xml' => $signedXml,
            'path' => null,
            'filename' => "factura-venta-{$ventaId}-signed.xml",
        ];
    }

    private function signXml(string $xml): string
    {
        $configuracion = Configuracion::current();
        $certificatePath = (string) ($configuracion?->firmaDigitalAbsolutePath() ?: config('siat.files.certificate_p12_path'));
        $certificatePassword = (string) ($configuracion?->firma_digital_password ?: config('siat.files.certificate_p12_password'));

        if ($certificatePath === '' || ! is_file($certificatePath)) {
            abort(422, 'No existe el certificado digital P12 configurado para la factura electronica.');
        }

        if ($certificatePassword === '') {
            abort(422, 'Debe configurar la contrasena del certificado P12 para firmar la factura electronica.');
        }

        require_once app_path('Libraries/xmlseclibs/src/XMLSecurityDSig.php');
        require_once app_path('Libraries/xmlseclibs/src/XMLSecurityKey.php');
        require_once app_path('Libraries/xmlseclibs/src/XMLSecEnc.php');
        require_once app_path('Libraries/xmlseclibs/src/Utils/XPath.php');

        $certificate = file_get_contents($certificatePath);

        if (! openssl_pkcs12_read((string) $certificate, $keyPair, $certificatePassword)) {
            abort(422, 'No se pudo leer el certificado digital P12 configurado para la firma.');
        }

        $document = new \DOMDocument();
        $document->loadXML($xml);

        $signature = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $signature->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);
        $signature->addReference(
            $document,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true]
        );

        $key = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
        $key->loadKey($keyPair['pkey']);
        $signature->sign($key);
        $signature->add509Cert((string) $keyPair['cert']);
        $signature->appendSignature($document->documentElement);

        return $document->saveXML() ?: $xml;
    }
}

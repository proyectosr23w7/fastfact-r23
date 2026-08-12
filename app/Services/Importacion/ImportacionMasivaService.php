<?php

namespace App\Services\Importacion;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\SinProductoServicio;
use App\Services\Inventario\ArticuloService;
use App\Services\Ventas\ClienteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportacionMasivaService
{
    public function __construct(
        private readonly ClienteService $clienteService,
        private readonly ArticuloService $articuloService,
    ) {}

    public function clientes(array $rows): array
    {
        return $this->importar($rows, function (array $row): string {
            $data = $this->clienteData($row);
            $cliente = Cliente::query()
                ->where('nit_ci', $data['nit_ci'])
                ->where('tipo_documento_identidad', $data['tipo_documento_identidad'])
                ->where('complemento', $data['complemento'])
                ->first();

            $this->validarCliente($data, $cliente);

            if ($cliente) {
                $this->clienteService->actualizar($cliente, $data);

                return 'actualizado';
            }

            $this->clienteService->crear($data);

            return 'creado';
        });
    }

    public function articulos(array $rows, array $defaults = []): array
    {
        $defaults = $this->articuloDefaults($defaults);

        return $this->importar($rows, function (array $row) use ($defaults): string {
            $data = $this->articuloData($row, $defaults);
            $articulo = Articulo::query()
                ->where('codigo_generico', $data['codigo_generico'])
                ->first();

            $this->validarArticulo($data, $articulo);

            if ($articulo) {
                $this->articuloService->actualizar($articulo, $data);

                return 'actualizado';
            }

            $this->articuloService->crear($data);

            return 'creado';
        });
    }

    private function importar(array $rows, callable $callback): array
    {
        $result = [
            'total' => count($rows),
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $status = DB::transaction(fn () => $callback($row));

                if ($status === 'actualizado') {
                    $result['actualizados']++;
                } else {
                    $result['creados']++;
                }
            } catch (ValidationException $exception) {
                $result['omitidos']++;
                $result['errores'][] = [
                    'fila' => $rowNumber,
                    'mensaje' => collect($exception->errors())->flatten()->implode(' '),
                ];
            } catch (Throwable $exception) {
                $result['omitidos']++;
                $result['errores'][] = [
                    'fila' => $rowNumber,
                    'mensaje' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function clienteData(array $row): array
    {
        $tipoDocumento = $this->string($row['tipo_documento_identidad'] ?? $row['tipo_documento'] ?? '');

        return [
            'razon_social' => $this->string($row['razon_social'] ?? $row['nombre'] ?? ''),
            'nit_ci' => $this->string($row['nit_ci'] ?? $row['documento'] ?? ''),
            'tipo_documento_identidad' => $tipoDocumento,
            'complemento' => $tipoDocumento === '1' ? $this->nullableString($row['complemento'] ?? null) : null,
            'telefono' => $this->nullableString($row['telefono'] ?? $row['celular'] ?? null),
            'correo' => $this->nullableString($row['correo'] ?? $row['email'] ?? null),
            'direccion' => $this->nullableString($row['direccion'] ?? null),
            'estado' => $this->boolean($row['estado'] ?? true),
        ];
    }

    private function articuloData(array $row, array $defaults = []): array
    {
        $categoriaId = $this->categoriaId($row['categoria_id'] ?? null, $row['categoria'] ?? $row['categoria_nombre'] ?? null);
        $marcaId = $this->marcaId($row['marca_id'] ?? null, $row['marca'] ?? $row['marca_nombre'] ?? null);
        $precio = $this->decimal($row['precio_base'] ?? $row['precio'] ?? 0);
        $codigoActividad = $this->nullableString($row['codigo_actividad_economica'] ?? $row['actividad_siat'] ?? null)
            ?? $defaults['codigo_actividad_economica'];
        $codigoProductoSin = $this->nullableString($row['codigo_producto_sin'] ?? $row['producto_sin'] ?? null)
            ?? $defaults['codigo_producto_sin'];
        $codigoUnidadSiat = $this->nullableString($row['codigo_unidad_medida_siat'] ?? $row['unidad_siat'] ?? null)
            ?? $defaults['codigo_unidad_medida_siat'];

        return [
            'codigo_generico' => $this->string($row['codigo_generico'] ?? $row['codigo'] ?? ''),
            'codigo_barras' => $this->nullableString($row['codigo_barras'] ?? null),
            'nombre' => $this->string($row['nombre'] ?? $row['producto'] ?? ''),
            'descripcion' => $this->nullableString($row['descripcion'] ?? null),
            'categoria_id' => $categoriaId,
            'marca_id' => $marcaId,
            'codigo_actividad_economica' => $codigoActividad,
            'codigo_producto_sin' => $codigoProductoSin,
            'codigo_unidad_medida_siat' => (string) ($codigoUnidadSiat ?? ''),
            'stock_minimo' => $this->decimal($row['stock_minimo'] ?? 0),
            'precio_base' => $precio,
            'estado' => $this->boolean($row['estado'] ?? true),
            'tags' => [],
            'alias' => [],
            'atributos' => [],
            'precios' => [
                [
                    'cantidad_minima' => 1,
                    'precio' => $precio,
                    'tipo_precio' => 'general',
                    'estado' => true,
                ],
            ],
        ];
    }

    private function articuloDefaults(array $defaults): array
    {
        return [
            'codigo_actividad_economica' => $this->nullableString($defaults['codigo_actividad_economica'] ?? null),
            'codigo_producto_sin' => $this->nullableString($defaults['codigo_producto_sin'] ?? null),
            'codigo_unidad_medida_siat' => $this->nullableString($defaults['codigo_unidad_medida_siat'] ?? null),
        ];
    }

    private function validarCliente(array $data, ?Cliente $cliente): void
    {
        Validator::make($data, [
            'razon_social' => ['required', 'string', 'max:200'],
            'nit_ci' => [
                'required',
                'string',
                'max:50',
                Rule::unique('clientes')->ignore($cliente?->id)->where(fn ($query) => $query
                    ->where('tipo_documento_identidad', $data['tipo_documento_identidad'])
                    ->where('complemento', $data['complemento'])
                ),
            ],
            'tipo_documento_identidad' => ['required', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:150'],
            'estado' => ['boolean'],
        ])->after(function ($validator) use ($data): void {
            if (in_array($data['tipo_documento_identidad'], ['1', '5'], true) && ! preg_match('/^[0-9]+$/', $data['nit_ci'])) {
                $validator->errors()->add('nit_ci', 'Para CI y NIT el documento debe ser numerico.');
            }

            if (preg_match('/^0+$/', $data['nit_ci']) === 1) {
                $validator->errors()->add('nit_ci', 'El documento debe ser distinto de 0.');
            }
        })->validate();
    }

    private function validarArticulo(array $data, ?Articulo $articulo): void
    {
        Validator::make($data, [
            'codigo_generico' => ['required', 'string', 'max:100', Rule::unique('articulos', 'codigo_generico')->ignore($articulo?->id)],
            'codigo_barras' => ['nullable', 'string', 'max:100', Rule::unique('articulos', 'codigo_barras')->ignore($articulo?->id)],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')],
            'codigo_actividad_economica' => ['nullable', 'string', Rule::exists('sin_actividades', 'codigo_clasificador')->where('estado', true)],
            'codigo_producto_sin' => ['nullable', 'string', Rule::exists('sin_productos_servicios', 'codigo_producto')->where('estado', true)],
            'codigo_unidad_medida_siat' => ['required', 'string', Rule::exists('sin_unidades_medida', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_uso', true))],
            'precio_base' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['boolean'],
        ])->after(function ($validator) use ($data): void {
            if (blank($data['codigo_actividad_economica']) !== blank($data['codigo_producto_sin'])) {
                $validator->errors()->add('codigo_producto_sin', 'Actividad economica y producto SIN deben enviarse juntos.');
            }

            if (filled($data['codigo_actividad_economica']) && filled($data['codigo_producto_sin'])) {
                $producto = SinProductoServicio::query()
                    ->where('estado', true)
                    ->where('codigo_producto', $data['codigo_producto_sin'])
                    ->first();

                if ($producto && (string) $producto->codigo_actividad !== (string) $data['codigo_actividad_economica']) {
                    $validator->errors()->add('codigo_producto_sin', 'El producto SIN seleccionado no pertenece a la actividad economica elegida.');
                }
            }
        })->validate();
    }

    private function categoriaId(mixed $id, mixed $nombre): int
    {
        if (filled($id)) {
            return (int) $id;
        }

        return (int) Categoria::query()->firstOrCreate(
            ['nombre' => $this->string($nombre ?: 'General')],
            ['descripcion' => 'Categoria creada por importacion.', 'estado' => true],
        )->id;
    }

    private function marcaId(mixed $id, mixed $nombre): ?int
    {
        if (filled($id)) {
            return (int) $id;
        }

        if (blank($nombre)) {
            return null;
        }

        return (int) Marca::query()->firstOrCreate(
            ['nombre' => $this->string($nombre)],
            ['descripcion' => 'Marca creada por importacion.', 'estado' => true],
        )->id;
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value === '' ? null : $value;
    }

    private function decimal(mixed $value): float
    {
        $value = str_replace(',', '.', $this->string($value));

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower($this->string($value)), ['1', 'si', 'true', 'activo', 'activa'], true);
    }
}

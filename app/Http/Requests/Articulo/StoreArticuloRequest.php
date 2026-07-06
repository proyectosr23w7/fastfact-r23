<?php

namespace App\Http\Requests\Articulo;

use App\Models\Configuracion\Configuracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_generico' => ['required', 'string', 'max:100', 'unique:articulos,codigo_generico'],
            'codigo_barras' => ['nullable', 'string', 'max:100', 'unique:articulos,codigo_barras'],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:80'],
            'alias' => ['nullable', 'array'],
            'alias.*' => ['nullable', 'string', 'max:120'],
            'atributos' => ['nullable', 'array'],
            'atributos.*.atributo' => ['nullable', 'string', 'max:120'],
            'atributos.*.valor' => ['nullable', 'string', 'max:200'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')],
            'unidad_medida_id' => ['nullable', 'integer', Rule::exists('unidades_medida', 'id')],
            'codigo_actividad_economica' => ['nullable', 'string', Rule::exists('sin_actividades', 'codigo_clasificador')->where('estado', true)],
            'codigo_producto_sin' => ['nullable', 'string', Rule::exists('sin_productos_servicios', 'codigo_producto')->where('estado', true)],
            'codigo_unidad_medida_siat' => ['required', 'string', Rule::exists('sin_unidades_medida', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_uso', true))],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'precio_base' => ['required', 'numeric', 'min:0'],
            'estado' => ['sometimes', 'boolean'],
            'precios' => ['nullable', 'array'],
            'precios.*.cantidad_minima' => ['required_with:precios.*.precio', 'numeric', 'min:1'],
            'precios.*.precio' => ['required_with:precios.*.cantidad_minima', 'numeric', 'min:0'],
            'precios.*.tipo_precio' => ['nullable', 'string', 'max:50'],
            'precios.*.estado' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $codigoActividad = trim((string) $this->input('codigo_actividad_economica', ''));
                $codigoProducto = trim((string) $this->input('codigo_producto_sin', ''));
                $codigoUnidad = trim((string) $this->input('codigo_unidad_medida_siat', ''));
                $configuracion = Configuracion::ensureCurrent();

                if ((bool) ($configuracion->productos_categorias_habilitadas ?? true) && blank($this->input('categoria_id'))) {
                    $validator->errors()->add('categoria_id', 'Selecciona una categoria para el producto.');
                }

                if ($codigoActividad !== '' || $codigoProducto !== '' || $codigoUnidad !== '') {
                    if ($codigoActividad === '') {
                        $validator->errors()->add('codigo_actividad_economica', 'La actividad economica SIAT es obligatoria si vas a homologar el articulo.');
                    }

                    if ($codigoProducto === '') {
                        $validator->errors()->add('codigo_producto_sin', 'El producto SIN es obligatorio si vas a homologar el articulo.');
                    }

                    if ($codigoUnidad === '') {
                        $validator->errors()->add('codigo_unidad_medida_siat', 'La unidad de medida SIAT es obligatoria si vas a homologar el articulo.');
                    }
                }

                if ($codigoActividad !== '' && $codigoProducto !== '') {
                    $producto = \App\Models\SinProductoServicio::query()
                        ->where('estado', true)
                        ->where('codigo_producto', $codigoProducto)
                        ->first();

                    if ($producto && (string) $producto->codigo_actividad !== $codigoActividad) {
                        $validator->errors()->add('codigo_producto_sin', 'El producto SIN seleccionado no pertenece a la actividad economica elegida.');
                    }
                }
            },
        ];
    }
}

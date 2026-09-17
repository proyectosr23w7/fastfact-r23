<?php

namespace App\Http\Requests\Configuracion\Configuracion;

use App\Enums\AmbienteFacturacionEnum;
use App\Enums\MetodoCostosEnum;
use App\Enums\MetodoSalidaEnum;
use App\Enums\TipoFacturacionEnum;
use App\Models\Configuracion\Configuracion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facturacion_habilitada' => ['sometimes', 'boolean'],
            'tipo_facturacion' => ['required', Rule::in([0, 1, 2, '0', '1', '2'])],
            'ambiente_facturacion' => ['required', new Enum(AmbienteFacturacionEnum::class)],
            'token_siat' => ['nullable', 'string'],
            'codigo_sistema' => ['nullable', 'string', 'max:120'],
            'token_siat_piloto' => ['nullable', 'string'],
            'token_siat_piloto_vigencia' => ['nullable', 'date'],
            'token_siat_produccion' => ['nullable', 'string'],
            'token_siat_produccion_vigencia' => ['nullable', 'date'],
            'firma_digital_nombre' => ['nullable', 'string'],
            'firma_digital_archivo' => [
                'nullable',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->hasFile('firma_digital_archivo')) {
                        return;
                    }

                    $extension = strtolower((string) $this->file('firma_digital_archivo')?->getClientOriginalExtension());

                    if (! in_array($extension, ['p12', 'pfx'], true)) {
                        $fail('La firma digital debe subirse en formato .p12 o .pfx.');
                    }
                },
            ],
            'firma_digital_password' => ['nullable', 'string', 'max:255'],
            'metodo_salida' => ['required', new Enum(MetodoSalidaEnum::class)],
            'metodo_costos' => ['required', new Enum(MetodoCostosEnum::class)],
            'multiples_precios' => ['sometimes', 'boolean'],
            'precios_por_cantidad' => ['sometimes', 'boolean'],
            'mostrar_descuento_detalle_factura' => ['sometimes', 'boolean'],
            'productos_categorias_habilitadas' => ['sometimes', 'boolean'],
            'productos_marcas_habilitadas' => ['sometimes', 'boolean'],
            'productos_busqueda_avanzada_habilitada' => ['sometimes', 'boolean'],
            'productos_codigo_barras_habilitado' => ['sometimes', 'boolean'],
            'pagos_credito_habilitados' => ['sometimes', 'boolean'],
            'confirmacion_rapida_ventas' => ['sometimes', 'boolean'],
            'facturacion_obligatoria_ventas' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_facturacion.required' => 'Debes seleccionar el tipo de facturación.',
            'tipo_facturacion.in' => 'El tipo de facturación seleccionado no es válido.',
            'ambiente_facturacion.required' => 'Debes seleccionar el ambiente de facturación.',
            'codigo_sistema.max' => 'El código de sistema no puede superar los 120 caracteres.',
            'token_siat_piloto_vigencia.date' => 'La vigencia del token piloto debe ser una fecha válida.',
            'token_siat_produccion_vigencia.date' => 'La vigencia del token de producción debe ser una fecha válida.',
            'firma_digital_archivo.max' => 'La firma digital no puede superar los 5 MB.',
            'firma_digital_password.max' => 'La contraseña de la firma digital no puede superar los 255 caracteres.',
            'metodo_salida.required' => 'Debes seleccionar el método de salida.',
            'metodo_costos.required' => 'Debes seleccionar el método de costos.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $facturacionHabilitada = filter_var($this->input('facturacion_habilitada', false), FILTER_VALIDATE_BOOLEAN);
                $tipoFacturacion = (int) $this->input('tipo_facturacion', TipoFacturacionEnum::NO_EMITE->value);
                $configuracionActual = Configuracion::current();
                $subioArchivo = $this->hasFile('firma_digital_archivo');
                $passwordIngresado = filled($this->input('firma_digital_password'));
                $archivoConfigurado = $configuracionActual?->firmaDigitalConfigurada() ?? false;

                if ($subioArchivo && ! $passwordIngresado) {
                    $validator->errors()->add('firma_digital_password', 'Debes registrar la contraseña de la firma digital al subir un nuevo archivo.');
                }

                if (! $facturacionHabilitada || $tipoFacturacion !== TipoFacturacionEnum::ELECTRONICA->value) {
                    return;
                }

                if (! $subioArchivo && ! $archivoConfigurado) {
                    $validator->errors()->add('firma_digital_archivo', 'La factura electrónica requiere subir una firma digital P12 o PFX.');
                }

                if (! $passwordIngresado && blank($configuracionActual?->firma_digital_password)) {
                    $validator->errors()->add('firma_digital_password', 'La factura electrónica requiere la contraseña de la firma digital.');
                }
            },
        ];
    }
}

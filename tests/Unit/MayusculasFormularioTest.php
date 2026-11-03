<?php

use App\Http\Controllers\Integracion\IntegrationController;
use App\Http\Requests\Articulo\StoreArticuloRequest;
use App\Http\Requests\Articulo\UpdateArticuloRequest;
use App\Http\Requests\Venta\StoreClienteRequest;
use App\Http\Requests\Venta\UpdateClienteRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    Schema::create('configuraciones', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
    $this->migration = require database_path('migrations/2026_11_03_000001_add_textos_mayusculas_habilitados_to_configuraciones.php');
    $this->migration->up();
});

function prepararTextoFormulario(string $class, array $payload): array
{
    $request = $class::create('/', 'POST', $payload);
    (new ReflectionMethod($class, 'prepareForValidation'))->invoke($request);

    return $request->all();
}

test('uppercase migration is idempotent and defaults to disabled', function () {
    $this->migration->up();
    DB::table('configuraciones')->insert(['id' => 1]);
    expect((bool) DB::table('configuraciones')->value('textos_mayusculas_habilitados'))->toBeFalse();
    $this->migration->down();
    $this->migration->down();
    expect(Schema::hasColumn('configuraciones', 'textos_mayusculas_habilitados'))->toBeFalse();
});

test('client forms uppercase only the business name before validation', function (string $class) {
    DB::table('configuraciones')->insert(['textos_mayusculas_habilitados' => true]);
    $payload = [
        'razon_social' => 'Muñoz e hijos Áéíóú',
        'correo' => 'MiCorreo@example.com',
        'nit_ci' => 'ab123',
        'complemento' => 'a1',
        'telefono' => '+591 70000000',
        'tipo_documento_identidad' => '3',
    ];
    $result = prepararTextoFormulario($class, $payload);
    expect($result)->toBe([...$payload, 'razon_social' => 'MUÑOZ E HIJOS ÁÉÍÓÚ']);
})->with([[StoreClienteRequest::class], [UpdateClienteRequest::class]]);

test('document inference still runs with uppercase enabled', function () {
    DB::table('configuraciones')->insert(['textos_mayusculas_habilitados' => true]);
    $data = prepararTextoFormulario(StoreClienteRequest::class, ['razon_social' => 'Peña', 'nit_ci' => '1234567']);
    expect($data['razon_social'])->toBe('PEÑA');
    expect((string) $data['tipo_documento_identidad'])->toBe('1');
});

test('product forms uppercase name and description without changing identifiers', function (string $class) {
    DB::table('configuraciones')->insert(['textos_mayusculas_habilitados' => true]);
    $payload = [
        'nombre' => 'Pintura cañón',
        'descripcion' => "Azul\npara jardín",
        'codigo_generico' => 'abc-001',
        'codigo_barras' => 'ab123',
        'codigo_actividad_economica' => '012000',
        'tags' => ['Azul'],
        'atributos' => [['atributo' => 'Modelo', 'valor' => 'abC']],
        'precio_base' => 12.5,
    ];
    expect(prepararTextoFormulario($class, $payload))->toBe([
        ...$payload, 'nombre' => 'PINTURA CAÑÓN', 'descripcion' => "AZUL\nPARA JARDÍN",
    ]);
})->with([[StoreArticuloRequest::class], [UpdateArticuloRequest::class]]);

test('disabled or absent configuration preserves the entered text', function (string $class) {
    $payload = ['razon_social' => 'Peña', 'tipo_documento_identidad' => '1', 'nombre' => 'Pintura azul', 'descripcion' => null];
    expect(prepararTextoFormulario($class, $payload))->toBe($payload);
    DB::table('configuraciones')->insert(['textos_mayusculas_habilitados' => false]);
    expect(prepararTextoFormulario($class, $payload))->toBe($payload);
})->with([StoreClienteRequest::class, UpdateClienteRequest::class, StoreArticuloRequest::class, UpdateArticuloRequest::class]);

test('uppercase conversion does not hide invalid input or bypass length validation', function () {
    DB::table('configuraciones')->insert(['textos_mayusculas_habilitados' => true]);
    $data = prepararTextoFormulario(StoreArticuloRequest::class, ['nombre' => str_repeat('ß', 101), 'descripcion' => ['invalid']]);
    $validator = Validator::make($data, ['nombre' => ['string', 'max:200'], 'descripcion' => ['nullable', 'string']]);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('nombre'))->toBeTrue();
    expect($validator->errors()->has('descripcion'))->toBeTrue();
});

test('integration API retains the original client and product text', function () {
    DB::table('configuraciones')->insert(['textos_mayusculas_habilitados' => true]);
    Schema::create('clientes', function (Blueprint $table) {
        $table->id();
        $table->string('nit_ci');
        $table->string('tipo_documento_identidad');
        $table->string('complemento')->nullable();
    });
    Schema::create('articulos', function (Blueprint $table) {
        $table->id();
        $table->string('codigo_generico');
    });
    Schema::create('sin_unidades_medida', function (Blueprint $table) {
        $table->string('codigo_clasificador');
        $table->boolean('estado');
        $table->boolean('habilitado_uso');
    });
    DB::table('sin_unidades_medida')->insert(['codigo_clasificador' => '57', 'estado' => true, 'habilitado_uso' => true]);
    $controller = (new ReflectionClass(IntegrationController::class))->newInstanceWithoutConstructor();
    $cliente = (new ReflectionMethod(IntegrationController::class, 'validateCliente'))->invoke($controller,
        Request::create('/', 'POST', ['razon_social' => 'Peña', 'nit_ci' => '1234567', 'tipo_documento_identidad' => '1']));
    $producto = (new ReflectionMethod(IntegrationController::class, 'validateProducto'))->invoke($controller,
        Request::create('/', 'POST', ['codigo_generico' => 'abc-001', 'nombre' => 'Pintura azul', 'descripcion' => 'Para jardín', 'codigo_unidad_medida_siat' => '57', 'precio_base' => 10]));
    expect($cliente['razon_social'])->toBe('Peña');
    expect($producto['nombre'])->toBe('Pintura azul');
    expect($producto['descripcion'])->toBe('Para jardín');
});

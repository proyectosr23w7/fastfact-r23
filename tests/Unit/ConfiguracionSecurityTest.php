<?php

use App\Http\Resources\Configuracion\ConfiguracionResource;
use App\Models\Configuracion\Configuracion;
use App\Repositories\Configuracion\ConfiguracionRepository;
use App\Services\Configuracion\ConfiguracionService;
use Illuminate\Http\Request;

it('expone solo el estado de las credenciales SIAT', function () {
    $configuracion = new Configuracion;
    $configuracion->forceFill([
        'id' => 1,
        'facturacion_habilitada' => true,
        'tipo_facturacion' => 2,
        'ambiente_facturacion' => 'produccion',
        'token_siat' => 'token-general-secreto',
        'token_siat_piloto' => 'token-piloto-secreto',
        'token_siat_produccion' => 'token-produccion-secreto',
        'estado' => true,
    ]);

    $data = (new ConfiguracionResource($configuracion))->toArray(Request::create('/'));

    expect($data)
        ->not->toHaveKeys(['token_siat', 'token_siat_piloto', 'token_siat_produccion', 'firma_digital_password'])
        ->and($data['token_siat_configurado'])->toBeTrue()
        ->and($data['token_siat_piloto_configurado'])->toBeTrue()
        ->and($data['token_siat_produccion_configurado'])->toBeTrue();
});

it('conserva las credenciales existentes cuando no se envía un reemplazo', function () {
    $configuracion = new Configuracion;
    $configuracion->forceFill(['id' => 1]);

    $repository = Mockery::mock(ConfiguracionRepository::class);
    $repository
        ->shouldReceive('update')
        ->once()
        ->with(
            $configuracion,
            Mockery::on(fn (array $data) => $data === [
                'ambiente_facturacion' => 'produccion',
            ]),
        )
        ->andReturn($configuracion);

    $service = new ConfiguracionService($repository);
    $service->actualizar($configuracion, [
        'ambiente_facturacion' => 'produccion',
        'token_siat' => '',
        'token_siat_piloto' => null,
        'token_siat_produccion' => '   ',
        'firma_digital_password' => '',
    ]);
});

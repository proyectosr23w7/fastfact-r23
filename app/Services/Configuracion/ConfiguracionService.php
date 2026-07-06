<?php

namespace App\Services\Configuracion;

use App\Enums\TipoFacturacionEnum;
use App\Models\Configuracion\Configuracion;
use App\Repositories\Configuracion\ConfiguracionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConfiguracionService
{
    public function __construct(
        private readonly ConfiguracionRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Configuracion
    {
        return $this->guardar($this->repository->singleton(), $data);
    }

    public function actualizar(Configuracion $configuracion, array $data): Configuracion
    {
        return $this->guardar($configuracion, $data);
    }

    public function eliminar(Configuracion $configuracion): void
    {
        abort(422, 'La configuracion general es un registro unico y no puede eliminarse.');
    }

    private function guardar(Configuracion $configuracion, array $data): Configuracion
    {
        $data = $this->normalizarCodigoSistema($data);

        foreach (['token_siat', 'token_siat_piloto', 'token_siat_produccion'] as $credential) {
            if (! filled($data[$credential] ?? null)) {
                unset($data[$credential]);
            }
        }

        /** @var UploadedFile|null $firmaDigital */
        $firmaDigital = $data['firma_digital_archivo'] ?? null;

        unset($data['firma_digital_archivo']);

        if ($firmaDigital instanceof UploadedFile) {
            if (filled($configuracion->firma_digital_path)) {
                Storage::disk('local')->delete((string) $configuracion->firma_digital_path);
            }

            $nombreArchivo = 'firma-digital-'.Str::uuid().'.'.$firmaDigital->getClientOriginalExtension();
            $data['firma_digital_path'] = $firmaDigital->storeAs('siat/certificados', $nombreArchivo, 'local');
            $data['firma_digital_nombre'] = $firmaDigital->getClientOriginalName();
        }

        if (! filled($data['firma_digital_password'] ?? null)) {
            unset($data['firma_digital_password']);
        }

        return $this->repository->update($configuracion, $data);
    }

    private function normalizarCodigoSistema(array $data): array
    {
        $codigo = match ((int) ($data['tipo_facturacion'] ?? TipoFacturacionEnum::NO_EMITE->value)) {
            TipoFacturacionEnum::ELECTRONICA->value => '7C70CB5C65357C323C8B20F',
            TipoFacturacionEnum::COMPUTARIZADA->value => '7C7391F0B6F85E18C9EB20F',
            default => null,
        };

        if ($codigo !== null) {
            $data['codigo_sistema'] = $codigo;
        }

        return $data;
    }
}

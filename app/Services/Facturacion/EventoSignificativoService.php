<?php

namespace App\Services\Facturacion;

use App\Actions\Facturacion\GenerarPaqueteFacturasSiatAction;
use App\Enums\FacturaEstadoEnum;
use App\Models\Cafc;
use App\Models\Cufd;
use App\Models\EventoSignificativo;
use App\Models\EventoSignificativoPaquete;
use App\Models\Factura;
use App\Models\User;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Repositories\Facturacion\CufdRepository;
use App\Repositories\Facturacion\EventoSignificativoRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EventoSignificativoService
{
    private const EVENTOS_SIGNIFICATIVOS = [
        '1' => [
            'descripcion' => 'CORTE DEL SERVICIO DE INTERNET',
            'tipo_contingencia' => 'fuera_linea',
            'requiere_cafc' => false,
        ],
        '2' => [
            'descripcion' => 'INACCESIBILIDAD AL SERVICIO WEB DE LA ADMINISTRACION TRIBUTARIA',
            'tipo_contingencia' => 'fuera_linea',
            'requiere_cafc' => false,
        ],
        '3' => [
            'descripcion' => 'INGRESO A ZONAS SIN INTERNET POR DESPLIEGUE DE PUNTO DE VENTA EN VEHICULOS AUTOMOTORES',
            'tipo_contingencia' => 'fuera_linea',
            'requiere_cafc' => false,
        ],
        '4' => [
            'descripcion' => 'VENTA EN LUGARES SIN INTERNET',
            'tipo_contingencia' => 'fuera_linea',
            'requiere_cafc' => false,
        ],
        '5' => [
            'descripcion' => 'VIRUS INFORMATICO O FALLA DE SOFTWARE',
            'tipo_contingencia' => 'manual',
            'requiere_cafc' => true,
        ],
        '6' => [
            'descripcion' => 'CAMBIO DE INFRAESTRUCTURA DE SISTEMA O FALLA DE HARDWARE',
            'tipo_contingencia' => 'manual',
            'requiere_cafc' => true,
        ],
        '7' => [
            'descripcion' => 'CORTE DE SUMINISTRO DE ENERGIA ELECTRICA',
            'tipo_contingencia' => 'manual',
            'requiere_cafc' => true,
        ],
    ];

    private const TIPOS_REPORTE = [
        'cufd' => 'Falla al obtener CUFD',
        'sincronizacion_fecha_hora' => 'Falla en sincronizacion de fecha y hora',
        'recepcion_factura' => 'Falla al registrar factura',
        'otro' => 'Otra incidencia SIAT',
    ];

    public function __construct(
        private readonly EventoSignificativoRepository $repository,
        private readonly CufdRepository $cufdRepository,
        private readonly SiatClientService $client,
        private readonly CufdService $cufdService,
        private readonly GenerarPaqueteFacturasSiatAction $generarPaqueteFacturasSiat,
    ) {
    }

    public function listar(array $filters = [], ?User $user = null): Collection
    {
        $filters = $this->applyUserContextFilters($filters, $user);

        return $this->repository->allForIndex($filters);
    }

    public function activar(array $data, User $user)
    {
        $this->assertCanManageContingencia($user);

        return DB::transaction(function () use ($data, $user) {
            $configuracion = Configuracion::current();

            if (! $configuracion?->facturacionSiatActiva()) {
                abort(422, 'La facturacion no esta activa. No se puede abrir una contingencia fuera de linea.');
            }

            $ambiente = (string) ($configuracion->ambiente_facturacion ?: 'piloto');
            $codigoEvento = (string) $data['codigo_evento'];

            if (! array_key_exists($codigoEvento, self::EVENTOS_SIGNIFICATIVOS)) {
                abort(422, 'El codigo de evento significativo no es valido para este flujo.');
            }
            $eventoConfig = self::EVENTOS_SIGNIFICATIVOS[$codigoEvento];
            $tipoContingencia = (string) $eventoConfig['tipo_contingencia'];

            if ($this->repository->activeForContext($data['sucursal_id'], $data['punto_venta_id'], $ambiente)) {
                abort(422, 'Ya existe una contingencia activa para la sucursal y punto de venta seleccionados.');
            }

            $fechaInicio = Carbon::parse((string) $data['fecha_inicio'], config('app.timezone'))->timezone(config('app.timezone'));
            $cufdEvento = $this->resolveCufdEvento(
                $data['sucursal_id'],
                $data['punto_venta_id'],
                $ambiente,
                $fechaInicio,
                $data['cufd_evento_id'] ?? null,
            );
            $cafc = $tipoContingencia === 'manual'
                ? $this->resolveCafcEvento(
                    $data['sucursal_id'],
                    $data['punto_venta_id'],
                    $ambiente,
                    $fechaInicio,
                    $data['cafc_id'] ?? null,
                )
                : null;

            $evento = $this->repository->create([
                'sucursal_id' => $data['sucursal_id'],
                'punto_venta_id' => $data['punto_venta_id'],
                'ambiente_facturacion' => $ambiente,
                'tipo_contingencia' => $tipoContingencia,
                'modo_activacion' => 'manual',
                'cufd_evento_id' => $cufdEvento->id,
                'cafc_id' => $cafc?->id,
                'codigo_evento' => $codigoEvento,
                'descripcion' => trim((string) ($data['descripcion'] ?: $eventoConfig['descripcion'])),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => null,
                'estado' => 'activo_local',
                'codigo_recepcion' => null,
                'registrado_siat_at' => null,
                'datos_respuesta_siat' => null,
                'observacion_interna' => $data['observacion_interna'] ?? null,
                'user_id' => $user->id,
            ]);

            return $this->repository->refresh($evento);
        });
    }

    public function cerrar(EventoSignificativo $evento, array $data, User $user)
    {
        $this->assertCanManageContingencia($user);

        return DB::transaction(function () use ($evento, $data) {
            $evento = $this->repository->findForProcess($evento);

            if ($evento->estado !== 'activo_local') {
                abort(422, 'Solo se pueden cerrar eventos significativos que se encuentren activos localmente.');
            }

            $fechaFin = Carbon::parse((string) $data['fecha_fin'], config('app.timezone'))->timezone(config('app.timezone'));

            if ($fechaFin->lt($evento->fecha_inicio)) {
                abort(422, 'La fecha de fin no puede ser menor a la fecha de inicio del evento.');
            }

            $ultimaFactura = $evento->facturas
                ->filter(fn ($factura) => $factura->fecha_emision !== null)
                ->sortByDesc('fecha_emision')
                ->first();

            if ($ultimaFactura && $ultimaFactura->fecha_emision?->gt($fechaFin)) {
                abort(422, 'No puedes cerrar el evento antes de la ultima factura emitida en contingencia para este contexto.');
            }

            return $this->repository->update($evento, [
                'fecha_fin' => $fechaFin,
                'estado' => 'cerrado_local',
                'observacion_interna' => $data['observacion_interna'] ?? $evento->observacion_interna,
            ]);
        });
    }

    public function procesarRecuperacion(EventoSignificativo $evento, User $user): EventoSignificativo
    {
        $this->assertCanManageContingencia($user);

        $configuracion = Configuracion::current();

        if (! $configuracion?->facturacionSiatActiva()) {
            abort(422, 'La facturacion no esta activa. No se puede procesar la recuperacion del evento significativo.');
        }

        $evento = $this->repository->findForProcess($evento);

        if (! in_array((string) $evento->tipo_contingencia, ['fuera_linea', 'manual'], true)) {
            abort(422, 'El tipo de contingencia del evento no es compatible con la recuperacion SIAT.');
        }

        if ($evento->estado === 'activo_local') {
            abort(422, 'Primero debes cerrar localmente la contingencia antes de registrar el evento en SIAT.');
        }

        if ($evento->estado === 'concluido') {
            abort(422, 'Este evento ya fue sincronizado completamente con SIAT.');
        }

        if (! $evento->fecha_fin) {
            abort(422, 'El evento debe tener fecha fin para procesar la recuperacion.');
        }

        $cufdRecuperacion = $this->resolveCufdRecuperacion($evento, $configuracion, $user);
        $evento = $this->repository->update($evento, [
            'cufd_recuperacion_id' => $cufdRecuperacion->id,
        ]);

        $evento = $this->registrarEventoSiatSiCorresponde($evento, $cufdRecuperacion);
        $evento = $this->validarPaquetesPendientes($evento, $cufdRecuperacion, $user);
        $evento = $this->enviarPaquetesPendientes($evento, $cufdRecuperacion, $user);

        return $this->actualizarEstadoFinalDelEvento($evento);
    }

    public function reportar(array $data, User $user): array
    {
        $configuracion = Configuracion::current();

        if (! $configuracion?->facturacionSiatActiva()) {
            abort(422, 'La facturacion no esta activa. No corresponde reportar incidencias SIAT en este momento.');
        }

        $ambiente = (string) ($configuracion->ambiente_facturacion ?: 'piloto');
        $activeEvent = $this->repository->activeForContext($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

        $reporte = $this->repository->createReport([
            'evento_significativo_id' => $activeEvent?->id,
            'sucursal_id' => $data['sucursal_id'],
            'punto_venta_id' => $data['punto_venta_id'],
            'ambiente_facturacion' => $ambiente,
            'tipo_falla' => $data['tipo_falla'],
            'descripcion' => $data['descripcion'] ?? null,
            'user_id' => $user->id,
        ]);

        return [
            'id' => $reporte->id,
            'evento_significativo_id' => $reporte->evento_significativo_id,
            'tipo_falla' => $reporte->tipo_falla,
            'descripcion' => $reporte->descripcion,
            'created_at' => optional($reporte->created_at)?->format('Y-m-d H:i:s'),
        ];
    }

    public function eventoActivoPorContexto(int $sucursalId, int $puntoVentaId, ?string $ambiente = null): ?EventoSignificativo
    {
        $configuracion = Configuracion::current();
        $activeAmbiente = $ambiente ?: (string) ($configuracion?->ambiente_facturacion ?: 'piloto');

        return $this->repository->activeForContext($sucursalId, $puntoVentaId, $activeAmbiente);
    }

    public function eventoManualPendientePorContexto(int $sucursalId, int $puntoVentaId, ?string $ambiente = null): ?EventoSignificativo
    {
        $configuracion = Configuracion::current();
        $activeAmbiente = $ambiente ?: (string) ($configuracion?->ambiente_facturacion ?: 'piloto');

        return $this->repository->manualPendingForContext($sucursalId, $puntoVentaId, $activeAmbiente);
    }

    public function eventosFacturablesPorContexto(array $filters = [], ?User $user = null): Collection
    {
        $configuracion = Configuracion::current();
        $filters['ambiente_facturacion'] = $filters['ambiente_facturacion'] ?? (string) ($configuracion?->ambiente_facturacion ?: 'piloto');
        return $this->repository->facturableEvents($filters);
    }

    public function meta(?User $user = null): array
    {
        $configuracion = Configuracion::current();
        $ambiente = (string) ($configuracion?->ambiente_facturacion ?: 'piloto');
        $filters = [
            'ambiente_facturacion' => $ambiente,
        ];

        $sucursales = Sucursal::query()->where('estado', true)->orderBy('codigo');
        $puntosVenta = PuntoVenta::query()->where('estado', true)->orderBy('sucursal_id')->orderBy('codigo');

        return [
            'sucursales' => $sucursales->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => $puntosVenta->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'siat' => $this->client->profile(),
            'eventos_disponibles' => $this->availableEvents(),
            'cafc_disponibles' => $this->cafcDisponibles($filters),
            'tipos_reporte' => $this->reportTypes(),
            'eventos_activos' => $this->repository->activeEvents($filters)->map(fn (EventoSignificativo $evento) => [
                'id' => $evento->id,
                'codigo_evento' => $evento->codigo_evento,
                'descripcion' => $evento->descripcion,
                'tipo_contingencia' => $evento->tipo_contingencia,
                'sucursal_id' => $evento->sucursal_id,
                'punto_venta_id' => $evento->punto_venta_id,
                'fecha_inicio' => optional($evento->fecha_inicio)?->format('Y-m-d H:i:s'),
                'cufd_evento_id' => $evento->cufd_evento_id,
                'cufd_codigo' => $evento->cufdEvento?->codigo,
                'cafc_id' => $evento->cafc_id,
                'cafc_codigo' => $evento->cafc?->codigo,
            ])->values(),
            'reportes_recientes' => $this->repository->latestReports($filters)->map(fn ($reporte) => [
                'id' => $reporte->id,
                'evento_significativo_id' => $reporte->evento_significativo_id,
                'tipo_falla' => $reporte->tipo_falla,
                'descripcion' => $reporte->descripcion,
                'sucursal' => $reporte->sucursal?->nombre,
                'punto_venta' => $reporte->puntoVenta?->nombre,
                'usuario' => $reporte->user?->name,
                'created_at' => optional($reporte->created_at)?->format('Y-m-d H:i:s'),
            ])->values(),
            'puede_gestionar' => (bool) ($user?->canManageAllOperationalContexts() ?? false),
        ];
    }

    private function availableEvents(): array
    {
        return collect(self::EVENTOS_SIGNIFICATIVOS)
            ->map(fn (array $evento, string $codigo) => [
                'codigo' => $codigo,
                'descripcion' => $evento['descripcion'],
                'tipo_contingencia' => $evento['tipo_contingencia'],
                'requiere_cafc' => (bool) $evento['requiere_cafc'],
            ])
            ->values()
            ->all();
    }

    private function reportTypes(): array
    {
        return collect(self::TIPOS_REPORTE)
            ->map(fn (string $descripcion, string $codigo) => [
                'codigo' => $codigo,
                'descripcion' => $descripcion,
            ])
            ->values()
            ->all();
    }

    private function resolveCufdEvento(
        int $sucursalId,
        int $puntoVentaId,
        string $ambiente,
        Carbon $fechaInicio,
        ?int $cufdEventoId = null,
    ) {
        $cufdEvento = null;

        if ($cufdEventoId) {
            $cufdEvento = $this->cufdRepository->refresh(
                \App\Models\Cufd::query()->findOrFail($cufdEventoId),
            );

            if (
                $cufdEvento->sucursal_id !== $sucursalId
                || $cufdEvento->punto_venta_id !== $puntoVentaId
                || $cufdEvento->ambiente_facturacion !== $ambiente
            ) {
                abort(422, 'El CUFD seleccionado no corresponde al contexto operativo del evento.');
            }
        } else {
            $cufdEvento = $this->cufdRepository->vigenteEnFecha($sucursalId, $puntoVentaId, $ambiente, $fechaInicio);
        }

        if (! $cufdEvento) {
            abort(422, 'No existe un CUFD valido que cubra la fecha de inicio del evento significativo.');
        }

        if ($cufdEvento->fecha_vigencia && $fechaInicio->gt($cufdEvento->fecha_vigencia)) {
            abort(422, 'La fecha de inicio del evento debe estar dentro de la vigencia del CUFD del evento.');
        }

        return $cufdEvento;
    }

    private function resolveCafcEvento(
        int $sucursalId,
        int $puntoVentaId,
        string $ambiente,
        Carbon $fechaInicio,
        ?int $cafcId = null,
    ): Cafc {
        $cafc = Cafc::query()
            ->when($cafcId, fn ($query) => $query->whereKey($cafcId))
            ->where('estado', true)
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where(function ($query) use ($fechaInicio) {
                $query->whereNull('fecha_inicio_vigencia')
                    ->orWhere('fecha_inicio_vigencia', '<=', $fechaInicio);
            })
            ->where(function ($query) use ($fechaInicio) {
                $query->whereNull('fecha_fin_vigencia')
                    ->orWhere('fecha_fin_vigencia', '>=', $fechaInicio);
            })
            ->latest('fecha_fin_vigencia')
            ->latest('id')
            ->first();

        if (! $cafc) {
            abort(422, 'Debes seleccionar un CAFC activo y vigente para eventos significativos manuales 5 al 7.');
        }

        if ($cafc->numero_inicial !== null && $cafc->numero_final !== null && $cafc->numero_final >= $cafc->numero_inicial) {
            $rangoTotal = ((int) $cafc->numero_final - (int) $cafc->numero_inicial) + 1;
            $utilizados = $cafc->facturas()->count();

            if ($utilizados >= $rangoTotal) {
                abort(422, 'El CAFC seleccionado ya agoto su rango autorizado de numeracion manual.');
            }
        }

        return $cafc;
    }

    private function assertCanManageContingencia(User $user): void
    {
        if (! $user->canManageAllOperationalContexts()) {
            abort(403, 'Solo un usuario autorizado puede activar o cerrar contingencias SIAT.');
        }
    }

    private function cafcDisponibles(array $filters): array
    {
        return Cafc::query()
            ->with([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
            ])
            ->where('estado', true)
            ->where('ambiente_facturacion', (string) ($filters['ambiente_facturacion'] ?? 'piloto'))
            ->when(
                filled($filters['sucursal_id'] ?? null),
                fn ($query) => $query->where('sucursal_id', $filters['sucursal_id']),
            )
            ->when(
                filled($filters['punto_venta_id'] ?? null),
                fn ($query) => $query->where('punto_venta_id', $filters['punto_venta_id']),
            )
            ->orderByDesc('fecha_fin_vigencia')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Cafc $cafc) => [
                'id' => $cafc->id,
                'codigo' => $cafc->codigo,
                'descripcion' => $cafc->descripcion,
                'sucursal_id' => $cafc->sucursal_id,
                'punto_venta_id' => $cafc->punto_venta_id,
                'fecha_inicio_vigencia' => optional($cafc->fecha_inicio_vigencia)?->format('Y-m-d H:i:s'),
                'fecha_fin_vigencia' => optional($cafc->fecha_fin_vigencia)?->format('Y-m-d H:i:s'),
            ])
            ->values()
            ->all();
    }

    private function resolveCufdRecuperacion(EventoSignificativo $evento, Configuracion $configuracion, User $user): Cufd
    {
        $ambiente = (string) ($evento->ambiente_facturacion ?: ($configuracion->ambiente_facturacion ?: 'piloto'));
        $vigente = $this->cufdRepository->vigente($evento->sucursal_id, $evento->punto_venta_id, $ambiente);

        if (
            $vigente
            && $vigente->id !== $evento->cufd_evento_id
            && $vigente->created_at
            && $vigente->created_at->gte($evento->fecha_inicio)
        ) {
            return $this->cufdRepository->refresh($vigente);
        }

        return $this->cufdService->registrar([
            'sucursal_id' => $evento->sucursal_id,
            'punto_venta_id' => $evento->punto_venta_id,
            'forzar_nuevo' => true,
        ], $user->id);
    }

    private function registrarEventoSiatSiCorresponde(EventoSignificativo $evento, Cufd $cufdRecuperacion): EventoSignificativo
    {
        if (filled($evento->codigo_recepcion) && $evento->registrado_siat_at) {
            return $evento;
        }

        $response = $this->client->registrarEventoSignificativo([
            'sucursal_id' => $evento->sucursal_id,
            'punto_venta_id' => $evento->punto_venta_id,
            'codigo_evento' => $evento->codigo_evento,
            'descripcion' => $evento->descripcion,
            'fecha_inicio' => $evento->fecha_inicio,
            'fecha_fin' => $evento->fecha_fin,
            'cufd_evento' => $evento->cufdEvento?->codigo,
        ]);

        if (($response['success'] ?? false) !== true || blank($response['codigo'] ?? null)) {
            $evento = $this->repository->update($evento, [
                'estado' => 'observado_siat',
                'datos_respuesta_siat' => $response,
                'cufd_recuperacion_id' => $cufdRecuperacion->id,
            ]);

            abort(422, 'No se pudo registrar el evento significativo en SIAT: '.($response['message'] ?? 'Sin detalle disponible.'));
        }

        return $this->repository->update($evento, [
            'codigo_recepcion' => (string) $response['codigo'],
            'registrado_siat_at' => now(config('app.timezone')),
            'datos_respuesta_siat' => $response,
            'estado' => 'registrado_siat',
            'cufd_recuperacion_id' => $cufdRecuperacion->id,
        ]);
    }

    private function validarPaquetesPendientes(EventoSignificativo $evento, Cufd $cufdRecuperacion, User $user): EventoSignificativo
    {
        $evento = $this->repository->findForProcess($evento);

        foreach ($evento->paquetes as $paquete) {
            if (! in_array((string) $paquete->estado, ['enviado', 'pendiente_validacion'], true) || blank($paquete->codigo_recepcion)) {
                continue;
            }

            $response = $this->client->validacionRecepcionPaqueteFactura([
                'sucursal_id' => $evento->sucursal_id,
                'punto_venta_id' => $evento->punto_venta_id,
                'codigo_recepcion' => $paquete->codigo_recepcion,
            ]);

            if (($response['success'] ?? false) !== true) {
                $this->markPaqueteAsObserved($paquete, $response, $user->id);
                $this->markFacturasAsObserved($paquete->facturas, $response);
                $this->repository->update($evento, ['estado' => 'observado_siat']);
                abort(422, 'SIAT observo la validacion del paquete: '.($response['message'] ?? 'Sin detalle disponible.'));
            }

            if ($this->isPaqueteValidado($response)) {
                $this->markPaqueteAsValidated($paquete, $response, $cufdRecuperacion->id, $user->id);
                $this->markFacturasAsSynchronized($paquete->facturas, $response, $paquete->codigo_recepcion);
                continue;
            }

            $paquete->update([
                'estado' => 'pendiente_validacion',
                'codigo_estado' => (string) ($response['code'] ?? $paquete->codigo_estado),
                'descripcion_estado' => mb_substr((string) ($response['message'] ?? $paquete->descripcion_estado), 0, 1000),
                'fecha_validacion' => now(config('app.timezone')),
                'datos_respuesta_validacion' => $response,
                'cufd_envio_id' => $cufdRecuperacion->id,
                'user_id' => $user->id,
            ]);

            foreach ($paquete->facturas as $factura) {
                $factura->update([
                    'estado_sincronizacion' => 'pendiente_validacion',
                    'codigo_recepcion' => $paquete->codigo_recepcion,
                    'codigo_estado' => (string) ($response['code'] ?? $factura->codigo_estado),
                    'descripcion_estado' => mb_substr((string) ($response['message'] ?? $factura->descripcion_estado), 0, 1000),
                    'datos_respuesta_siat' => $response,
                ]);
            }
        }

        return $this->repository->findForProcess($evento);
    }

    private function enviarPaquetesPendientes(EventoSignificativo $evento, Cufd $cufdRecuperacion, User $user): EventoSignificativo
    {
        $evento = $this->repository->findForProcess($evento);

        if ((string) $evento->tipo_contingencia === 'manual' && ! $evento->cafc) {
            abort(422, 'El evento significativo manual no tiene un CAFC asociado. No se puede preparar el envio por paquetes.');
        }

        $facturasPendientes = $evento->facturas
            ->filter(fn (Factura $factura) => in_array((string) $factura->estado_sincronizacion, ['pendiente_paquete', 'observado_paquete', 'paquete_preparado'], true))
            ->values();

        $numeroPaquete = ((int) $evento->paquetes->max('numero_paquete')) + 1;

        foreach ($facturasPendientes->chunk(500) as $chunk) {
            $paquete = EventoSignificativoPaquete::query()->create([
                'evento_significativo_id' => $evento->id,
                'cufd_envio_id' => $cufdRecuperacion->id,
                'numero_paquete' => $numeroPaquete,
                'cantidad_facturas' => $chunk->count(),
                'estado' => 'preparado',
                'user_id' => $user->id,
            ]);

            Factura::query()
                ->whereKey($chunk->pluck('id')->all())
                ->update([
                    'evento_significativo_paquete_id' => $paquete->id,
                    'estado_sincronizacion' => 'paquete_preparado',
                ]);

            $archivo = ($this->generarPaqueteFacturasSiat)($chunk, $evento->id, $numeroPaquete);

            $response = $this->client->recepcionPaqueteFactura([
                'sucursal_id' => $evento->sucursal_id,
                'punto_venta_id' => $evento->punto_venta_id,
                'archivo' => $archivo['binary'],
                'fecha_envio' => now(config('app.timezone')),
                'hash_archivo' => $archivo['hash'],
                'cantidad_facturas' => $archivo['cantidad'],
                'codigo_recepcion_evento' => $evento->codigo_recepcion,
                'cafc' => $evento->tipo_contingencia === 'manual' ? $evento->cafc?->codigo : null,
            ]);

            if (($response['success'] ?? false) !== true || blank($response['codigo_recepcion'] ?? $response['codigo'] ?? null)) {
                $paquete->update([
                    'estado' => 'observado',
                    'codigo_estado' => (string) ($response['code'] ?? ''),
                    'descripcion_estado' => mb_substr((string) ($response['message'] ?? 'SIAT observo el envio del paquete.'), 0, 1000),
                    'hash_archivo' => $archivo['hash'],
                    'nombre_archivo' => $archivo['nombre_archivo'],
                    'datos_respuesta_recepcion' => $response,
                ]);

                $this->markFacturasAsObserved($chunk, $response, $paquete->id);
                $this->repository->update($evento, ['estado' => 'observado_siat']);
                abort(422, 'No se pudo enviar el paquete SIAT: '.($response['message'] ?? 'Sin detalle disponible.'));
            }

            $codigoRecepcionPaquete = (string) ($response['codigo_recepcion'] ?? $response['codigo']);

            $paquete->update([
                'codigo_recepcion' => $codigoRecepcionPaquete,
                'codigo_estado' => (string) ($response['code'] ?? ''),
                'descripcion_estado' => mb_substr((string) ($response['message'] ?? 'Paquete recibido por SIAT.'), 0, 1000),
                'estado' => 'enviado',
                'hash_archivo' => $archivo['hash'],
                'nombre_archivo' => $archivo['nombre_archivo'],
                'fecha_envio' => now(config('app.timezone')),
                'datos_respuesta_recepcion' => $response,
            ]);

            Factura::query()
                ->whereKey($chunk->pluck('id')->all())
                ->update([
                    'estado_sincronizacion' => 'pendiente_validacion',
                    'codigo_recepcion' => $codigoRecepcionPaquete,
                    'codigo_estado' => (string) ($response['code'] ?? ''),
                    'descripcion_estado' => mb_substr((string) ($response['message'] ?? 'Paquete recibido por SIAT. Pendiente de validacion.'), 0, 1000),
                    'datos_respuesta_siat' => $response,
                    'evento_significativo_paquete_id' => $paquete->id,
                ]);

            $validationResponse = $this->client->validacionRecepcionPaqueteFactura([
                'sucursal_id' => $evento->sucursal_id,
                'punto_venta_id' => $evento->punto_venta_id,
                'codigo_recepcion' => $codigoRecepcionPaquete,
            ]);

            if (($validationResponse['success'] ?? false) !== true) {
                $this->markPaqueteAsObserved($paquete, $validationResponse, $user->id);
                $this->markFacturasAsObserved($chunk, $validationResponse, $paquete->id);
                $this->repository->update($evento, ['estado' => 'observado_siat']);
                abort(422, 'SIAT observo la validacion del paquete: '.($validationResponse['message'] ?? 'Sin detalle disponible.'));
            }

            if ($this->isPaqueteValidado($validationResponse)) {
                $this->markPaqueteAsValidated($paquete, $validationResponse, $cufdRecuperacion->id, $user->id);
                $this->markFacturasAsSynchronized($chunk, $validationResponse, $codigoRecepcionPaquete, $paquete->id);
            } else {
                $paquete->update([
                    'estado' => 'pendiente_validacion',
                    'codigo_estado' => (string) ($validationResponse['code'] ?? $paquete->codigo_estado),
                    'descripcion_estado' => mb_substr((string) ($validationResponse['message'] ?? $paquete->descripcion_estado), 0, 1000),
                    'fecha_validacion' => now(config('app.timezone')),
                    'datos_respuesta_validacion' => $validationResponse,
                    'cufd_envio_id' => $cufdRecuperacion->id,
                    'user_id' => $user->id,
                ]);

                Factura::query()
                    ->whereKey($chunk->pluck('id')->all())
                    ->update([
                        'estado_sincronizacion' => 'pendiente_validacion',
                        'codigo_recepcion' => $codigoRecepcionPaquete,
                        'codigo_estado' => (string) ($validationResponse['code'] ?? ''),
                        'descripcion_estado' => mb_substr((string) ($validationResponse['message'] ?? 'Paquete pendiente de validacion en SIAT.'), 0, 1000),
                        'datos_respuesta_siat' => $validationResponse,
                        'evento_significativo_paquete_id' => $paquete->id,
                    ]);
            }

            $numeroPaquete++;
        }

        return $this->repository->findForProcess($evento);
    }

    private function actualizarEstadoFinalDelEvento(EventoSignificativo $evento): EventoSignificativo
    {
        $evento = $this->repository->findForProcess($evento);
        $facturas = $evento->facturas;

        $todasSincronizadas = $facturas->isEmpty()
            || $facturas->every(fn (Factura $factura) => (string) $factura->estado_sincronizacion === 'sincronizada');

        if ($todasSincronizadas) {
            return $this->repository->update($evento, ['estado' => 'concluido']);
        }

        $hayPendienteValidacion = EventoSignificativoPaquete::query()
            ->where('evento_significativo_id', $evento->id)
            ->whereIn('estado', ['enviado', 'pendiente_validacion'])
            ->exists();

        if ($hayPendienteValidacion) {
            return $this->repository->update($evento, ['estado' => 'pendiente_validacion_paquetes']);
        }

        $hayObservados = EventoSignificativoPaquete::query()
            ->where('evento_significativo_id', $evento->id)
            ->where('estado', 'observado')
            ->exists();

        if ($hayObservados) {
            return $this->repository->update($evento, ['estado' => 'observado_siat']);
        }

        return $this->repository->update($evento, ['estado' => 'registrado_siat']);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Factura>  $facturas
     */
    private function markFacturasAsObserved(\Illuminate\Support\Collection $facturas, array $response, ?int $paqueteId = null): void
    {
        foreach ($facturas as $factura) {
            $factura->update([
                'estado_sincronizacion' => 'observado_paquete',
                'codigo_estado' => (string) ($response['code'] ?? $factura->codigo_estado),
                'descripcion_estado' => mb_substr((string) ($response['message'] ?? $factura->descripcion_estado), 0, 1000),
                'datos_respuesta_siat' => $response,
                'evento_significativo_paquete_id' => $paqueteId ?? $factura->evento_significativo_paquete_id,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Factura>  $facturas
     */
    private function markFacturasAsSynchronized(\Illuminate\Support\Collection $facturas, array $response, string $codigoRecepcionPaquete, ?int $paqueteId = null): void
    {
        foreach ($facturas as $factura) {
            $factura->update([
                'estado_factura' => FacturaEstadoEnum::EMITIDA->value,
                'estado_sincronizacion' => 'sincronizada',
                'codigo_recepcion' => $codigoRecepcionPaquete,
                'codigo_estado' => (string) ($response['code'] ?? $factura->codigo_estado),
                'descripcion_estado' => mb_substr((string) ($response['message'] ?? 'Factura sincronizada por paquete SIAT.'), 0, 1000),
                'datos_respuesta_siat' => $response,
                'evento_significativo_paquete_id' => $paqueteId ?? $factura->evento_significativo_paquete_id,
            ]);

        }
    }

    private function markPaqueteAsObserved(EventoSignificativoPaquete $paquete, array $response, int $userId): void
    {
        $paquete->update([
            'estado' => 'observado',
            'codigo_estado' => (string) ($response['code'] ?? $paquete->codigo_estado),
            'descripcion_estado' => mb_substr((string) ($response['message'] ?? $paquete->descripcion_estado), 0, 1000),
            'fecha_validacion' => now(config('app.timezone')),
            'datos_respuesta_validacion' => $response,
            'user_id' => $userId,
        ]);
    }

    private function markPaqueteAsValidated(EventoSignificativoPaquete $paquete, array $response, int $cufdRecuperacionId, int $userId): void
    {
        $paquete->update([
            'estado' => 'validado',
            'codigo_estado' => (string) ($response['code'] ?? $paquete->codigo_estado),
            'descripcion_estado' => mb_substr((string) ($response['message'] ?? $paquete->descripcion_estado), 0, 1000),
            'fecha_validacion' => now(config('app.timezone')),
            'datos_respuesta_validacion' => $response,
            'cufd_envio_id' => $cufdRecuperacionId,
            'user_id' => $userId,
        ]);
    }

    private function isPaqueteValidado(array $response): bool
    {
        if (($response['success'] ?? false) !== true) {
            return false;
        }

        $codigo = (string) ($response['code'] ?? '');
        $mensaje = mb_strtoupper((string) ($response['message'] ?? ''));

        return $codigo === '908'
            || str_contains($mensaje, 'VALIDADA')
            || str_contains($mensaje, 'VALIDADO');
    }
}

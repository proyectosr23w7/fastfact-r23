<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { useEventoSignificativoStore } from '@/src/stores/facturacion/eventoSignificativoStore';
import {
    AlertTriangle,
    Bolt,
    Box,
    Check,
    CheckCircle2,
    ChevronDown,
    Circle,
    ClipboardList,
    Eye,
    FileClock,
    FileText,
    Filter,
    Flag,
    PackageCheck,
    RefreshCcw,
    RotateCcw,
    Search,
    WifiOff,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type DataItem = Record<string, any>;
type TabKey = 'eventos' | 'facturas' | 'paquetes';

const store = useEventoSignificativoStore();
const dialogOpen = ref(false);
const reportDialogOpen = ref(false);
const closeDialogOpen = ref(false);
const recoveryDialogOpen = ref(false);
const selectedEvento = ref<DataItem | null>(null);
const recoveryEvento = ref<DataItem | null>(null);
const activeTab = ref<TabKey>('eventos');
const filters = ref({
    sucursal_id: '',
    punto_venta_id: '',
    desde: '',
    hasta: '',
    estado: '',
    search: '',
});

const canManage = computed(() =>
    Boolean(store.state.meta.puede_gestionar ?? false),
);
const items = computed<DataItem[]>(() => store.state.items as DataItem[]);
const sucursales = computed<DataItem[]>(
    () => (store.state.meta.sucursales as DataItem[] | undefined) ?? [],
);
const puntosVenta = computed<DataItem[]>(
    () => (store.state.meta.puntos_venta as DataItem[] | undefined) ?? [],
);
const eventosDisponibles = computed<DataItem[]>(
    () =>
        (store.state.meta.eventos_disponibles as DataItem[] | undefined) ?? [],
);
const tiposReporte = computed<DataItem[]>(
    () => (store.state.meta.tipos_reporte as DataItem[] | undefined) ?? [],
);
const siatProfile = computed<DataItem>(
    () => (store.state.meta.siat as DataItem | undefined) ?? {},
);
const eventosActivos = computed<DataItem[]>(() =>
    items.value.filter((item) => item.estado === 'activo_local'),
);

const puntosVentaFiltro = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            !filters.value.sucursal_id ||
            String(punto.sucursal_id) === filters.value.sucursal_id,
    ),
);
const puntosVentaDisponibles = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            String(punto.sucursal_id) ===
            String(store.state.form.sucursal_id ?? ''),
    ),
);
const puntosVentaReporteDisponibles = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            String(punto.sucursal_id) ===
            String(store.state.reportForm.sucursal_id ?? ''),
    ),
);
const cafcDisponibles = computed(() =>
    (
        (store.state.meta.cafc_disponibles as DataItem[] | undefined) ?? []
    ).filter(
        (cafc) =>
            String(cafc.sucursal_id ?? '') ===
                String(store.state.form.sucursal_id ?? '') &&
            String(cafc.punto_venta_id ?? '') ===
            String(store.state.form.punto_venta_id ?? ''),
    ),
);
const cufdDisponibles = computed(() =>
    (
        (store.state.meta.cufd_disponibles as DataItem[] | undefined) ?? []
    ).filter(
        (cufd) =>
            String(cufd.sucursal_id ?? '') ===
                String(store.state.form.sucursal_id ?? '') &&
            String(cufd.punto_venta_id ?? '') ===
                String(store.state.form.punto_venta_id ?? ''),
    ),
);
const ultimoCufdAutomatico = computed(() => cufdDisponibles.value[0] ?? null);
const eventoSeleccionadoConfig = computed(
    () =>
        eventosDisponibles.value.find(
            (evento) =>
                String(evento.codigo ?? '') ===
                String(store.state.form.codigo_evento ?? ''),
        ) ?? null,
);
const eventoManualSeleccionado = computed(
    () =>
        String(eventoSeleccionadoConfig.value?.tipo_contingencia ?? '') ===
        'manual',
);

const estados = computed(() => [
    ...new Set(
        items.value.map((item) => String(item.estado ?? '')).filter(Boolean),
    ),
]);
const filteredEvents = computed(() => {
    const search = filters.value.search.trim().toLocaleLowerCase('es');

    return items.value.filter((item) => {
        const fecha = String(item.fecha_inicio ?? '').slice(0, 10);
        const searchable = [
            item.id,
            item.codigo_evento,
            item.descripcion,
            item.sucursal?.nombre,
            item.punto_venta?.nombre,
            item.codigo_recepcion,
        ]
            .join(' ')
            .toLocaleLowerCase('es');

        return (
            (!filters.value.sucursal_id ||
                String(item.sucursal_id) === filters.value.sucursal_id) &&
            (!filters.value.punto_venta_id ||
                String(item.punto_venta_id) === filters.value.punto_venta_id) &&
            (!filters.value.desde || fecha >= filters.value.desde) &&
            (!filters.value.hasta || fecha <= filters.value.hasta) &&
            (!filters.value.estado ||
                String(item.estado) === filters.value.estado) &&
            (!search || searchable.includes(search))
        );
    });
});
const pendingInvoices = computed(() =>
    filteredEvents.value.flatMap((evento) =>
        (evento.facturas ?? [])
            .filter(
                (factura: DataItem) =>
                    String(factura.estado_sincronizacion ?? '') !==
                    'sincronizada',
            )
            .map((factura: DataItem) => ({ ...factura, evento })),
    ),
);
const visiblePackages = computed(() =>
    filteredEvents.value.flatMap((evento) =>
        (evento.paquetes ?? []).map((paquete: DataItem) => ({
            ...paquete,
            evento,
        })),
    ),
);
const allPendingInvoices = computed(() =>
    items.value.reduce(
        (total, evento) =>
            total +
            (evento.facturas ?? []).filter(
                (factura: DataItem) =>
                    factura.estado_sincronizacion !== 'sincronizada',
            ).length,
        0,
    ),
);
const allPendingPackages = computed(() =>
    items.value.reduce(
        (total, evento) =>
            total +
            (evento.paquetes ?? []).filter(
                (paquete: DataItem) => paquete.estado !== 'validado',
            ).length,
        0,
    ),
);
const closedThisMonth = computed(() => {
    const now = new Date();
    const prefix = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    return items.value.filter((item) =>
        String(item.fecha_fin ?? '').startsWith(prefix),
    ).length;
});
const selectedPackages = computed<DataItem[]>(
    () => selectedEvento.value?.paquetes ?? [],
);
const selectedInvoices = computed<DataItem[]>(
    () => selectedEvento.value?.facturas ?? [],
);
const selectedReports = computed<DataItem[]>(
    () => selectedEvento.value?.reportes ?? [],
);
const recoverableEstados = [
    'cerrado_local',
    'registrado_siat',
    'pendiente_validacion_paquetes',
    'observado_siat',
];

const selectedSteps = computed(() => {
    if (!selectedEvento.value) return [];

    const evento = selectedEvento.value;
    const reportes = selectedReports.value;
    const paquetes = selectedPackages.value;
    const facturas = selectedInvoices.value;
    const registrado = Boolean(
        evento.registrado_siat_at || evento.codigo_recepcion,
    );
    const paquetesEnviados =
        paquetes.length > 0
            ? paquetes.every((paquete) => Boolean(paquete.fecha_envio))
            : registrado && facturas.length === 0;
    const facturasValidadas =
        facturas.length > 0
            ? facturas.every(
                  (factura) => factura.estado_sincronizacion === 'sincronizada',
              )
            : evento.estado === 'concluido';

    return [
        {
            label: 'Reportada',
            done: reportes.length > 0,
            date: reportes[0]?.created_at,
            detail: reportes.length
                ? 'Incidencia registrada'
                : 'Sin reporte previo asociado',
        },
        {
            label: 'Activada',
            done: Boolean(evento.fecha_inicio),
            date: evento.fecha_inicio,
            detail: evento.usuario?.name
                ? `Por ${evento.usuario.name}`
                : undefined,
        },
        {
            label: 'Cerrada',
            done: Boolean(evento.fecha_fin),
            date: evento.fecha_fin,
            detail: evento.fecha_fin
                ? 'Fin local confirmado'
                : 'La contingencia sigue abierta',
        },
        {
            label: 'Registrada en SIAT',
            done: registrado,
            date: evento.registrado_siat_at,
            detail: evento.codigo_recepcion
                ? `Recepción ${evento.codigo_recepcion}`
                : 'Pendiente',
        },
        {
            label: 'Paquetes enviados',
            done: paquetesEnviados,
            date: paquetes.find((paquete) => paquete.fecha_envio)?.fecha_envio,
            detail: paquetes.length
                ? `${paquetes.length} paquete(s)`
                : 'Sin paquetes generados',
        },
        {
            label: 'Facturas validadas',
            done: facturasValidadas,
            date: undefined,
            detail: `${evento.resumen?.facturas_sincronizadas ?? 0} de ${evento.resumen?.total_facturas ?? 0}`,
        },
    ];
});

const nextAction = computed(() => {
    const estado = String(selectedEvento.value?.estado ?? '');
    if (estado === 'activo_local')
        return 'Cerrar la contingencia cuando el servicio o la operación se hayan restablecido.';
    if (estado === 'cerrado_local')
        return 'Registrar el evento en SIAT y preparar el envío de las facturas pendientes.';
    if (estado === 'registrado_siat')
        return 'Enviar las facturas pendientes en paquetes y consultar su validación.';
    if (estado === 'pendiente_validacion_paquetes')
        return 'Consultar nuevamente la validación de los paquetes recibidos por SIAT.';
    if (estado === 'observado_siat')
        return 'Revisar la respuesta técnica de SIAT y reintentar la recuperación cuando corresponda.';
    if (estado === 'concluido')
        return 'La recuperación está completa; no existen acciones fiscales pendientes para este evento.';
    return 'Revisar el estado y la trazabilidad técnica antes de continuar.';
});

const connectivityLabel = computed(() => {
    const verified =
        siatProfile.value.last_verified_at ||
        siatProfile.value.ultima_verificacion;
    return verified
        ? `Última verificación: ${formatDate(verified)}`
        : 'Estado de conexión no verificado en esta pantalla';
});

function formatDate(value?: unknown) {
    if (!value) return '—';
    const raw = String(value);
    const date = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return raw;
    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
}

function localDateTimeInput() {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60_000);
    return local.toISOString().slice(0, 16);
}

function statusLabel(value?: unknown) {
    const labels: Record<string, string> = {
        activo_local: 'Activa',
        cerrado_local: 'Pendiente de registro',
        registrado_siat: 'Registrada en SIAT',
        pendiente_validacion_paquetes: 'Pendiente de validación',
        observado_siat: 'Observada por SIAT',
        concluido: 'Procesada',
    };
    return (
        labels[String(value ?? '')] ??
        String(value ?? 'Sin estado').replaceAll('_', ' ')
    );
}

function statusClass(value?: unknown) {
    const estado = String(value ?? '');
    if (estado === 'concluido')
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    if (estado === 'activo_local')
        return 'border-amber-300 bg-amber-50 text-amber-800';
    if (estado === 'observado_siat')
        return 'border-red-200 bg-red-50 text-red-800';
    return 'border-[#cfe8d8] bg-[#eaf7ef] text-[#116b3a]';
}

function selectEvento(item: DataItem) {
    selectedEvento.value = item;
}

function openActivationDialog() {
    store.resetForm();
    prepararContextoFiscal();
    store.state.form.fecha_inicio = localDateTimeInput();
    dialogOpen.value = true;
}

function openCloseDialog(item: DataItem) {
    selectedEvento.value = item;
    store.resetCloseForm();
    store.state.closeForm.fecha_fin = localDateTimeInput();
    closeDialogOpen.value = true;
}

function openRecoveryDialog(item: DataItem) {
    recoveryEvento.value = item;
    recoveryDialogOpen.value = true;
}

async function submit() {
    const ok = await store.save();
    if (ok) dialogOpen.value = false;
}

async function submitReport() {
    const ok = await store.report();
    if (ok) reportDialogOpen.value = false;
}

async function submitClose() {
    if (!selectedEvento.value?.id) return;
    const id = Number(selectedEvento.value.id);
    const ok = await store.close(id);
    if (ok) {
        closeDialogOpen.value = false;
        selectedEvento.value =
            items.value.find((item) => Number(item.id) === id) ?? null;
    }
}

async function processRecovery() {
    if (!recoveryEvento.value?.id) return;
    const id = Number(recoveryEvento.value.id);
    const ok = await store.processRecovery(id);
    if (ok) {
        recoveryDialogOpen.value = false;
        selectedEvento.value =
            items.value.find((item) => Number(item.id) === id) ?? null;
    }
}

function showRelated(tab: TabKey) {
    activeTab.value = tab;
    document
        .getElementById('contingencia-data')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearFilters() {
    filters.value = {
        sucursal_id: '',
        punto_venta_id: '',
        desde: '',
        hasta: '',
        estado: '',
        search: '',
    };
}

function canProcessRecovery(item: DataItem) {
    return (
        canManage.value &&
        recoverableEstados.includes(String(item.estado ?? ''))
    );
}

function prepararContextoFiscal() {
    const sucursalId = String(sucursales.value[0]?.id ?? '');
    const puntoVentaId = String(
        puntosVenta.value.find(
            (punto) => String(punto.sucursal_id ?? '') === sucursalId,
        )?.id ??
            puntosVenta.value[0]?.id ?? '',
    );

    store.state.form.sucursal_id ||= sucursalId;
    store.state.form.punto_venta_id ||= puntoVentaId;
    store.state.reportForm.sucursal_id ||= sucursalId;
    store.state.reportForm.punto_venta_id ||= puntoVentaId;
}

watch(
    () => filters.value.sucursal_id,
    () => {
        if (
            !puntosVentaFiltro.value.some(
                (punto) => String(punto.id) === filters.value.punto_venta_id,
            )
        )
            filters.value.punto_venta_id = '';
    },
);
watch(
    () => store.state.form.sucursal_id,
    () => {
        if (
            !puntosVentaDisponibles.value.some(
                (punto) =>
                    String(punto.id) ===
                    String(store.state.form.punto_venta_id),
            )
        ) {
            store.state.form.punto_venta_id = String(
                puntosVentaDisponibles.value[0]?.id ?? '',
            );
        }
        if (
            !cafcDisponibles.value.some(
                (cafc) => String(cafc.id) === String(store.state.form.cafc_id),
            )
        )
            store.state.form.cafc_id = '';
        if (
            !cufdDisponibles.value.some(
                (cufd) =>
                    String(cufd.id) ===
                    String(store.state.form.cufd_evento_id),
            )
        )
            store.state.form.cufd_evento_id = '';
    },
);
watch(
    () => store.state.form.punto_venta_id,
    () => {
        if (
            !cafcDisponibles.value.some(
                (cafc) => String(cafc.id) === String(store.state.form.cafc_id),
            )
        )
            store.state.form.cafc_id = '';
        if (
            !cufdDisponibles.value.some(
                (cufd) =>
                    String(cufd.id) ===
                    String(store.state.form.cufd_evento_id),
            )
        )
            store.state.form.cufd_evento_id = '';
    },
);
watch(
    () => store.state.reportForm.sucursal_id,
    () => {
        if (
            !puntosVentaReporteDisponibles.value.some(
                (punto) =>
                    String(punto.id) ===
                    String(store.state.reportForm.punto_venta_id),
            )
        ) {
            store.state.reportForm.punto_venta_id = String(
                puntosVentaReporteDisponibles.value[0]?.id ?? '',
            );
        }
    },
);
watch(
    () => store.state.form.codigo_evento,
    () => {
        if (!eventoManualSeleccionado.value) {
            store.state.form.cafc_id = '';
            store.state.form.cufd_evento_id = '';
        }
    },
);
watch(reportDialogOpen, (open) => {
    if (open) prepararContextoFiscal();
});
watch(filteredEvents, (visible) => {
    if (
        !selectedEvento.value ||
        !visible.some((item) => item.id === selectedEvento.value?.id)
    )
        selectedEvento.value = visible[0] ?? null;
});

onMounted(async () => {
    await store.load();
    prepararContextoFiscal();
    selectedEvento.value = items.value[0] ?? null;
});
</script>

<template>
    <ModulePageLayout
        title="Contingencias SIAT"
        description="Gestiona interrupciones y recupera facturas emitidas fuera de línea."
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            {
                title: 'Contingencias SIAT',
                href: '/facturacion/eventos-significativos',
            },
        ]"
        compact
    >
        <template #actions>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    class="company-action-secondary gap-2"
                    @click="reportDialogOpen = true"
                >
                    <Flag class="size-4" aria-hidden="true" /> Reportar falla
                </Button>
                <Button
                    v-if="canManage"
                    class="company-action-primary gap-2"
                    @click="openActivationDialog"
                >
                    <Bolt class="size-4" aria-hidden="true" /> Activar
                    contingencia
                </Button>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="store.state.generalError"
                role="alert"
                class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
            >
                <XCircle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                <span>{{ store.state.generalError }}</span>
            </div>
            <div
                v-if="store.state.generalSuccess"
                role="status"
                class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                <CheckCircle2
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ store.state.generalSuccess }}</span>
            </div>

            <div
                class="rounded-2xl border p-4 md:p-5"
                :class="
                    eventosActivos.length
                        ? 'border-amber-200 bg-amber-50/70'
                        : 'border-[#cfe8d8] bg-[#f2faf5]'
                "
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="grid size-12 shrink-0 place-items-center rounded-full text-white"
                            :class="
                                eventosActivos.length
                                    ? 'bg-amber-500'
                                    : 'bg-[#168447]'
                            "
                        >
                            <AlertTriangle
                                v-if="eventosActivos.length"
                                class="size-6"
                                aria-hidden="true"
                            />
                            <Check v-else class="size-7" aria-hidden="true" />
                        </div>
                        <div>
                            <p
                                class="font-semibold"
                                :class="
                                    eventosActivos.length
                                        ? 'text-amber-900'
                                        : 'text-[#116b3a]'
                                "
                            >
                                {{
                                    eventosActivos.length
                                        ? `${eventosActivos.length} contingencia(s) activa(s)`
                                        : 'Sin contingencia activa'
                                }}
                            </p>
                            <p class="mt-1 text-sm text-[#36473d]">
                                {{
                                    eventosActivos.length
                                        ? 'Hay contextos operando en modo de contingencia.'
                                        : 'No existen eventos locales abiertos en los contextos disponibles.'
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        class="border-t border-black/10 pt-3 text-sm md:border-t-0 md:border-l md:pt-0 md:pl-6"
                    >
                        <p class="font-medium text-[#19221d]">
                            Integración SIAT
                        </p>
                        <p class="mt-1 flex items-center gap-2 text-[#5d6b62]">
                            <WifiOff class="size-4" aria-hidden="true" />
                            {{ connectivityLabel }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    class="rounded-2xl border border-[#dfe8e2] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><ClipboardList class="size-5"
                        /></span>
                        <div>
                            <p class="text-xs text-[#5d6b62]">
                                Eventos abiertos
                            </p>
                            <p class="text-2xl font-bold text-[#101713]">
                                {{ eventosActivos.length }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-[#718078]">
                        Contingencias locales activas
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-[#dfe8e2] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-amber-50 text-amber-600"
                            ><FileClock class="size-5"
                        /></span>
                        <div>
                            <p class="text-xs text-[#5d6b62]">
                                Facturas pendientes
                            </p>
                            <p class="text-2xl font-bold text-[#101713]">
                                {{ allPendingInvoices }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-[#718078]">
                        Por recuperar o validar
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-[#dfe8e2] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><Box class="size-5"
                        /></span>
                        <div>
                            <p class="text-xs text-[#5d6b62]">
                                Paquetes por enviar
                            </p>
                            <p class="text-2xl font-bold text-[#101713]">
                                {{ allPendingPackages }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-[#718078]">
                        Pendientes de validación final
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-[#dfe8e2] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><PackageCheck class="size-5"
                        /></span>
                        <div>
                            <p class="text-xs text-[#5d6b62]">
                                Eventos cerrados del mes
                            </p>
                            <p class="text-2xl font-bold text-[#101713]">
                                {{ closedThisMonth }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-[#718078]">
                        Con fecha de cierre en el mes actual
                    </p>
                </div>
            </div>

            <div
                class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <section
                    id="contingencia-data"
                    class="overflow-hidden rounded-2xl border border-[#dfe8e2] bg-white shadow-sm"
                >
                    <div
                        class="flex overflow-x-auto border-b border-[#e4ebe6] px-3"
                        role="tablist"
                        aria-label="Vistas de contingencia"
                    >
                        <button
                            v-for="tab in [
                                {
                                    key: 'eventos',
                                    label: 'Eventos',
                                    count: filteredEvents.length,
                                },
                                {
                                    key: 'facturas',
                                    label: 'Facturas pendientes',
                                    count: pendingInvoices.length,
                                },
                                {
                                    key: 'paquetes',
                                    label: 'Paquetes',
                                    count: visiblePackages.length,
                                },
                            ]"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.key"
                            class="border-b-2 px-4 py-3 text-sm font-medium whitespace-nowrap transition"
                            :class="
                                activeTab === tab.key
                                    ? 'border-[#168447] text-[#116b3a]'
                                    : 'border-transparent text-[#65736a] hover:text-[#19221d]'
                            "
                            @click="activeTab = tab.key as TabKey"
                        >
                            {{ tab.label }}
                            <span
                                class="ml-1 rounded-full bg-[#f0f5f2] px-2 py-0.5 text-xs"
                                >{{ tab.count }}</span
                            >
                        </button>
                    </div>

                    <div class="border-b border-[#e4ebe6] bg-[#fbfcfb] p-4">
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                            <label class="text-xs font-medium text-[#435148]"
                                >Sucursal
                                <select
                                    v-model="filters.sucursal_id"
                                    class="company-select mt-1"
                                >
                                    <option value="">Todas</option>
                                    <option
                                        v-for="sucursal in sucursales"
                                        :key="sucursal.id"
                                        :value="String(sucursal.id)"
                                    >
                                        {{ sucursal.nombre }}
                                    </option>
                                </select>
                            </label>
                            <label class="text-xs font-medium text-[#435148]"
                                >Punto de venta
                                <select
                                    v-model="filters.punto_venta_id"
                                    class="company-select mt-1"
                                >
                                    <option value="">Todos</option>
                                    <option
                                        v-for="punto in puntosVentaFiltro"
                                        :key="punto.id"
                                        :value="String(punto.id)"
                                    >
                                        {{ punto.nombre }}
                                    </option>
                                </select>
                            </label>
                            <label class="text-xs font-medium text-[#435148]"
                                >Desde
                                <Input
                                    v-model="filters.desde"
                                    type="date"
                                    class="company-input mt-1"
                                />
                            </label>
                            <label class="text-xs font-medium text-[#435148]"
                                >Hasta
                                <Input
                                    v-model="filters.hasta"
                                    type="date"
                                    class="company-input mt-1"
                                />
                            </label>
                            <label class="text-xs font-medium text-[#435148]"
                                >Estado
                                <select
                                    v-model="filters.estado"
                                    class="company-select mt-1"
                                >
                                    <option value="">Todos</option>
                                    <option
                                        v-for="estado in estados"
                                        :key="estado"
                                        :value="estado"
                                    >
                                        {{ statusLabel(estado) }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div
                            class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <label class="relative min-w-0 flex-1">
                                <span class="sr-only">Buscar eventos</span
                                ><Search
                                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#718078]"
                                />
                                <Input
                                    v-model="filters.search"
                                    class="company-input pl-9"
                                    placeholder="Buscar por evento, contexto o recepción"
                                />
                            </label>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    class="company-action-primary gap-2"
                                    disabled
                                    ><Filter class="size-4" /> Filtros
                                    aplicados</Button
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="company-action-secondary gap-2"
                                    @click="clearFilters"
                                    ><RotateCcw class="size-4" />
                                    Limpiar</Button
                                >
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="store.state.loading"
                        class="space-y-3 p-6"
                        aria-busy="true"
                    >
                        <div
                            v-for="index in 5"
                            :key="index"
                            class="h-12 animate-pulse rounded-xl bg-[#edf3ef]"
                        />
                    </div>

                    <div
                        v-else-if="activeTab === 'eventos'"
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[920px] text-left text-sm">
                            <thead class="bg-[#f7faf8] text-xs text-[#536158]">
                                <tr>
                                    <th class="px-4 py-3">Evento</th>
                                    <th class="px-4 py-3">Contexto</th>
                                    <th class="px-4 py-3">Inicio</th>
                                    <th class="px-4 py-3">Fin</th>
                                    <th class="px-4 py-3 text-center">
                                        Facturas
                                    </th>
                                    <th class="px-4 py-3 text-center">
                                        Paquetes
                                    </th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in filteredEvents"
                                    :key="item.id"
                                    class="border-t border-[#edf1ee] transition hover:bg-[#f5f8f6]"
                                    :class="
                                        selectedEvento?.id === item.id
                                            ? 'bg-[#eaf7ef]/70'
                                            : ''
                                    "
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-[#19221d]">
                                            #{{ item.id }} · Tipo
                                            {{ item.codigo_evento }}
                                        </p>
                                        <p
                                            class="mt-1 max-w-56 truncate text-xs text-[#65736a]"
                                        >
                                            {{ item.descripcion }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p>
                                            {{ item.sucursal?.nombre || '—' }}
                                        </p>
                                        <p class="mt-1 text-xs text-[#65736a]">
                                            {{
                                                item.punto_venta?.nombre || '—'
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(item.fecha_inicio) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(item.fecha_fin) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{
                                            item.resumen?.facturas_pendientes ??
                                            0
                                        }}
                                        /
                                        {{ item.resumen?.total_facturas ?? 0 }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-center font-semibold"
                                    >
                                        {{ item.resumen?.total_paquetes ?? 0 }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
                                            :class="statusClass(item.estado)"
                                            >{{
                                                statusLabel(item.estado)
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="company-action-secondary gap-1"
                                                @click="selectEvento(item)"
                                                ><Eye class="size-4" />
                                                Ver</Button
                                            ><Button
                                                v-if="
                                                    item.estado ===
                                                        'activo_local' &&
                                                    canManage
                                                "
                                                size="sm"
                                                variant="outline"
                                                class="border-amber-300 text-amber-800 hover:bg-amber-50"
                                                @click="openCloseDialog(item)"
                                                >Cerrar</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-if="filteredEvents.length === 0"
                            class="grid place-items-center px-6 py-14 text-center"
                        >
                            <ClipboardList class="size-10 text-[#9ba8a0]" />
                            <p class="mt-3 font-medium text-[#26332b]">
                                No hay eventos para estos filtros
                            </p>
                            <p class="mt-1 text-sm text-[#718078]">
                                Ajusta el contexto, las fechas o el estado.
                            </p>
                        </div>
                    </div>

                    <div
                        v-else-if="activeTab === 'facturas'"
                        class="overflow-x-auto"
                    >
                        <table
                            v-if="pendingInvoices.length"
                            class="w-full min-w-[760px] text-left text-sm"
                        >
                            <thead class="bg-[#f7faf8] text-xs text-[#536158]">
                                <tr>
                                    <th class="px-4 py-3">Factura</th>
                                    <th class="px-4 py-3">Evento</th>
                                    <th class="px-4 py-3">Emisión</th>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-4 py-3">Recepción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="factura in pendingInvoices"
                                    :key="factura.id"
                                    class="cursor-pointer border-t border-[#edf1ee] hover:bg-[#f5f8f6]"
                                    @click="selectEvento(factura.evento)"
                                >
                                    <td class="px-4 py-3 font-semibold">
                                        {{
                                            factura.numero_factura ||
                                            `#${factura.id}`
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        #{{ factura.evento.id }} ·
                                        {{ factura.evento.descripcion }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatDate(factura.fecha_emision) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            factura.cliente?.razon_social ||
                                            factura.cliente?.nombre ||
                                            '—'
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            statusLabel(
                                                factura.estado_sincronizacion,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ factura.codigo_recepcion || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-else
                            class="grid place-items-center px-6 py-14 text-center"
                        >
                            <CheckCircle2 class="size-10 text-[#168447]" />
                            <p class="mt-3 font-medium text-[#26332b]">
                                Sin facturas pendientes
                            </p>
                            <p class="mt-1 text-sm text-[#718078]">
                                No hay facturas por recuperar con los filtros
                                actuales.
                            </p>
                        </div>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table
                            v-if="visiblePackages.length"
                            class="w-full min-w-[760px] text-left text-sm"
                        >
                            <thead class="bg-[#f7faf8] text-xs text-[#536158]">
                                <tr>
                                    <th class="px-4 py-3">Paquete</th>
                                    <th class="px-4 py-3">Evento</th>
                                    <th class="px-4 py-3">Facturas</th>
                                    <th class="px-4 py-3">Envío</th>
                                    <th class="px-4 py-3">Validación</th>
                                    <th class="px-4 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="paquete in visiblePackages"
                                    :key="paquete.id"
                                    class="cursor-pointer border-t border-[#edf1ee] hover:bg-[#f5f8f6]"
                                    @click="selectEvento(paquete.evento)"
                                >
                                    <td class="px-4 py-3 font-semibold">
                                        Paquete {{ paquete.numero_paquete }}
                                    </td>
                                    <td class="px-4 py-3">
                                        #{{ paquete.evento.id }} ·
                                        {{ paquete.evento.descripcion }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ paquete.cantidad_facturas }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ formatDate(paquete.fecha_envio) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            formatDate(paquete.fecha_validacion)
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-xs"
                                            :class="
                                                statusClass(
                                                    paquete.estado ===
                                                        'validado'
                                                        ? 'concluido'
                                                        : paquete.estado,
                                                )
                                            "
                                            >{{
                                                statusLabel(paquete.estado)
                                            }}</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div
                            v-else
                            class="grid place-items-center px-6 py-14 text-center"
                        >
                            <Box class="size-10 text-[#9ba8a0]" />
                            <p class="mt-3 font-medium text-[#26332b]">
                                Sin paquetes generados
                            </p>
                            <p class="mt-1 text-sm text-[#718078]">
                                Los paquetes aparecerán después de iniciar la
                                recuperación.
                            </p>
                        </div>
                    </div>
                </section>

                <aside
                    class="rounded-2xl border border-[#dfe8e2] bg-white shadow-sm xl:sticky xl:top-4"
                >
                    <div class="border-b border-[#e4ebe6] p-4">
                        <p class="font-semibold text-[#101713]">
                            Seguimiento del evento
                        </p>
                        <p class="mt-1 text-xs text-[#718078]">
                            Estado fiscal y siguiente acción calculados con
                            datos registrados.
                        </p>
                    </div>
                    <div v-if="selectedEvento" class="space-y-4 p-4">
                        <dl
                            class="grid grid-cols-[116px_1fr] gap-x-3 gap-y-2 text-sm"
                        >
                            <dt class="font-medium text-[#536158]">Evento</dt>
                            <dd>
                                #{{ selectedEvento.id }} · Tipo
                                {{ selectedEvento.codigo_evento }}
                            </dd>
                            <dt class="font-medium text-[#536158]">
                                Descripción
                            </dt>
                            <dd>{{ selectedEvento.descripcion }}</dd>
                            <dt class="font-medium text-[#536158]">Sucursal</dt>
                            <dd>
                                {{ selectedEvento.sucursal?.nombre || '—' }}
                            </dd>
                            <dt class="font-medium text-[#536158]">
                                Punto de venta
                            </dt>
                            <dd>
                                {{ selectedEvento.punto_venta?.nombre || '—' }}
                            </dd>
                            <dt class="font-medium text-[#536158]">Inicio</dt>
                            <dd>
                                {{ formatDate(selectedEvento.fecha_inicio) }}
                            </dd>
                            <dt class="font-medium text-[#536158]">Fin</dt>
                            <dd>{{ formatDate(selectedEvento.fecha_fin) }}</dd>
                            <dt
                                v-if="selectedEvento.cafc?.codigo"
                                class="font-medium text-[#536158]"
                            >
                                CAFC
                            </dt>
                            <dd
                                v-if="selectedEvento.cafc?.codigo"
                                class="break-all"
                            >
                                {{ selectedEvento.cafc.codigo }}
                            </dd>
                            <dt class="font-medium text-[#536158]">
                                CUFD evento
                            </dt>
                            <dd class="text-xs break-all">
                                {{ selectedEvento.cufd_evento?.codigo || '—' }}
                            </dd>
                        </dl>
                        <div
                            v-if="selectedEvento.alerta_cafc?.message"
                            class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
                        >
                            <p class="font-semibold">
                                {{ selectedEvento.alerta_cafc.label }}
                            </p>
                            <p class="mt-1">
                                {{ selectedEvento.alerta_cafc.message }}
                            </p>
                            <p
                                v-if="selectedEvento.alerta_cafc.detail"
                                class="mt-1 text-xs"
                            >
                                {{ selectedEvento.alerta_cafc.detail }}
                            </p>
                        </div>
                        <ol class="space-y-0" aria-label="Progreso del evento">
                            <li
                                v-for="(step, index) in selectedSteps"
                                :key="step.label"
                                class="relative flex gap-3 pb-4 last:pb-0"
                            >
                                <span
                                    v-if="index < selectedSteps.length - 1"
                                    class="absolute top-6 bottom-0 left-[9px] w-px"
                                    :class="
                                        step.done
                                            ? 'bg-[#20a85b]'
                                            : 'bg-[#d9e1dc]'
                                    "
                                /><span
                                    class="relative z-10 mt-0.5 grid size-5 shrink-0 place-items-center rounded-full"
                                    :class="
                                        step.done
                                            ? 'bg-[#168447] text-white'
                                            : 'border-2 border-[#cbd5ce] bg-white text-[#9aa79f]'
                                    "
                                >
                                    <Check
                                        v-if="step.done"
                                        class="size-3" /><Circle
                                        v-else
                                        class="size-2 fill-current"
                                /></span>
                                <div>
                                    <p
                                        class="text-sm font-semibold"
                                        :class="
                                            step.done
                                                ? 'text-[#183c29]'
                                                : 'text-[#7a877f]'
                                        "
                                    >
                                        {{ step.label }}
                                    </p>
                                    <p class="text-xs text-[#718078]">
                                        {{
                                            step.date
                                                ? formatDate(step.date)
                                                : step.detail
                                        }}
                                    </p>
                                    <p
                                        v-if="step.date && step.detail"
                                        class="text-xs text-[#718078]"
                                    >
                                        {{ step.detail }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                        <div
                            class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950"
                        >
                            <div class="flex gap-2">
                                <AlertTriangle
                                    class="mt-0.5 size-4 shrink-0 text-amber-600"
                                />
                                <p>
                                    <span class="font-semibold"
                                        >Siguiente acción:</span
                                    >
                                    {{ nextAction }}
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="canProcessRecovery(selectedEvento)"
                            class="company-action-primary w-full gap-2"
                            @click="openRecoveryDialog(selectedEvento)"
                            ><RefreshCcw class="size-4" /> Procesar
                            recuperación</Button
                        >
                        <Button
                            v-if="
                                selectedEvento.estado === 'activo_local' &&
                                canManage
                            "
                            variant="outline"
                            class="w-full border-amber-300 text-amber-800 hover:bg-amber-50"
                            @click="openCloseDialog(selectedEvento)"
                            >Cerrar contingencia</Button
                        >
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                variant="outline"
                                class="company-action-secondary gap-2"
                                @click="showRelated('facturas')"
                                ><FileText class="size-4" /> Ver
                                facturas</Button
                            ><Button
                                variant="outline"
                                class="company-action-secondary gap-2"
                                @click="showRelated('paquetes')"
                                ><Box class="size-4" /> Ver paquetes</Button
                            >
                        </div>
                        <details
                            class="rounded-xl border border-[#dfe8e2] bg-[#f8faf9] p-3"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-[#26332b]"
                            >
                                Detalle técnico <ChevronDown class="size-4" />
                            </summary>
                            <div class="mt-3 space-y-3 text-xs text-[#5d6b62]">
                                <p>
                                    <strong>Estado interno:</strong>
                                    {{ selectedEvento.estado }}
                                </p>
                                <p>
                                    <strong>Tipo:</strong>
                                    {{ selectedEvento.tipo_contingencia }}
                                </p>
                                <p>
                                    <strong>Modo:</strong>
                                    {{ selectedEvento.modo_activacion }}
                                </p>
                                <p>
                                    <strong>Recepción evento:</strong>
                                    {{ selectedEvento.codigo_recepcion || '—' }}
                                </p>
                                <p>
                                    <strong>Observación:</strong>
                                    {{
                                        selectedEvento.observacion_interna ||
                                        'Sin observación.'
                                    }}
                                </p>
                                <pre
                                    v-if="selectedEvento.datos_respuesta_siat"
                                    class="max-h-48 overflow-auto rounded-lg bg-[#101713] p-3 whitespace-pre-wrap text-[#eaf7ef]"
                                    >{{
                                        JSON.stringify(
                                            selectedEvento.datos_respuesta_siat,
                                            null,
                                            2,
                                        )
                                    }}</pre
                                >
                                <div v-if="selectedReports.length">
                                    <p class="font-semibold">
                                        Reportes asociados
                                    </p>
                                    <div
                                        v-for="reporte in selectedReports"
                                        :key="reporte.id"
                                        class="mt-2 rounded-lg border border-[#dfe8e2] bg-white p-2"
                                    >
                                        <p>
                                            {{ reporte.tipo_falla }} ·
                                            {{ formatDate(reporte.created_at) }}
                                        </p>
                                        <p>
                                            {{
                                                reporte.descripcion ||
                                                'Sin descripción adicional.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                    <div
                        v-else
                        class="grid place-items-center px-6 py-14 text-center"
                    >
                        <Eye class="size-9 text-[#a2ada6]" />
                        <p class="mt-3 text-sm text-[#65736a]">
                            Selecciona un evento para revisar su seguimiento.
                        </p>
                    </div>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen"
            ><DialogContent class="max-w-3xl border-border/80 p-0"
                ><div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader
                            ><DialogTitle
                                class="text-left text-lg font-semibold text-white"
                                >Activar contingencia SIAT</DialogTitle
                            ><DialogDescription
                                class="text-left text-emerald-50/80"
                                >Abre un evento local en el contexto
                                seleccionado. Las reglas de CUFD y CAFC se
                                validarán en el servidor.</DialogDescription
                            ></DialogHeader
                        >
                    </div>
                    <form class="space-y-5 p-5 md:p-6" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Sucursal</Label
                                ><select
                                    v-model="store.state.form.sucursal_id"
                                    class="company-select"
                                    :disabled="!canManage"
                                >
                                    <option value="">
                                        Seleccione una sucursal
                                    </option>
                                    <option
                                        v-for="sucursal in sucursales"
                                        :key="sucursal.id"
                                        :value="String(sucursal.id)"
                                    >
                                        {{ sucursal.nombre }}
                                    </option></select
                                ><InputError
                                    :message="
                                        store.state.errors.sucursal_id?.[0]
                                    "
                                />
                            </div>
                            <div class="company-field">
                                <Label class="company-label"
                                    >Punto de venta</Label
                                ><select
                                    v-model="store.state.form.punto_venta_id"
                                    class="company-select"
                                    :disabled="!canManage"
                                >
                                    <option value="">
                                        Seleccione un punto de venta
                                    </option>
                                    <option
                                        v-for="punto in puntosVentaDisponibles"
                                        :key="punto.id"
                                        :value="String(punto.id)"
                                    >
                                        {{ punto.nombre }}
                                    </option></select
                                ><InputError
                                    :message="
                                        store.state.errors.punto_venta_id?.[0]
                                    "
                                />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label"
                                    >Tipo de evento</Label
                                ><select
                                    v-model="store.state.form.codigo_evento"
                                    class="company-select"
                                >
                                    <option value="">
                                        Seleccione un evento
                                    </option>
                                    <option
                                        v-for="evento in eventosDisponibles"
                                        :key="evento.codigo"
                                        :value="String(evento.codigo)"
                                    >
                                        {{ evento.codigo }} -
                                        {{ evento.descripcion }}
                                    </option></select
                                ><InputError
                                    :message="
                                        store.state.errors.codigo_evento?.[0]
                                    "
                                />
                            </div>
                            <div class="company-field">
                                <Label class="company-label"
                                    >CUFD del evento</Label
                                >
                                <select
                                    v-if="eventoManualSeleccionado"
                                    v-model="store.state.form.cufd_evento_id"
                                    class="company-select"
                                >
                                    <option value="">Seleccione un CUFD</option>
                                    <option
                                        v-for="cufd in cufdDisponibles"
                                        :key="cufd.id"
                                        :value="String(cufd.id)"
                                    >
                                        Generado: {{ formatDate(cufd.created_at) }}
                                        · Vigencia: {{ formatDate(cufd.fecha_vigencia) }}
                                    </option>
                                </select>
                                <div
                                    v-else
                                    class="rounded-xl border border-[#dfe8e2] bg-[#f5f8f6] px-3 py-2 text-sm text-[#19221d]"
                                >
                                    <p v-if="ultimoCufdAutomatico">
                                        Se usará automáticamente el último CUFD
                                        generado para este punto de venta.
                                    </p>
                                    <p v-else>
                                        No existe un CUFD generado para este
                                        punto de venta.
                                    </p>
                                    <p
                                        v-if="ultimoCufdAutomatico"
                                        class="mt-1 text-xs text-[#65736a]"
                                    >
                                        Generado:
                                        {{ formatDate(ultimoCufdAutomatico.created_at) }}
                                        · Vigencia:
                                        {{ formatDate(ultimoCufdAutomatico.fecha_vigencia) }}
                                    </p>
                                </div>
                                <p
                                    v-if="eventoManualSeleccionado"
                                    class="text-xs text-[#65736a]"
                                >
                                    Para eventos 5 al 7 selecciona el CUFD que
                                    corresponde al evento. Se muestran fecha de
                                    generación y vigencia.
                                </p>
                                <InputError
                                    :message="
                                        store.state.errors.cufd_evento_id?.[0]
                                    "
                                />
                            </div>
                        </div>
                        <div
                            v-if="eventoManualSeleccionado"
                            class="company-field"
                        >
                            <Label class="company-label">CAFC autorizado</Label
                            ><select
                                v-model="store.state.form.cafc_id"
                                class="company-select"
                            >
                                <option value="">Seleccione un CAFC</option>
                                <option
                                    v-for="cafc in cafcDisponibles"
                                    :key="cafc.id"
                                    :value="String(cafc.id)"
                                >
                                    {{ cafc.codigo
                                    }}{{
                                        cafc.descripcion
                                            ? ` - ${cafc.descripcion}`
                                            : ''
                                    }}
                                </option>
                            </select>
                            <p class="text-xs text-[#65736a]">
                                Los eventos 5 al 7 requieren un CAFC vigente
                                para la emisión manual.
                            </p>
                            <InputError
                                :message="store.state.errors.cafc_id?.[0]"
                            />
                        </div>
                        <div class="company-field">
                            <Label class="company-label">Descripción</Label
                            ><Input
                                v-model="store.state.form.descripcion"
                                class="company-input"
                                placeholder="Describe brevemente la contingencia"
                            /><InputError
                                :message="store.state.errors.descripcion?.[0]"
                            />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label"
                                    >Fecha y hora de inicio</Label
                                ><Input
                                    v-model="store.state.form.fecha_inicio"
                                    type="datetime-local"
                                    class="company-input"
                                /><InputError
                                    :message="
                                        store.state.errors.fecha_inicio?.[0]
                                    "
                                />
                            </div>
                            <div class="company-field">
                                <Label class="company-label"
                                    >Observación interna</Label
                                ><Input
                                    v-model="
                                        store.state.form.observacion_interna
                                    "
                                    class="company-input"
                                    placeholder="Dato opcional para seguimiento"
                                />
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                        >
                            <strong
                                >Confirma el contexto antes de
                                continuar.</strong
                            >
                            La activación cambia el modo de emisión fiscal para
                            la sucursal y punto de venta seleccionados.
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="company-action-secondary"
                                @click="dialogOpen = false"
                                >Cancelar</Button
                            ><Button
                                type="submit"
                                class="company-action-primary"
                                :disabled="store.state.saving"
                                >{{
                                    store.state.saving
                                        ? 'Activando…'
                                        : 'Confirmar activación'
                                }}</Button
                            >
                        </div>
                    </form>
                </div></DialogContent
            ></Dialog
        >

        <Dialog v-model:open="reportDialogOpen"
            ><DialogContent class="max-w-2xl border-border/80 p-0"
                ><div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader
                            ><DialogTitle
                                class="text-left text-lg font-semibold text-white"
                                >Reportar incidencia SIAT</DialogTitle
                            ><DialogDescription
                                class="text-left text-emerald-50/80"
                                >Registra evidencia operativa para que un
                                usuario autorizado evalúe la
                                contingencia.</DialogDescription
                            ></DialogHeader
                        >
                    </div>
                    <form
                        class="space-y-5 p-5 md:p-6"
                        @submit.prevent="submitReport"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Sucursal</Label
                                ><select
                                    v-model="store.state.reportForm.sucursal_id"
                                    class="company-select"
                                    :disabled="!canManage"
                                >
                                    <option value="">
                                        Seleccione una sucursal
                                    </option>
                                    <option
                                        v-for="sucursal in sucursales"
                                        :key="sucursal.id"
                                        :value="String(sucursal.id)"
                                    >
                                        {{ sucursal.nombre }}
                                    </option></select
                                ><InputError
                                    :message="
                                        store.state.reportErrors
                                            .sucursal_id?.[0]
                                    "
                                />
                            </div>
                            <div class="company-field">
                                <Label class="company-label"
                                    >Punto de venta</Label
                                ><select
                                    v-model="
                                        store.state.reportForm.punto_venta_id
                                    "
                                    class="company-select"
                                    :disabled="!canManage"
                                >
                                    <option value="">
                                        Seleccione un punto de venta
                                    </option>
                                    <option
                                        v-for="punto in puntosVentaReporteDisponibles"
                                        :key="punto.id"
                                        :value="String(punto.id)"
                                    >
                                        {{ punto.nombre }}
                                    </option></select
                                ><InputError
                                    :message="
                                        store.state.reportErrors
                                            .punto_venta_id?.[0]
                                    "
                                />
                            </div>
                        </div>
                        <div class="company-field">
                            <Label class="company-label">Tipo de falla</Label
                            ><select
                                v-model="store.state.reportForm.tipo_falla"
                                class="company-select"
                            >
                                <option
                                    v-for="tipo in tiposReporte"
                                    :key="tipo.codigo"
                                    :value="String(tipo.codigo)"
                                >
                                    {{ tipo.descripcion }}
                                </option></select
                            ><InputError
                                :message="
                                    store.state.reportErrors.tipo_falla?.[0]
                                "
                            />
                        </div>
                        <div class="company-field">
                            <Label class="company-label">Descripción</Label
                            ><textarea
                                v-model="store.state.reportForm.descripcion"
                                class="company-textarea"
                                placeholder="Detalla el error observado y cuándo comenzó."
                            /><InputError
                                :message="
                                    store.state.reportErrors.descripcion?.[0]
                                "
                            />
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="company-action-secondary"
                                @click="reportDialogOpen = false"
                                >Cancelar</Button
                            ><Button
                                type="submit"
                                class="company-action-primary"
                                :disabled="store.state.reporting"
                                >{{
                                    store.state.reporting
                                        ? 'Reportando…'
                                        : 'Registrar reporte'
                                }}</Button
                            >
                        </div>
                    </form>
                </div></DialogContent
            ></Dialog
        >

        <Dialog v-model:open="closeDialogOpen"
            ><DialogContent class="max-w-2xl border-border/80 p-0"
                ><div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader
                            ><DialogTitle
                                class="text-left text-lg font-semibold text-white"
                                >Cerrar contingencia</DialogTitle
                            ><DialogDescription
                                class="text-left text-emerald-50/80"
                                >Confirma el fin local. La recuperación y el
                                registro en SIAT se procesan
                                después.</DialogDescription
                            ></DialogHeader
                        >
                    </div>
                    <form
                        class="space-y-5 p-5 md:p-6"
                        @submit.prevent="submitClose"
                    >
                        <div
                            class="rounded-xl border border-[#dfe8e2] bg-[#f5f8f6] p-4 text-sm"
                        >
                            <p>
                                <strong>Evento:</strong> #{{
                                    selectedEvento?.id
                                }}
                                · {{ selectedEvento?.descripcion }}
                            </p>
                            <p class="mt-1">
                                <strong>Inicio:</strong>
                                {{ formatDate(selectedEvento?.fecha_inicio) }}
                            </p>
                            <p class="mt-1">
                                <strong>Facturas asociadas:</strong>
                                {{
                                    selectedEvento?.resumen?.total_facturas ?? 0
                                }}
                            </p>
                        </div>
                        <div class="company-field">
                            <Label class="company-label"
                                >Fecha y hora de fin</Label
                            ><Input
                                v-model="store.state.closeForm.fecha_fin"
                                type="datetime-local"
                                class="company-input"
                            /><InputError
                                :message="
                                    store.state.closeErrors.fecha_fin?.[0]
                                "
                            />
                        </div>
                        <div class="company-field">
                            <Label class="company-label"
                                >Observación de cierre</Label
                            ><textarea
                                v-model="
                                    store.state.closeForm.observacion_interna
                                "
                                class="company-textarea"
                                placeholder="Describe el restablecimiento o motivo del cierre."
                            />
                        </div>
                        <div
                            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                        >
                            El cierre detiene la emisión bajo esta contingencia.
                            No registra todavía el evento ni envía paquetes a
                            SIAT.
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="company-action-secondary"
                                @click="closeDialogOpen = false"
                                >Cancelar</Button
                            ><Button
                                type="submit"
                                class="company-action-primary"
                                :disabled="store.state.closing"
                                >{{
                                    store.state.closing
                                        ? 'Cerrando…'
                                        : 'Confirmar cierre'
                                }}</Button
                            >
                        </div>
                    </form>
                </div></DialogContent
            ></Dialog
        >

        <Dialog v-model:open="recoveryDialogOpen"
            ><DialogContent class="max-w-xl border-border/80 p-0"
                ><div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader
                            ><DialogTitle
                                class="text-left text-lg font-semibold text-white"
                                >Procesar recuperación</DialogTitle
                            ><DialogDescription
                                class="text-left text-emerald-50/80"
                                >Esta operación puede registrar el evento,
                                enviar paquetes y consultar validaciones reales
                                en SIAT.</DialogDescription
                            ></DialogHeader
                        >
                    </div>
                    <div class="space-y-5 p-5 md:p-6">
                        <div
                            class="rounded-xl border border-[#dfe8e2] bg-[#f5f8f6] p-4 text-sm"
                        >
                            <p>
                                <strong>Evento:</strong> #{{
                                    recoveryEvento?.id
                                }}
                                · {{ recoveryEvento?.descripcion }}
                            </p>
                            <p class="mt-1">
                                <strong>Estado:</strong>
                                {{ statusLabel(recoveryEvento?.estado) }}
                            </p>
                            <p class="mt-1">
                                <strong>Facturas pendientes:</strong>
                                {{
                                    recoveryEvento?.resumen
                                        ?.facturas_pendientes ?? 0
                                }}
                            </p>
                        </div>
                        <div
                            class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                        >
                            <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                            <p>
                                Continúa solo cuando exista conectividad
                                operativa confirmada por tu procedimiento
                                interno. La pantalla no afirma el estado de
                                conexión.
                            </p>
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button
                                variant="outline"
                                class="company-action-secondary"
                                @click="recoveryDialogOpen = false"
                                >Cancelar</Button
                            ><Button
                                class="company-action-primary"
                                :disabled="
                                    store.state.processingRecoveryId ===
                                    Number(recoveryEvento?.id)
                                "
                                @click="processRecovery"
                                >{{
                                    store.state.processingRecoveryId ===
                                    Number(recoveryEvento?.id)
                                        ? 'Procesando…'
                                        : 'Confirmar y procesar'
                                }}</Button
                            >
                        </div>
                    </div>
                </div></DialogContent
            ></Dialog
        >
    </ModulePageLayout>
</template>

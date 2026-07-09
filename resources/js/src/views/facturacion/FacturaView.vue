<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import FacturaAnulacionModal from '@/src/components/facturacion/FacturaAnulacionModal.vue';
import FacturaDetalleModal from '@/src/components/facturacion/FacturaDetalleModal.vue';
import { useAuthStore } from '@/src/stores/authStore';
import { useFacturaStore } from '@/src/stores/facturacion/facturaStore';
import { Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Ban,
    Check,
    CheckCircle2,
    CircleEllipsis,
    Clock3,
    Download,
    ExternalLink,
    Eye,
    FileCheck2,
    FileText,
    Filter,
    LoaderCircle,
    MoreVertical,
    Plus,
    RotateCcw,
    Search,
    Send,
    ServerCog,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type Factura = Record<string, any>;

const store = useFacturaStore();
const auth = useAuthStore();
const facturas = computed<Factura[]>(() => store.state.items as Factura[]);
const isSuperadmin = computed(() =>
    auth.roles.value.some((role) => role.slug === 'superadmin'),
);
type SortKey =
    | 'numero_factura'
    | 'fecha_emision'
    | 'cliente'
    | 'monto_total'
    | 'estado_factura';
const sortKey = ref<SortKey>('fecha_emision');
const sortDirection = ref<'asc' | 'desc'>('desc');
const sortValue = (item: Factura, key: SortKey) => {
    if (key === 'cliente') {
        return String(
            item.cliente?.razon_social ?? item.cliente?.nombre ?? '',
        ).toLocaleLowerCase('es');
    }
    if (key === 'fecha_emision') {
        return new Date(String(item.fecha_emision ?? '')).getTime() || 0;
    }
    if (key === 'numero_factura' || key === 'monto_total') {
        return Number(item[key] ?? 0);
    }
    return String(item[key] ?? '').toLocaleLowerCase('es');
};
const sortedFacturas = computed(() =>
    [...facturas.value].sort((left, right) => {
        const leftValue = sortValue(left, sortKey.value);
        const rightValue = sortValue(right, sortKey.value);
        const result =
            typeof leftValue === 'number' && typeof rightValue === 'number'
                ? leftValue - rightValue
                : String(leftValue).localeCompare(String(rightValue), 'es', {
                      numeric: true,
                      sensitivity: 'base',
                  });

        return sortDirection.value === 'asc' ? result : -result;
    }),
);
const changeSort = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }
    sortKey.value = key;
    sortDirection.value = key === 'fecha_emision' ? 'desc' : 'asc';
};
const sortIcon = (key: SortKey) =>
    sortKey.value !== key
        ? ArrowUpDown
        : sortDirection.value === 'asc'
          ? ArrowUp
          : ArrowDown;
const emitDialogOpen = ref(false);
const detailDialogOpen = ref(false);
const anularDialogOpen = ref(false);
const retryDialogOpen = ref(false);
const selectedFacturaId = ref<number | null>(null);
const pendingRetry = ref<Factura | null>(null);

const metaRecord = (key: string) =>
    (store.state.meta[key] as Factura | undefined) ?? {};
const metaList = (key: string) =>
    (store.state.meta[key] as Factura[] | undefined) ?? [];
const siatProfile = computed(() => metaRecord('siat'));
const health = computed(() => metaRecord('salud_siat'));
const capabilities = computed(() => metaRecord('capacidades'));
const kpis = computed(() => metaRecord('kpis'));
const metodosPago = computed(() => metaList('metodos_pago'));
const clientes = computed(() => metaList('clientes'));
const sucursales = computed(() => metaList('sucursales'));
const puntosVenta = computed(() => metaList('puntos_venta'));
const puntosVentaDisponibles = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            String(punto.sucursal_id ?? '') ===
            String(store.state.emitForm.sucursal_id ?? ''),
    ),
);
const productosServicios = computed(() => metaList('productos_servicios'));
const unidadesMedida = computed(() => metaList('unidades_medida'));
const ventasFacturables = computed(() => metaList('ventas_facturables'));
const eventosFacturables = computed(() =>
    metaList('eventos_significativos_facturables'),
);
const selectedFactura = computed(
    () =>
        facturas.value.find(
            (item) => Number(item.id) === selectedFacturaId.value,
        ) ?? null,
);
const selectedVentaFacturable = computed(
    () =>
        ventasFacturables.value.find(
            (venta) =>
                String(venta.id ?? '') ===
                String(store.state.emitForm.venta_id ?? ''),
        ) ?? null,
);
const selectedEventoFacturable = computed(
    () =>
        eventosFacturables.value.find(
            (evento) =>
                String(evento.sucursal_id ?? '') ===
                    String(selectedVentaFacturable.value?.sucursal_id ?? '') &&
                String(evento.punto_venta_id ?? '') ===
                    String(selectedVentaFacturable.value?.punto_venta_id ?? ''),
        ) ?? null,
);
const selectedEventoManual = computed(
    () =>
        String(selectedEventoFacturable.value?.tipo_contingencia ?? '') ===
        'manual',
);
const selectedAttention = computed(() =>
    selectedFactura.value &&
    ['observada', 'rechazada'].includes(
        String(selectedFactura.value.estado_factura),
    )
        ? selectedFactura.value
        : null,
);
const facturacionModule = computed(() => {
    const moduleKey = String(
        (siatProfile.value.tipo_facturacion as Factura | undefined)
            ?.module_key ?? '',
    );
    return ((siatProfile.value.modules as Factura[] | undefined) ?? []).find(
        (item) => String(item.key) === moduleKey,
    );
});
const tabs = computed(() => [
    { value: 'facturas', label: 'Facturas', count: facturas.value.length },
    {
        value: 'pendientes',
        label: 'Pendientes',
        count: Number(kpis.value.pendientes?.count ?? 0),
    },
    {
        value: 'contingencias',
        label: 'Contingencias',
        count: Number(kpis.value.contingencias?.count ?? 0),
    },
]);

const statusLabel = (status?: unknown) =>
    ({
        emitida: 'Validada',
        pendiente: 'Pendiente',
        pendiente_envio: 'Pendiente de envio',
        observada: 'Observada',
        rechazada: 'Rechazada',
        anulada: 'Anulada',
    })[String(status ?? '')] ??
    String(status ?? 'No disponible').replaceAll('_', ' ');
const statusClass = (status?: unknown) =>
    ({
        emitida: 'border-[#bde5cc] bg-[#eaf7ef] text-[#126c3a]',
        pendiente: 'border-amber-200 bg-amber-50 text-amber-800',
        pendiente_envio: 'border-amber-200 bg-amber-50 text-amber-800',
        observada: 'border-red-200 bg-red-50 text-red-700',
        rechazada: 'border-red-200 bg-red-50 text-red-700',
        anulada: 'border-[#d8ddda] bg-[#f0f2f1] text-[#566159]',
    })[String(status ?? '')] ?? 'border-[#d8ddda] bg-[#f5f8f6] text-[#566159]';
const formatDate = (value?: unknown, withTime = true) => {
    if (!value) return 'No disponible';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(
        'es-BO',
        withTime
            ? { dateStyle: 'short', timeStyle: 'short' }
            : { dateStyle: 'medium' },
    ).format(date);
};
const formatMoney = (value?: unknown) =>
    new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));
const cufdRemaining = computed(() => {
    const expiry = health.value.cufd?.fecha_vigencia;
    if (!expiry) return null;
    return Math.ceil(
        (new Date(String(expiry).replace(' ', 'T')).getTime() - Date.now()) /
            86400000,
    );
});
const attentionMessage = computed(() => {
    if (!selectedAttention.value) return '';
    const raw = String(selectedAttention.value.descripcion_estado ?? '')
        .split('|')[0]
        .trim();
    return (
        raw ||
        (selectedAttention.value.estado_factura === 'rechazada'
            ? 'SIAT rechazo la factura y requiere una correccion antes de volver a enviarla.'
            : 'SIAT devolvio observaciones que deben revisarse antes de continuar.')
    );
});
const nextStep = computed(() => {
    if (!selectedAttention.value) return '';
    if (
        String(selectedAttention.value.codigo_estado ?? '') ===
        'ADAPTER_PENDING'
    ) {
        return 'Verifica la configuracion del servicio SIAT y consulta nuevamente el estado.';
    }
    return selectedAttention.value.can_retry
        ? 'Revisa los datos fiscales y del cliente; corrige el origen y reintenta el envio.'
        : 'Abre el detalle tecnico y corrige la informacion en la operacion de origen.';
});

const metodoPagoRequiresCardNumber = (codigo?: unknown) =>
    Boolean(
        metodosPago.value.find(
            (item) =>
                String(item.codigo_clasificador ?? '') === String(codigo ?? ''),
        )?.requires_card_number,
    );
const metodoPagoRequiresGiftCardAmount = (codigo?: unknown) =>
    Boolean(
        metodosPago.value.find(
            (item) =>
                String(item.codigo_clasificador ?? '') === String(codigo ?? ''),
        )?.requires_gift_card_amount,
    );
const applyProductoServicio = (index: number) => {
    const detalle = store.state.emitForm.detalles[index];
    const producto = productosServicios.value.find(
        (item) =>
            String(item.id ?? '') ===
            String(detalle.producto_servicio_id ?? ''),
    );

    if (!detalle || !producto) return;

    detalle.actividad_economica = String(producto.codigo_actividad ?? '');
    detalle.codigo_producto_sin = String(producto.codigo_producto ?? '');
    detalle.codigo_producto = String(producto.codigo_producto ?? '');
    detalle.descripcion = String(producto.descripcion ?? '');
    store.recalculateEmitDetail(index);
};
const applySucursalFactura = () => {
    const firstPunto = puntosVentaDisponibles.value[0];
    store.state.emitForm.punto_venta_id = firstPunto
        ? String(firstPunto.id ?? '')
        : '';
};
const selectFactura = (item: Factura) => {
    selectedFacturaId.value = Number(item.id);
};
const openEmitDialog = () => {
    window.location.assign('/facturacion/facturas/nueva');
};
const openDetail = async (item: Factura) => {
    selectFactura(item);
    const detail = await store.show(Number(item.id));
    if (detail) detailDialogOpen.value = true;
};
const openAnularDialog = (item: Factura) => {
    selectFactura(item);
    store.resetAnularForm();
    anularDialogOpen.value = true;
};
const requestRetry = (item: Factura) => {
    pendingRetry.value = item;
    retryDialogOpen.value = true;
};
const executeRetry = async () => {
    if (!pendingRetry.value) return;
    const ok = await store.reintentar(pendingRetry.value);
    if (ok) retryDialogOpen.value = false;
};
const submitEmit = async () => {
    if (await store.emitirDirecta()) emitDialogOpen.value = false;
};
const submitAnular = async () => {
    const item = facturas.value.find(
        (factura) => Number(factura.id) === selectedFacturaId.value,
    );
    if (item && (await store.anular(item))) anularDialogOpen.value = false;
};
const executeRevertirAnulacion = async (item: Factura) => {
    const numero = String(item.numero_factura ?? item.id ?? '');
    if (
        !window.confirm(
            `Se revertira la anulacion de la factura ${numero}. La factura volvera a ser valida y no podra anularse nuevamente. Deseas continuar?`,
        )
    ) {
        return;
    }

    await store.revertirAnulacion(item);
};
const applyFilters = () => store.load();
const clearFilters = async () => {
    store.resetFilters();
    await store.load();
};
const changeScope = async (scope: string) => {
    store.state.filters.scope = scope;
    await store.load();
};
const downloadFile = (url?: string | null) => {
    if (url) window.open(url, '_blank', 'noopener,noreferrer');
};
const openSiatConsult = (item: Factura) => {
    const url = String(item.consulta_siat_url ?? '');

    if (!url) {
        store.state.generalError =
            'No se pudo abrir la consulta SIAT porque la factura no tiene una URL fiscal disponible.';
        return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
};
const normalizeCardInput = (
    key: 'numero_tarjeta_inicio' | 'numero_tarjeta_fin',
    value: string,
) => {
    store.state.emitForm[key] = value.replace(/\D+/g, '').slice(0, 4);
};

watch(
    () => store.state.emitForm.codigo_metodo_pago,
    (codigo) => {
        if (!metodoPagoRequiresCardNumber(codigo)) {
            store.state.emitForm.numero_tarjeta_inicio = '';
            store.state.emitForm.numero_tarjeta_fin = '';
        }
        if (!metodoPagoRequiresGiftCardAmount(codigo))
            store.state.emitForm.monto_gift_card = '';
    },
);
watch(
    facturas,
    (items) => {
        if (!items.length) {
            selectedFacturaId.value = null;
            return;
        }
        if (
            !items.some((item) => Number(item.id) === selectedFacturaId.value)
        ) {
            selectedFacturaId.value = Number(
                items.find((item) =>
                    ['observada', 'rechazada'].includes(
                        String(item.estado_factura),
                    ),
                )?.id ?? items[0].id,
            );
        }
    },
    { deep: true },
);

onMounted(store.load);
</script>

<template>
    <ModulePageLayout
        compact
        title="Facturacion SIAT"
        description="Controla la emision, envio y estado de tus facturas."
        :breadcrumbs="[
            { title: 'Panel principal', href: '/dashboard' },
            { title: 'Facturacion SIAT', href: '/facturacion/facturas' },
        ]"
    >
        <template #actions>
            <Button
                v-if="capabilities.emitir"
                class="h-11 bg-[#168447] px-5 text-white hover:bg-[#116f3b]"
                :disabled="store.state.loading"
                @click="openEmitDialog"
            >
                <Plus class="size-5" /> Emitir factura
            </Button>
        </template>

        <div class="space-y-5 text-[#101713]">
            <div
                v-if="store.state.generalError || store.state.generalSuccess"
                :class="[
                    'flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm',
                    store.state.generalError
                        ? 'border-red-200 bg-red-50 text-red-800'
                        : 'border-[#bde5cc] bg-[#eaf7ef] text-[#126c3a]',
                ]"
                role="status"
            >
                <div class="flex items-start gap-3">
                    <AlertCircle
                        v-if="store.state.generalError"
                        class="mt-0.5 size-5 shrink-0"
                    />
                    <CheckCircle2 v-else class="mt-0.5 size-5 shrink-0" />
                    <span>{{
                        store.state.generalError || store.state.generalSuccess
                    }}</span>
                </div>
                <button
                    type="button"
                    class="rounded p-1 hover:bg-black/5"
                    aria-label="Cerrar mensaje"
                    @click="
                        store.state.generalError = '';
                        store.state.generalSuccess = '';
                    "
                >
                    <X class="size-4" />
                </button>
            </div>

            <section
                v-if="isSuperadmin"
                class="rounded-xl border border-[#cfdad3] bg-white p-4 shadow-sm"
                aria-labelledby="siat-health-title"
            >
                <div
                    class="grid gap-4 md:grid-cols-[1.25fr_repeat(4,1fr)] md:items-center"
                >
                    <div
                        class="flex items-center gap-4 md:border-r md:border-[#dfe7e2] md:pr-4"
                    >
                        <div
                            :class="[
                                'flex size-12 shrink-0 items-center justify-center rounded-xl',
                                health.operativo
                                    ? 'bg-[#eaf7ef] text-[#168447]'
                                    : 'bg-amber-50 text-amber-700',
                            ]"
                        >
                            <CheckCircle2
                                v-if="health.operativo"
                                class="size-7"
                            /><TriangleAlert v-else class="size-7" />
                        </div>
                        <div>
                            <h2 id="siat-health-title" class="font-bold">
                                {{
                                    health.operativo
                                        ? 'Servicios operativos'
                                        : 'Requiere configuracion'
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-[#536158]">
                                {{ health.mensaje || 'No disponible' }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-[#6c786f]">Ambiente</p>
                        <p class="mt-1 font-bold text-[#168447] capitalize">
                            {{ health.ambiente || 'No disponible' }}
                        </p>
                        <p class="text-xs text-[#6c786f]">
                            {{ health.contexto?.sucursal || 'Sin sucursal'
                            }}<template v-if="health.contexto?.punto_venta">
                                · {{ health.contexto.punto_venta }}</template
                            >
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6c786f]">CUIS vigente</p>
                        <p
                            class="mt-1 truncate font-bold"
                            :title="health.cuis?.codigo"
                        >
                            {{ health.cuis?.codigo || 'No disponible' }}
                        </p>
                        <Link
                            v-if="!health.cuis"
                            href="/facturacion/cuis"
                            class="text-xs font-semibold text-[#168447] hover:underline"
                            >Obtener CUIS</Link
                        >
                        <p v-else class="text-xs text-[#6c786f]">
                            Hasta
                            {{ formatDate(health.cuis.fecha_vigencia, false) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6c786f]">Vigencia CUFD</p>
                        <p
                            class="mt-1 font-bold"
                            :class="
                                cufdRemaining !== null && cufdRemaining <= 1
                                    ? 'text-amber-700'
                                    : 'text-[#168447]'
                            "
                        >
                            {{
                                cufdRemaining === null
                                    ? 'No disponible'
                                    : cufdRemaining > 0
                                      ? `${cufdRemaining} ${cufdRemaining === 1 ? 'dia' : 'dias'}`
                                      : 'Vencido'
                            }}
                        </p>
                        <Link
                            v-if="!health.cufd"
                            href="/facturacion/cufd"
                            class="text-xs font-semibold text-[#168447] hover:underline"
                            >Obtener CUFD</Link
                        >
                        <p v-else class="text-xs text-[#6c786f]">
                            Hasta
                            {{ formatDate(health.cufd.fecha_vigencia, false) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-[#6c786f]">
                            Ultima sincronizacion
                        </p>
                        <p class="mt-1 font-bold">
                            {{
                                formatDate(health.ultima_sincronizacion?.fecha)
                            }}
                        </p>
                        <Link
                            href="/facturacion/sincronizaciones-siat"
                            class="text-xs font-semibold text-[#168447] hover:underline"
                            >Gestionar catalogos</Link
                        >
                    </div>
                </div>
            </section>

            <section
                v-if="isSuperadmin"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Resumen de facturacion"
            >
                <article
                    v-for="card in [
                        {
                            key: 'emitidas_hoy',
                            label: 'Emitidas hoy',
                            icon: FileCheck2,
                            tone: 'green',
                        },
                        {
                            key: 'pendientes',
                            label: 'Pendientes de envio',
                            icon: Send,
                            tone: 'amber',
                        },
                        {
                            key: 'con_observaciones',
                            label: 'Observadas o rechazadas',
                            icon: AlertCircle,
                            tone: 'red',
                        },
                        {
                            key: 'anuladas',
                            label: 'Anuladas',
                            icon: Ban,
                            tone: 'gray',
                        },
                    ]"
                    :key="card.key"
                    class="rounded-xl border border-[#dbe4de] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-4">
                        <div
                            :class="[
                                'flex size-12 items-center justify-center rounded-xl',
                                card.tone === 'green'
                                    ? 'bg-[#eaf7ef] text-[#168447]'
                                    : card.tone === 'amber'
                                      ? 'bg-amber-50 text-amber-700'
                                      : card.tone === 'red'
                                        ? 'bg-red-50 text-red-600'
                                        : 'bg-[#f0f2f1] text-[#59645d]',
                            ]"
                        >
                            <component :is="card.icon" class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm text-[#3d4941]">
                                {{ card.label }}
                            </p>
                            <p class="text-2xl font-bold">
                                {{ kpis[card.key]?.count ?? 0 }}
                            </p>
                            <p class="text-xs text-[#6c786f]">
                                {{ formatMoney(kpis[card.key]?.amount) }}
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <div
                :class="[
                    'grid gap-4',
                    isSuperadmin
                        ? '2xl:grid-cols-[minmax(0,1fr)_280px]'
                        : 'grid-cols-1',
                ]"
            >
                <section
                    class="overflow-hidden rounded-xl border border-[#d5dfd8] bg-white shadow-sm"
                >
                    <div
                        class="flex overflow-x-auto border-b border-[#dce5df] px-3"
                        role="tablist"
                        aria-label="Vistas de facturacion"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            role="tab"
                            :aria-selected="
                                store.state.filters.scope === tab.value
                            "
                            :class="[
                                'border-b-2 px-4 py-3 text-sm font-semibold whitespace-nowrap',
                                store.state.filters.scope === tab.value
                                    ? 'border-[#168447] text-[#168447]'
                                    : 'border-transparent text-[#536158] hover:text-[#101713]',
                            ]"
                            @click="changeScope(tab.value)"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.value !== 'facturas' && tab.count"
                                class="ml-1 rounded-full bg-[#f0f4f1] px-2 py-0.5 text-xs"
                                >{{ tab.count }}</span
                            >
                        </button>
                    </div>
                    <form
                        class="grid gap-3 border-b border-[#dce5df] bg-[#fbfcfb] p-3 md:grid-cols-2 xl:grid-cols-[1.45fr_repeat(4,minmax(120px,0.8fr))_auto]"
                        @submit.prevent="applyFilters"
                    >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#6c786f]"
                            /><Input
                                v-model="store.state.filters.search"
                                class="company-input pl-9"
                                aria-label="Buscar factura, cliente, venta o CUF"
                                placeholder="Factura, cliente, venta o CUF"
                            />
                        </div>
                        <Input
                            v-model="store.state.filters.fecha_desde"
                            type="date"
                            class="company-input"
                            aria-label="Fecha desde"
                        />
                        <Input
                            v-model="store.state.filters.fecha_hasta"
                            type="date"
                            class="company-input"
                            aria-label="Fecha hasta"
                        />
                        <select
                            v-model="store.state.filters.estado_factura"
                            class="company-select"
                            aria-label="Estado SIAT"
                        >
                            <option value="">Todos los estados</option>
                            <option
                                v-for="estado in metaList('estados_factura')"
                                :key="estado.value"
                                :value="estado.value"
                            >
                                {{ estado.label }}
                            </option>
                        </select>
                        <select
                            v-model="store.state.filters.sucursal_id"
                            class="company-select"
                            aria-label="Sucursal"
                        >
                            <option value="">Todas las sucursales</option>
                            <option
                                v-for="sucursal in metaList('sucursales')"
                                :key="sucursal.id"
                                :value="String(sucursal.id)"
                            >
                                {{ sucursal.nombre }}
                            </option>
                        </select>
                        <div class="flex gap-2">
                            <Button
                                type="submit"
                                class="bg-[#168447] text-white hover:bg-[#116f3b]"
                                :disabled="store.state.loading"
                                ><Filter class="size-4" />Filtrar</Button
                            ><Button
                                type="button"
                                variant="outline"
                                class="px-3"
                                aria-label="Limpiar filtros"
                                @click="clearFilters"
                                ><RotateCcw class="size-4"
                            /></Button>
                        </div>
                    </form>

                    <div
                        v-if="store.state.loading"
                        class="flex min-h-64 items-center justify-center gap-3 text-sm text-[#536158]"
                    >
                        <LoaderCircle
                            class="size-5 animate-spin text-[#168447]"
                        />
                        Cargando facturas...
                    </div>
                    <div
                        v-else-if="!facturas.length"
                        class="flex min-h-64 flex-col items-center justify-center px-6 text-center"
                    >
                        <FileText class="size-10 text-[#9aa69e]" />
                        <h3 class="mt-3 font-bold">
                            No hay facturas para mostrar
                        </h3>
                        <p class="mt-1 max-w-md text-sm text-[#66736a]">
                            Ajusta los filtros o emite una factura directa.
                        </p>
                        <Button
                            v-if="
                                capabilities.emitir && ventasFacturables.length
                            "
                            class="mt-4 bg-[#168447] text-white hover:bg-[#116f3b]"
                            @click="openEmitDialog"
                            ><Plus class="size-4" />Emitir factura</Button
                        >
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead
                                class="bg-[#f5f8f6] text-left text-xs font-bold text-[#3f4b43]"
                            >
                                <tr>
                                    <th
                                        v-for="column in [
                                            {
                                                key: 'numero_factura',
                                                label: 'Factura',
                                            },
                                            {
                                                key: 'fecha_emision',
                                                label: 'Fecha',
                                            },
                                            {
                                                key: 'cliente',
                                                label: 'Cliente',
                                            },
                                            {
                                                key: 'monto_total',
                                                label: 'Total',
                                                align: 'right',
                                            },
                                            {
                                                key: 'estado_factura',
                                                label: 'Estado',
                                            },
                                        ]"
                                        :key="column.key"
                                        :class="[
                                            'px-4 py-3',
                                            column.align === 'right'
                                                ? 'text-right'
                                                : '',
                                        ]"
                                    >
                                        <button
                                            type="button"
                                            :class="[
                                                'inline-flex items-center gap-1.5 hover:text-[#168447]',
                                                column.align === 'right'
                                                    ? 'ml-auto'
                                                    : '',
                                            ]"
                                            :aria-label="`Ordenar por ${column.label}`"
                                            @click="
                                                changeSort(
                                                    column.key as SortKey,
                                                )
                                            "
                                        >
                                            {{ column.label }}
                                            <component
                                                :is="
                                                    sortIcon(
                                                        column.key as SortKey,
                                                    )
                                                "
                                                class="size-3.5"
                                            />
                                        </button>
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in sortedFacturas"
                                    :key="item.id"
                                    :class="[
                                        'cursor-pointer border-t border-[#e3e9e5] transition hover:bg-[#f5f8f6]',
                                        selectedFacturaId === Number(item.id)
                                            ? [
                                                  'observada',
                                                  'rechazada',
                                              ].includes(
                                                  String(item.estado_factura),
                                              )
                                                ? 'bg-red-50/70'
                                                : 'bg-[#eef8f2]'
                                            : '',
                                    ]"
                                    tabindex="0"
                                    @click="selectFactura(item)"
                                    @keydown.enter="selectFactura(item)"
                                >
                                    <td class="px-4 py-3">
                                        <p class="font-bold">
                                            FAC-{{
                                                String(
                                                    item.numero_factura ||
                                                        item.id,
                                                ).padStart(7, '0')
                                            }}
                                        </p>
                                        <p
                                            v-if="isSuperadmin"
                                            class="max-w-36 truncate text-xs text-[#6c786f]"
                                            :title="item.cuf"
                                        >
                                            {{ item.cuf || 'Sin CUF' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(item.fecha_emision) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <p
                                            class="max-w-44 truncate font-medium"
                                        >
                                            {{
                                                item.cliente?.razon_social ||
                                                item.cliente?.nombre ||
                                                'Sin cliente'
                                            }}
                                        </p>
                                        <p class="text-xs text-[#6c786f]">
                                            {{
                                                item.cliente?.nit_ci ||
                                                'Sin documento'
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold"
                                    >
                                        {{ formatMoney(item.monto_total) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex rounded-md border px-2.5 py-1 text-xs font-bold',
                                                statusClass(
                                                    item.estado_factura,
                                                ),
                                            ]"
                                            >{{
                                                statusLabel(item.estado_factura)
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3" @click.stop>
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="border-[#cbd8cf] text-[#126c3a]"
                                                @click="openDetail(item)"
                                                ><Eye
                                                    class="size-4"
                                                />Detalle</Button
                                            ><DropdownMenu
                                                ><DropdownMenuTrigger as-child
                                                    ><Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-9"
                                                        :aria-label="`Mas acciones para factura ${item.numero_factura || item.id}`"
                                                        ><MoreVertical
                                                            class="size-4" /></Button></DropdownMenuTrigger
                                                ><DropdownMenuContent
                                                    align="end"
                                                    class="w-52"
                                                    ><DropdownMenuItem
                                                        v-if="
                                                            item.can_consult &&
                                                            capabilities.consultar
                                                        "
                                                        @select="
                                                            openSiatConsult(
                                                                item,
                                                            )
                                                        "
                                                        ><ExternalLink
                                                            class="size-4"
                                                        />Consultar en
                                                        SIAT</DropdownMenuItem
                                                    ><DropdownMenuItem
                                                        v-if="
                                                            item.can_retry &&
                                                            capabilities.reintentar
                                                        "
                                                        @select="
                                                            requestRetry(item)
                                                        "
                                                        ><Send
                                                            class="size-4"
                                                        />Reintentar
                                                        envio</DropdownMenuItem
                                                    ><DropdownMenuSeparator
                                                        v-if="
                                                            capabilities.descargar
                                                        "
                                                    /><DropdownMenuItem
                                                        v-if="
                                                            capabilities.descargar &&
                                                            item.pdf_download_url
                                                        "
                                                        @select="
                                                            downloadFile(
                                                                item.pdf_download_url,
                                                            )
                                                        "
                                                        ><FileText
                                                            class="size-4"
                                                        />Abrir
                                                        PDF</DropdownMenuItem
                                                    ><DropdownMenuItem
                                                        v-if="
                                                            capabilities.descargar &&
                                                            item.xml_download_url
                                                        "
                                                        @select="
                                                            downloadFile(
                                                                item.xml_download_url,
                                                            )
                                                        "
                                                        ><Download
                                                            class="size-4"
                                                        />Abrir
                                                        XML</DropdownMenuItem
                                                    ><DropdownMenuSeparator
                                                        v-if="
                                                            item.can_cancel &&
                                                            capabilities.anular
                                                        "
                                                    /><DropdownMenuItem
                                                        v-if="
                                                            item.can_reverse_cancellation &&
                                                            capabilities.anular
                                                        "
                                                        @select="
                                                            executeRevertirAnulacion(
                                                                item,
                                                            )
                                                        "
                                                        ><RotateCcw
                                                            class="size-4"
                                                        />Revertir
                                                        anulacion</DropdownMenuItem
                                                    ><DropdownMenuSeparator
                                                        v-if="
                                                            item.can_reverse_cancellation &&
                                                            capabilities.anular
                                                        "
                                                    /><DropdownMenuItem
                                                        v-if="
                                                            item.can_cancel &&
                                                            capabilities.anular
                                                        "
                                                        class="text-red-700 focus:text-red-700"
                                                        @select="
                                                            openAnularDialog(
                                                                item,
                                                            )
                                                        "
                                                        ><Ban
                                                            class="size-4"
                                                        />Anular
                                                        factura</DropdownMenuItem
                                                    ></DropdownMenuContent
                                                ></DropdownMenu
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-[#dce5df] px-4 py-3 text-xs text-[#66736a]"
                    >
                        <span>Mostrando {{ facturas.length }} factura(s)</span
                        ><span>Facturas emitidas: {{ facturas.length }}</span>
                    </div>
                </section>

                <aside
                    v-if="isSuperadmin"
                    class="rounded-xl border border-[#d5dfd8] bg-white p-4 shadow-sm"
                    aria-live="polite"
                >
                    <template v-if="selectedAttention"
                        ><div
                            class="flex items-center gap-2 font-bold text-red-700"
                        >
                            <AlertCircle class="size-5" />Requiere atencion
                        </div>
                        <dl
                            class="mt-4 space-y-2 border-b border-[#e1e7e3] pb-4 text-sm"
                        >
                            <div>
                                <dt class="text-xs text-[#6c786f]">Factura</dt>
                                <dd class="font-bold">
                                    FAC-{{
                                        String(
                                            selectedAttention.numero_factura ||
                                                selectedAttention.id,
                                        ).padStart(7, '0')
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#6c786f]">Venta</dt>
                                <dd>
                                    {{
                                        selectedAttention.venta?.numero_venta ||
                                        '-'
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4">
                            <h3 class="text-sm font-bold">Motivo</h3>
                            <p class="mt-1 text-sm leading-6 text-[#4f5b53]">
                                {{ attentionMessage }}
                            </p>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-xs font-bold text-[#6c786f]">
                                Codigo tecnico
                            </h3>
                            <p class="mt-1 font-mono text-xs break-words">
                                {{
                                    selectedAttention.codigo_estado ||
                                    'No disponible'
                                }}
                            </p>
                        </div>
                        <div
                            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3"
                        >
                            <p
                                class="flex items-center gap-2 text-sm font-bold text-amber-900"
                            >
                                <TriangleAlert class="size-4" />Siguiente paso
                                recomendado
                            </p>
                            <p class="mt-1 text-sm leading-5 text-amber-900/80">
                                {{ nextStep }}
                            </p>
                        </div>
                        <Button
                            v-if="
                                selectedAttention.can_retry &&
                                capabilities.reintentar
                            "
                            class="mt-4 w-full bg-[#168447] text-white hover:bg-[#116f3b]"
                            @click="requestRetry(selectedAttention)"
                            ><Send class="size-4" />Reintentar envio</Button
                        ><Button
                            variant="outline"
                            class="mt-2 w-full"
                            @click="openDetail(selectedAttention)"
                            ><FileText class="size-4" />Ver detalle
                            tecnico</Button
                        ></template
                    >
                    <template v-else
                        ><div
                            class="flex items-center gap-2 font-bold text-[#168447]"
                        >
                            <CheckCircle2 class="size-5" />Seguimiento de
                            factura
                        </div>
                        <p class="mt-3 text-sm leading-6 text-[#5f6c63]">
                            Selecciona una fila para revisar su estado y
                            trazabilidad. Las facturas observadas o rechazadas
                            mostraran aqui el siguiente paso.
                        </p>
                        <div
                            v-if="selectedFactura"
                            class="mt-5 rounded-lg bg-[#f5f8f6] p-3 text-sm"
                        >
                            <p class="font-bold">
                                FAC-{{
                                    String(
                                        selectedFactura.numero_factura ||
                                            selectedFactura.id,
                                    ).padStart(7, '0')
                                }}
                            </p>
                            <p class="mt-1 text-[#66736a]">
                                {{
                                    statusLabel(selectedFactura.estado_factura)
                                }}
                                · {{ formatMoney(selectedFactura.monto_total) }}
                            </p>
                        </div></template
                    >
                </aside>
            </div>

            <section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_290px]">
                <div
                    v-if="isSuperadmin"
                    class="rounded-xl border border-[#d5dfd8] bg-white p-4 shadow-sm"
                >
                    <h2 class="font-bold">
                        Trazabilidad de la factura seleccionada
                    </h2>
                    <div
                        v-if="selectedFactura"
                        class="mt-5 grid gap-3 sm:grid-cols-4"
                    >
                        <div
                            v-for="step in [
                                { key: 'generada', label: 'Generada' },
                                {
                                    key: 'firmada',
                                    label:
                                        selectedFactura.timeline?.firmada
                                            ?.required === false
                                            ? 'Firma no requerida'
                                            : 'Firmada',
                                },
                                {
                                    key: 'enviada',
                                    label:
                                        selectedFactura.codigo_emision === 2
                                            ? 'Pendiente de paquete'
                                            : 'Enviada',
                                },
                                { key: 'validada', label: 'Validada' },
                            ]"
                            :key="step.key"
                            class="relative"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    :class="[
                                        'flex size-9 shrink-0 items-center justify-center rounded-full border-2',
                                        selectedFactura.timeline?.[step.key]
                                            ?.complete
                                            ? 'border-[#168447] bg-[#168447] text-white'
                                            : [
                                                    'observada',
                                                    'rechazada',
                                                ].includes(
                                                    String(
                                                        selectedFactura.estado_factura,
                                                    ),
                                                ) && step.key === 'validada'
                                              ? 'border-red-400 bg-red-50 text-red-600'
                                              : 'border-[#cfd8d2] bg-white text-[#87928a]',
                                    ]"
                                >
                                    <Check
                                        v-if="
                                            selectedFactura.timeline?.[step.key]
                                                ?.complete
                                        "
                                        class="size-5"
                                    /><AlertCircle
                                        v-else-if="
                                            ['observada', 'rechazada'].includes(
                                                String(
                                                    selectedFactura.estado_factura,
                                                ),
                                            ) && step.key === 'validada'
                                        "
                                        class="size-5"
                                    /><Clock3 v-else class="size-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold">
                                        {{ step.label }}
                                    </p>
                                    <p class="text-xs text-[#6c786f]">
                                        {{
                                            selectedFactura.timeline?.[step.key]
                                                ?.at
                                                ? formatDate(
                                                      selectedFactura.timeline[
                                                          step.key
                                                      ].at,
                                                  )
                                                : selectedFactura.timeline?.[
                                                        step.key
                                                    ]?.complete
                                                  ? 'Completada, hora no disponible'
                                                  : step.key === 'validada'
                                                    ? statusLabel(
                                                          selectedFactura.estado_factura,
                                                      )
                                                    : 'Sin registro disponible'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-[#66736a]">
                        Selecciona una factura para ver sus hitos disponibles.
                    </p>
                </div>
                <div
                    v-if="isSuperadmin"
                    :class="[
                        'rounded-xl border p-4 shadow-sm',
                        health.contingencia
                            ? 'border-amber-200 bg-amber-50'
                            : 'border-[#cbe2d3] bg-[#f3faf5]',
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <TriangleAlert
                            v-if="health.contingencia"
                            class="size-6 text-amber-700"
                        /><CheckCircle2 v-else class="size-6 text-[#168447]" />
                        <div>
                            <h2 class="font-bold">Modo contingencia</h2>
                            <p
                                class="text-sm"
                                :class="
                                    health.contingencia
                                        ? 'text-amber-900'
                                        : 'text-[#536158]'
                                "
                            >
                                {{
                                    health.contingencia
                                        ? 'Existe una contingencia activa'
                                        : 'No hay contingencia activa'
                                }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="health.contingencia"
                        class="mt-3 text-sm text-amber-900/80"
                    >
                        Evento {{ health.contingencia.codigo_evento }}:
                        {{ health.contingencia.descripcion }}
                    </p>
                    <Link
                        href="/facturacion/eventos-significativos"
                        class="mt-3 inline-flex items-center gap-2 text-sm font-bold text-[#168447] hover:underline"
                        ><ServerCog class="size-4" />Gestionar
                        contingencias</Link
                    >
                </div>
            </section>
        </div>
        <Dialog v-model:open="retryDialogOpen"
            ><DialogContent class="max-w-md"
                ><DialogHeader
                    ><div
                        class="mb-2 flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-700"
                    >
                        <CircleEllipsis class="size-6" />
                    </div>
                    <DialogTitle class="text-left">Reintentar envio</DialogTitle
                    ><DialogDescription class="text-left"
                        >Se volvera a generar y enviar la factura
                        {{ pendingRetry?.numero_factura || pendingRetry?.id }}
                        con los datos actuales de la venta. El resultado solo se
                        marcara como exitoso si SIAT lo
                        confirma.</DialogDescription
                    ></DialogHeader
                >
                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" @click="retryDialogOpen = false"
                        >Cancelar</Button
                    ><Button
                        class="bg-[#168447] text-white hover:bg-[#116f3b]"
                        :disabled="store.state.processing"
                        @click="executeRetry"
                        ><LoaderCircle
                            v-if="store.state.processing"
                            class="size-4 animate-spin"
                        /><Send v-else class="size-4" />{{
                            store.state.processing
                                ? 'Reintentando...'
                                : 'Reintentar envio'
                        }}</Button
                    >
                </div></DialogContent
            ></Dialog
        >

        <FacturaDetalleModal
            v-model:open="detailDialogOpen"
            :factura="store.state.currentItem"
            :show-technical-details="isSuperadmin"
        />
        <FacturaAnulacionModal
            v-model:open="anularDialogOpen"
            :factura="selectedFactura"
            :form="store.state.anularForm"
            :motivos="metaList('motivos_anulacion')"
            :errors="store.state.errors"
            :processing="store.state.processing"
            @submit="submitAnular"
        />
    </ModulePageLayout>
</template>

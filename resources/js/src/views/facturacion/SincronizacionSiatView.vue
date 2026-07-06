<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { useSiatSyncStore } from '@/src/stores/facturacion/siatSyncStore';
import {
    AlertCircle,
    AlertTriangle,
    Building2,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    CloudCog,
    Eye,
    History,
    LoaderCircle,
    MapPin,
    RefreshCw,
    Ruler,
    Search,
    Settings2,
    Store,
    WalletCards,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type Item = Record<string, unknown>;
type TabKey = 'historial' | 'metodos' | 'unidades';
type SortKey = 'fecha' | 'catalogo' | 'estado' | 'sucursal' | 'punto_venta';

const props = defineProps<{
    catalogOnly?: boolean;
}>();
const catalogOnly = computed(() => props.catalogOnly === true);
const store = useSiatSyncStore();
const activeTab = ref<TabKey>(catalogOnly.value ? 'metodos' : 'historial');
const selectedItem = ref<Item | null>(null);
const search = ref('');
const methodsSearch = ref('');
const unitsSearch = ref('');
const sortBy = ref<SortKey>('fecha');
const sortDirection = ref<'asc' | 'desc'>('desc');
const currentPage = ref(1);
const methodsPage = ref(1);
const unitsPage = ref(1);
const pageSize = ref(8);
const catalogPageSize = ref(8);

const meta = computed(() => store.state.meta);
const siatProfile = computed(() => (meta.value.siat as Item | undefined) ?? {});
const sucursales = computed(() => (meta.value.sucursales as Item[] | undefined) ?? []);
const puntosVenta = computed(() => (meta.value.puntos_venta as Item[] | undefined) ?? []);
const metodosPago = computed(() => (meta.value.metodos_pago as Item[] | undefined) ?? []);
const unidadesMedida = computed(() => (meta.value.unidades_medida as Item[] | undefined) ?? []);
const puedeGestionarCatalogos = computed(() => Boolean(meta.value.puede_gestionar_catalogos));
const puedeSincronizar = computed(() => Boolean(meta.value.puede_sincronizar));
const catalogosTotales = computed(() => Number(meta.value.catalogos_totales ?? (meta.value.catalogos as Item[] | undefined)?.length ?? 0));
const pageTitle = computed(() => catalogOnly.value ? 'Catalogos SIAT' : 'Sincronizacion SIAT');
const pageDescription = computed(() =>
    catalogOnly.value
        ? 'Administra metodos de pago y unidades de medida disponibles para la operacion.'
        : 'Manten control sobre los catalogos fiscales, su historial y las opciones operativas de la empresa.',
);
const visibleTabs = computed(() => [
    ...(catalogOnly.value ? [] : [{ key: 'historial', label: 'Historial', icon: History }]),
    { key: 'metodos', label: 'Metodos de pago', icon: WalletCards },
    { key: 'unidades', label: 'Unidades de medida', icon: Ruler },
]);

const sucursalSeleccionada = computed(() =>
    sucursales.value.find((item) => String(item.id) === String(store.state.form.sucursal_id)),
);
const puntosVentaDisponibles = computed(() =>
    puntosVenta.value.filter((item) => String(item.sucursal_id) === String(store.state.form.sucursal_id)),
);
const puntoVentaSeleccionado = computed(() =>
    puntosVenta.value.find((item) => String(item.id) === String(store.state.form.punto_venta_id)),
);

const historialContexto = computed(() => {
    const codigoSucursal = String(sucursalSeleccionada.value?.codigo ?? '');
    const codigoPunto = String(puntoVentaSeleccionado.value?.codigo ?? '');

    return store.state.items.filter((item) =>
        (!codigoSucursal || String(item.codigo_sucursal) === codigoSucursal)
        && (!codigoPunto || String(item.codigo_punto_venta) === codigoPunto),
    );
});

const historialFiltrado = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    const filtered = historialContexto.value.filter((item) => {
        if (!term) return true;

        return [
            item.tipo_catalogo,
            item.estado,
            item.observacion,
            item.codigo_sucursal,
            item.codigo_punto_venta,
            (item.usuario as Item | undefined)?.name,
        ].some((value) => String(value ?? '').toLocaleLowerCase('es').includes(term));
    });

    return [...filtered].sort((left, right) => {
        const comparison = sortableValue(left, sortBy.value).localeCompare(
            sortableValue(right, sortBy.value),
            'es',
            { numeric: true, sensitivity: 'base' },
        );

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(historialFiltrado.value.length / pageSize.value)));
const historialPaginado = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return historialFiltrado.value.slice(start, start + pageSize.value);
});

const metodosFiltrados = computed(() => {
    const term = methodsSearch.value.trim().toLocaleLowerCase('es');

    return [...metodosPago.value]
        .filter((item) => !term || [item.descripcion, item.codigo_clasificador].some((value) =>
            String(value ?? '').toLocaleLowerCase('es').includes(term),
        ))
        .sort((left, right) => {
            const order = Number(left.orden_operativo ?? 0) - Number(right.orden_operativo ?? 0);
            return order || String(left.descripcion ?? '').localeCompare(String(right.descripcion ?? ''), 'es');
        });
});

const unidadesFiltradas = computed(() => {
    const term = unitsSearch.value.trim().toLocaleLowerCase('es');

    return unidadesMedida.value.filter((item) => !term || [item.descripcion, item.codigo_clasificador].some((value) =>
        String(value ?? '').toLocaleLowerCase('es').includes(term),
    ));
});

const totalMethodPages = computed(() => Math.max(1, Math.ceil(metodosFiltrados.value.length / catalogPageSize.value)));
const totalUnitPages = computed(() => Math.max(1, Math.ceil(unidadesFiltradas.value.length / catalogPageSize.value)));
const metodosPaginados = computed(() => paginate(metodosFiltrados.value, methodsPage.value, catalogPageSize.value));
const unidadesPaginadas = computed(() => paginate(unidadesFiltradas.value, unitsPage.value, catalogPageSize.value));

const ultimaFecha = computed(() => historialContexto.value[0]?.fecha_sincronizacion_iso ?? historialContexto.value[0]?.fecha_sincronizacion ?? null);
const ultimoLote = computed(() => {
    if (!ultimaFecha.value) return [];
    const key = String(ultimaFecha.value);
    return historialContexto.value.filter((item) => String(item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion) === key);
});
const catalogosSinObservacion = computed(() => ultimoLote.value.filter((item) => item.estado === 'exitosa').length);
const estadoGlobal = computed(() => {
    if (!ultimaFecha.value) return { label: 'Sin ejecución registrada', tone: 'neutral' };
    if (catalogosTotales.value > 0 && catalogosSinObservacion.value >= catalogosTotales.value) {
        return { label: 'Última ejecución completa', tone: 'success' };
    }
    if (catalogosSinObservacion.value > 0) return { label: 'Última ejecución parcial', tone: 'warning' };
    return { label: 'Última ejecución observada', tone: 'danger' };
});

const ejecucionesHoy = computed(() => uniqueRuns(historialContexto.value.filter((item) => isToday(item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion))).length);
const exitosasUltimosSieteDias = computed(() => historialContexto.value.filter((item) =>
    item.estado === 'exitosa' && isWithinDays(item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion, 7),
).length);
const observadasUltimosSieteDias = computed(() => historialContexto.value.filter((item) =>
    item.estado !== 'exitosa' && isWithinDays(item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion, 7),
).length);
const metodosVisibles = computed(() => metodosPago.value.filter((item) => item.estado && item.habilitado_venta).length);
const metodoPredeterminado = computed(() => metodosPago.value.find((item) => item.estado && item.habilitado_venta && item.es_predeterminado));
const unidadesActivas = computed(() => unidadesMedida.value.filter((item) => item.estado && item.habilitado_uso).length);
const contingenciaActiva = computed(() => {
    const eventos = (meta.value.contingencias_activas as Item[] | undefined) ?? [];
    return eventos.find((item) =>
        String(item.sucursal_id) === String(store.state.form.sucursal_id)
        && String(item.punto_venta_id) === String(store.state.form.punto_venta_id),
    );
});
const syncDetails = computed(() =>
    (store.state.lastSyncResponse?.detalle as Record<string, Item> | undefined) ?? {},
);

watch(
    () => store.state.form.sucursal_id,
    (sucursalId) => {
        const validPoint = puntosVentaDisponibles.value.some((item) => String(item.id) === String(store.state.form.punto_venta_id));
        if (!sucursalId) store.state.form.punto_venta_id = '';
        else if (!validPoint) store.state.form.punto_venta_id = String(puntosVentaDisponibles.value[0]?.id ?? '');
    },
);
watch([search, pageSize, sortBy, sortDirection], () => currentPage.value = 1);
watch(methodsSearch, () => methodsPage.value = 1);
watch(unitsSearch, () => unitsPage.value = 1);
watch(totalPages, (pages) => currentPage.value = Math.min(currentPage.value, pages));
watch(totalMethodPages, (pages) => methodsPage.value = Math.min(methodsPage.value, pages));
watch(totalUnitPages, (pages) => unitsPage.value = Math.min(unitsPage.value, pages));
watch(historialPaginado, (items) => {
    if (!selectedItem.value || !items.some((item) => item.id === selectedItem.value?.id)) {
        selectedItem.value = items[0] ?? null;
    }
}, { immediate: true });
watch(
    () => [store.state.form.sucursal_id, store.state.form.punto_venta_id],
    () => {
        currentPage.value = 1;
        selectedItem.value = historialContexto.value[0] ?? null;
    },
);

onMounted(async () => {
    await store.load();

    store.state.form.sucursal_id ||= String(sucursales.value[0]?.id ?? '');
    store.state.form.punto_venta_id ||= String(puntosVentaDisponibles.value[0]?.id ?? '');
    selectedItem.value = historialContexto.value[0] ?? null;
});

function paginate(items: Item[], pageNumber: number, size: number) {
    return items.slice((pageNumber - 1) * size, pageNumber * size);
}

function sortableValue(item: Item, field: SortKey) {
    const values: Record<SortKey, unknown> = {
        fecha: item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion,
        catalogo: item.tipo_catalogo,
        estado: item.estado,
        sucursal: item.codigo_sucursal,
        punto_venta: item.codigo_punto_venta,
    };
    return String(values[field] ?? '');
}

function parseDate(value: unknown) {
    if (!value) return null;
    const date = new Date(String(value));
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(value: unknown) {
    const date = parseDate(value);
    return date
        ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'short', timeStyle: 'medium' }).format(date)
        : 'No registrada';
}

function isToday(value: unknown) {
    const date = parseDate(value);
    const today = new Date();
    return Boolean(date && date.toDateString() === today.toDateString());
}

function isWithinDays(value: unknown, days: number) {
    const date = parseDate(value);
    return Boolean(date && date.getTime() >= Date.now() - days * 86_400_000);
}

function uniqueRuns(items: Item[]) {
    return [...new Set(items.map((item) => `${item.codigo_sucursal}-${item.codigo_punto_venta}-${item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion}`))];
}

function catalogLabel(value: unknown) {
    return String(value ?? 'Catálogo sin nombre')
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toLocaleUpperCase('es'));
}

async function updateMethodOrder(item: Item, event: Event) {
    const value = Number((event.target as HTMLInputElement).value);
    await store.updateMetodoPagoOperativo(item, { orden_operativo: Number.isFinite(value) ? Math.max(0, Math.trunc(value)) : 0 });
}
</script>

<template>
    <ModulePageLayout
        compact
        :title="pageTitle"
        :description="pageDescription"
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: pageTitle, href: catalogOnly ? '/facturacion/catalogos-siat' : '/facturacion/sincronizaciones-siat' },
        ]"
    >
        <template v-if="!catalogOnly" #actions>
            <Button
                class="min-h-11 bg-[#08752f] px-5 font-semibold text-white shadow-sm hover:bg-[#075f28]"
                :disabled="store.state.syncing || !puedeSincronizar || !store.state.form.sucursal_id || !store.state.form.punto_venta_id"
                @click="store.sync"
            >
                <LoaderCircle v-if="store.state.syncing" class="mr-2 size-4 animate-spin" />
                <RefreshCw v-else class="mr-2 size-4" />
                {{ store.state.syncing ? 'Sincronizando…' : 'Sincronizar catálogos' }}
            </Button>
        </template>

        <div class="space-y-4 text-[#172019]">
            <div v-if="store.state.generalError" class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <AlertCircle class="mt-0.5 size-4 shrink-0" />
                <span class="flex-1">{{ store.state.generalError }}</span>
                <button type="button" aria-label="Cerrar mensaje" @click="store.state.generalError = ''"><XCircle class="size-4" /></button>
            </div>
            <div v-if="store.state.generalSuccess" class="flex items-start gap-3 rounded-xl border border-[#b8dfc5] bg-[#edf8f0] px-4 py-3 text-sm text-[#145c2d]">
                <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
                <span class="flex-1">{{ store.state.generalSuccess }}</span>
                <button type="button" aria-label="Cerrar mensaje" @click="store.state.generalSuccess = ''"><XCircle class="size-4" /></button>
            </div>

            <section v-if="!catalogOnly" class="overflow-hidden rounded-2xl border border-[#dce5df] bg-white shadow-[0_12px_34px_-28px_rgba(6,42,24,0.65)]">
                <div v-if="store.state.loading" class="grid animate-pulse gap-px bg-[#e5ece7] sm:grid-cols-2 xl:grid-cols-5">
                    <div v-for="index in 5" :key="index" class="h-24 bg-white p-5"><div class="h-4 w-24 rounded bg-[#e8eee9]" /><div class="mt-3 h-5 w-32 rounded bg-[#dce5df]" /></div>
                </div>
                <div v-else class="grid gap-px bg-[#dfe8e2] sm:grid-cols-2 xl:grid-cols-5">
                    <div class="flex min-h-24 items-center gap-3 bg-white p-4">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-[#e8f6ed] text-[#08752f]"><CloudCog class="size-5" /></span>
                        <div><p class="text-xs font-medium text-[#657269]">Ambiente</p><p class="mt-1 font-semibold">{{ (siatProfile.environment as Item | undefined)?.label || 'No definido' }}</p><p class="text-xs text-[#77837b]">Configuración activa</p></div>
                    </div>
                    <div class="bg-white p-4"><p class="text-xs font-medium text-[#657269]">Modalidad</p><p class="mt-2 font-semibold text-[#145c2d]">{{ (siatProfile.tipo_facturacion as Item | undefined)?.label || 'No definida' }}</p><p class="mt-1 text-xs text-[#77837b]">{{ siatProfile.ready_for_live_calls ? 'Credenciales configuradas' : 'Configuración incompleta para llamadas' }}</p></div>
                    <div class="bg-white p-4"><p class="text-xs font-medium text-[#657269]">Última sincronización</p><p class="mt-2 font-semibold">{{ formatDate(ultimaFecha) }}</p><p class="mt-1 text-xs text-[#77837b]">Contexto seleccionado</p></div>
                    <div class="bg-white p-4"><p class="text-xs font-medium text-[#657269]">Catálogos sin observación</p><p class="mt-1 text-2xl font-bold text-[#08752f]">{{ catalogosSinObservacion }}</p><p class="text-xs text-[#77837b]">de {{ catalogosTotales }} en la última ejecución</p></div>
                    <div class="flex items-center gap-3 bg-white p-4">
                        <span :class="['flex size-10 items-center justify-center rounded-full', estadoGlobal.tone === 'success' ? 'bg-[#08752f] text-white' : estadoGlobal.tone === 'warning' ? 'bg-amber-100 text-amber-700' : estadoGlobal.tone === 'danger' ? 'bg-rose-100 text-rose-700' : 'bg-[#edf1ee] text-[#66736a]']">
                            <CheckCircle2 v-if="estadoGlobal.tone === 'success'" class="size-5" /><AlertTriangle v-else-if="estadoGlobal.tone === 'warning'" class="size-5" /><AlertCircle v-else-if="estadoGlobal.tone === 'danger'" class="size-5" /><History v-else class="size-5" />
                        </span>
                        <div><p class="font-semibold">{{ estadoGlobal.label }}</p><p class="mt-1 text-xs text-[#77837b]">Basado en registros locales</p></div>
                    </div>
                </div>
            </section>

            <section v-if="!catalogOnly" class="grid gap-4 rounded-2xl border border-[#dce5df] bg-white p-4 shadow-[0_12px_34px_-28px_rgba(6,42,24,0.65)] md:grid-cols-2">
                <div class="company-field">
                    <Label class="company-label flex items-center gap-2"><Building2 class="size-4 text-[#08752f]" />Sucursal</Label>
                    <select v-model="store.state.form.sucursal_id" class="company-select border-[#cfd9d2] bg-white">
                        <option value="">Selecciona una sucursal</option>
                        <option v-for="item in sucursales" :key="String(item.id)" :value="String(item.id)">{{ item.nombre }} · Cód. {{ item.codigo }}</option>
                    </select>
                    <InputError :message="store.state.errors.sucursal_id?.[0]" />
                </div>
                <div class="company-field">
                    <Label class="company-label flex items-center gap-2"><Store class="size-4 text-[#08752f]" />Punto de venta</Label>
                    <select v-model="store.state.form.punto_venta_id" class="company-select border-[#cfd9d2] bg-white" :disabled="!store.state.form.sucursal_id">
                        <option value="">Selecciona un punto de venta</option>
                        <option v-for="item in puntosVentaDisponibles" :key="String(item.id)" :value="String(item.id)">{{ item.nombre }} · Cód. {{ item.codigo }}</option>
                    </select>
                    <InputError :message="store.state.errors.punto_venta_id?.[0]" />
                </div>
            </section>

            <div v-if="!catalogOnly && store.state.syncing" class="rounded-2xl border border-[#b8dfc5] bg-[#f2faf4] p-4">
                <div class="flex items-center gap-3 text-sm font-semibold text-[#145c2d]"><LoaderCircle class="size-4 animate-spin" />Sincronización en curso</div>
                <p class="mt-1 text-sm text-[#526158]">La solicitud espera respuestas por catálogo. No cierres esta pantalla.</p>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#d9eadf]"><div class="h-full w-1/3 animate-pulse rounded-full bg-[#08752f]" /></div>
            </div>

            <section v-if="!catalogOnly && store.state.lastSyncResponse" class="rounded-2xl border border-[#dce5df] bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold">Resultado de la última solicitud</h2><p class="mt-1 text-sm text-[#657269]">{{ store.state.lastSyncResponse.message }}</p></div><span class="rounded-full bg-[#edf1ee] px-3 py-1 text-xs font-semibold text-[#526158]">{{ Object.keys(syncDetails).length }} respuestas</span></div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="(detail, key) in syncDetails" :key="key" class="rounded-xl border border-[#e0e8e2] p-3 text-sm">
                        <div class="flex items-center gap-2 font-medium"><CheckCircle2 v-if="detail.success" class="size-4 text-[#08752f]" /><AlertTriangle v-else class="size-4 text-amber-600" />{{ catalogLabel(key) }}</div>
                        <p class="mt-2 line-clamp-2 text-xs text-[#657269]">{{ detail.message || 'Sin mensaje adicional.' }}</p>
                    </div>
                </div>
            </section>

            <nav class="flex gap-1 overflow-x-auto border-b border-[#dce5df]" aria-label="Secciones de sincronización">
                <button v-for="tab in visibleTabs" :key="tab.key" type="button" :class="['flex min-w-max items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition', activeTab === tab.key ? 'border-[#08752f] text-[#08752f]' : 'border-transparent text-[#657269] hover:text-[#27332b]']" @click="activeTab = tab.key as TabKey">
                    <component :is="tab.icon" class="size-4" />{{ tab.label }}
                </button>
            </nav>

            <div v-if="activeTab === 'historial'" class="grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(310px,0.72fr)]">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-[#dce5df] bg-white p-4"><div class="flex items-center gap-3"><span class="rounded-lg bg-[#e8f6ed] p-2 text-[#08752f]"><History class="size-5" /></span><div><p class="text-xs text-[#657269]">Ejecuciones hoy</p><p class="text-2xl font-bold">{{ ejecucionesHoy }}</p></div></div><p class="mt-2 text-xs text-[#77837b]">Agrupadas por fecha y contexto</p></div>
                        <div class="rounded-xl border border-[#dce5df] bg-white p-4"><div class="flex items-center gap-3"><span class="rounded-lg bg-[#e8f6ed] p-2 text-[#08752f]"><CheckCircle2 class="size-5" /></span><div><p class="text-xs text-[#657269]">Catálogos exitosos</p><p class="text-2xl font-bold">{{ exitosasUltimosSieteDias }}</p></div></div><p class="mt-2 text-xs text-[#77837b]">Últimos 7 días</p></div>
                        <div class="rounded-xl border border-[#dce5df] bg-white p-4"><div class="flex items-center gap-3"><span class="rounded-lg bg-amber-50 p-2 text-amber-700"><AlertTriangle class="size-5" /></span><div><p class="text-xs text-[#657269]">Observados</p><p class="text-2xl font-bold">{{ observadasUltimosSieteDias }}</p></div></div><p class="mt-2 text-xs text-[#77837b]">Últimos 7 días</p></div>
                        <div class="rounded-xl border border-[#dce5df] bg-white p-4"><div class="flex items-center gap-3"><span class="rounded-lg bg-[#edf1ee] p-2 text-[#526158]"><Clock3 class="size-5" /></span><div><p class="text-xs text-[#657269]">Última duración</p><p class="text-base font-bold">No registrada</p></div></div><p class="mt-2 text-xs text-[#77837b]">El contrato actual no mide duración</p></div>
                    </div>

                    <section class="overflow-hidden rounded-2xl border border-[#dce5df] bg-white">
                        <div class="grid gap-3 border-b border-[#e1e8e3] bg-[#f8faf8] p-4 lg:grid-cols-[minmax(220px,1fr)_180px_150px_auto] lg:items-end">
                            <div class="company-field"><Label class="company-label">Buscar</Label><div class="relative"><Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#77837b]" /><input v-model="search" class="company-input h-10 w-full rounded-md pl-9" placeholder="Catálogo, estado, usuario…" /></div></div>
                            <div class="company-field"><Label class="company-label">Ordenar por</Label><select v-model="sortBy" class="company-select bg-white"><option value="fecha">Fecha</option><option value="catalogo">Catálogo</option><option value="estado">Estado</option><option value="sucursal">Sucursal</option><option value="punto_venta">Punto de venta</option></select></div>
                            <div class="company-field"><Label class="company-label">Dirección</Label><select v-model="sortDirection" class="company-select bg-white"><option value="desc">Descendente</option><option value="asc">Ascendente</option></select></div>
                            <div class="company-field"><Label class="company-label">Filas</Label><select v-model="pageSize" class="company-select min-w-20 bg-white"><option :value="5">5</option><option :value="8">8</option><option :value="15">15</option><option :value="25">25</option></select></div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] text-sm">
                                <thead class="bg-[#f3f6f4] text-left text-xs font-semibold tracking-wide text-[#526158] uppercase"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Catálogo</th><th class="px-4 py-3">Sucursal / punto</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3 text-center">Detalle</th></tr></thead>
                                <tbody>
                                    <tr v-for="item in historialPaginado" :key="String(item.id)" :class="['cursor-pointer border-t border-[#e5ebe7] transition hover:bg-[#f3faf5]', selectedItem?.id === item.id ? 'bg-[#edf8f0]' : 'bg-white']" @click="selectedItem = item">
                                        <td class="whitespace-nowrap px-4 py-3">{{ formatDate(item.fecha_sincronizacion_iso ?? item.fecha_sincronizacion) }}</td><td class="px-4 py-3 font-medium">{{ catalogLabel(item.tipo_catalogo) }}</td><td class="px-4 py-3"><span class="block">Sucursal {{ item.codigo_sucursal }}</span><span class="text-xs text-[#77837b]">Punto {{ item.codigo_punto_venta }}</span></td><td class="px-4 py-3"><span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', item.estado === 'exitosa' ? 'bg-[#e6f6eb] text-[#08752f]' : item.estado === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700']">{{ item.estado }}</span></td><td class="px-4 py-3">{{ (item.usuario as Item | undefined)?.name || 'No registrado' }}</td><td class="px-4 py-3 text-center"><button type="button" class="rounded-md border border-[#cfd9d2] p-2 text-[#526158] hover:border-[#08752f] hover:text-[#08752f]" aria-label="Ver detalle" @click.stop="selectedItem = item"><Eye class="size-4" /></button></td>
                                    </tr>
                                    <tr v-if="!store.state.loading && historialPaginado.length === 0"><td colspan="6" class="px-6 py-12 text-center"><History class="mx-auto size-8 text-[#9aa59d]" /><p class="mt-3 font-medium">No hay sincronizaciones para mostrar</p><p class="mt-1 text-sm text-[#657269]">Cambia los filtros o ejecuta una sincronización cuando el contexto esté configurado.</p></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-col gap-3 border-t border-[#e1e8e3] px-4 py-3 text-sm text-[#657269] sm:flex-row sm:items-center sm:justify-between"><span>Mostrando {{ historialPaginado.length }} de {{ historialFiltrado.length }} registros</span><div class="flex items-center gap-2"><Button variant="outline" size="sm" :disabled="currentPage <= 1" @click="currentPage--"><ChevronLeft class="size-4" />Anterior</Button><span class="px-2">{{ currentPage }} / {{ totalPages }}</span><Button variant="outline" size="sm" :disabled="currentPage >= totalPages" @click="currentPage++">Siguiente<ChevronRight class="size-4" /></Button></div></div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start">
                    <section class="rounded-2xl border border-[#dce5df] bg-white p-5">
                        <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold tracking-wider text-[#08752f] uppercase">Detalle de sincronización</p><h2 class="mt-1 font-semibold">{{ selectedItem ? catalogLabel(selectedItem.tipo_catalogo) : 'Sin selección' }}</h2></div><span class="rounded-xl bg-[#e8f6ed] p-3 text-[#08752f]"><CloudCog class="size-5" /></span></div>
                        <div v-if="selectedItem" class="mt-5 space-y-4 text-sm">
                            <dl class="grid grid-cols-2 gap-4"><div><dt class="text-xs text-[#77837b]">Fecha</dt><dd class="mt-1 font-medium">{{ formatDate(selectedItem.fecha_sincronizacion_iso ?? selectedItem.fecha_sincronizacion) }}</dd></div><div><dt class="text-xs text-[#77837b]">Estado</dt><dd class="mt-1 font-medium capitalize">{{ selectedItem.estado }}</dd></div><div><dt class="text-xs text-[#77837b]">Sucursal</dt><dd class="mt-1 font-medium">Código {{ selectedItem.codigo_sucursal }}</dd></div><div><dt class="text-xs text-[#77837b]">Punto de venta</dt><dd class="mt-1 font-medium">Código {{ selectedItem.codigo_punto_venta }}</dd></div><div><dt class="text-xs text-[#77837b]">Usuario</dt><dd class="mt-1 font-medium">{{ (selectedItem.usuario as Item | undefined)?.name || 'No registrado' }}</dd></div><div><dt class="text-xs text-[#77837b]">Duración</dt><dd class="mt-1 font-medium">No registrada</dd></div></dl>
                            <div class="rounded-xl border border-[#e0e8e2] bg-[#f8faf8] p-4"><p class="text-xs font-semibold text-[#526158]">Resumen de respuesta</p><p class="mt-2 text-sm leading-6">{{ selectedItem.observacion || 'No hay una observación almacenada para este registro.' }}</p></div>
                            <details class="rounded-xl border border-[#e0e8e2] p-4"><summary class="cursor-pointer text-sm font-semibold text-[#526158]">Detalle técnico</summary><dl class="mt-3 space-y-2 text-xs text-[#657269]"><div class="flex justify-between gap-3"><dt>ID local</dt><dd class="font-mono">{{ selectedItem.id }}</dd></div><div class="flex justify-between gap-3"><dt>Creado</dt><dd>{{ formatDate(selectedItem.created_at) }}</dd></div><div class="flex justify-between gap-3"><dt>Actualizado</dt><dd>{{ formatDate(selectedItem.updated_at) }}</dd></div></dl></details>
                            <p class="text-xs text-[#77837b]">No hay reintento individual disponible porque el backend actual solo sincroniza el conjunto completo de catálogos.</p>
                        </div>
                        <div v-else class="py-10 text-center text-sm text-[#657269]"><Eye class="mx-auto size-7 text-[#9aa59d]" /><p class="mt-2">Selecciona una fila para revisar su detalle.</p></div>
                    </section>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div class="rounded-xl border border-[#dce5df] bg-white p-4"><p class="text-sm font-semibold">Métodos visibles</p><p class="mt-2 text-2xl font-bold">{{ metodosVisibles }} <span class="text-sm font-normal text-[#77837b]">de {{ metodosPago.length }}</span></p><p class="mt-3 text-xs text-[#657269]">Predeterminado</p><p class="mt-1 truncate text-sm font-medium">{{ metodoPredeterminado?.descripcion || 'Sin método disponible' }}</p><button class="mt-4 text-sm font-semibold text-[#08752f]" type="button" @click="activeTab = 'metodos'">Administrar métodos</button></div>
                        <div class="rounded-xl border border-[#dce5df] bg-white p-4"><p class="text-sm font-semibold">Unidades activas</p><p class="mt-2 text-2xl font-bold">{{ unidadesActivas }} <span class="text-sm font-normal text-[#77837b]">de {{ unidadesMedida.length }}</span></p><p class="mt-3 text-xs text-[#657269]">Uso local</p><p class="mt-1 text-sm font-medium">Solo unidades habilitadas</p><button class="mt-4 text-sm font-semibold text-[#08752f]" type="button" @click="activeTab = 'unidades'">Administrar unidades</button></div>
                    </div>
                </aside>
            </div>

            <section v-else-if="activeTab === 'metodos'" class="overflow-hidden rounded-2xl border border-[#dce5df] bg-white">
                <header class="flex flex-col gap-4 border-b border-[#e1e8e3] p-5 lg:flex-row lg:items-center lg:justify-between"><div><h2 class="flex items-center gap-2 font-semibold"><WalletCards class="size-5 text-[#08752f]" />Métodos de pago operativos</h2><p class="mt-1 text-sm text-[#657269]">Controla cuáles aparecen en ventas, el predeterminado único y su orden. El estado fiscal SIAT no se modifica.</p></div><div class="relative w-full lg:max-w-sm"><Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#77837b]" /><input v-model="methodsSearch" class="company-input h-10 w-full rounded-md pl-9" placeholder="Buscar por código o descripción…" /></div></header>
                <div v-if="!puedeGestionarCatalogos" class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">Tu usuario puede consultar el catálogo, pero solo un administrador puede cambiar su configuración operativa.</div>
                <div class="divide-y divide-[#e5ebe7]">
                    <article v-for="item in metodosPaginados" :key="String(item.id)" class="grid gap-4 p-4 lg:grid-cols-[minmax(220px,1fr)_150px_170px_190px] lg:items-center">
                        <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-medium">{{ item.descripcion }}</h3><span v-if="item.es_predeterminado" class="rounded-full bg-[#e6f6eb] px-2.5 py-1 text-xs font-semibold text-[#08752f]">Predeterminado</span><span v-if="!item.estado" class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">Inactivo en SIAT</span></div><p class="mt-1 text-xs text-[#77837b]">Código {{ item.codigo_clasificador }}</p></div>
                        <div><Label class="text-xs text-[#657269]">Orden operativo</Label><input :value="Number(item.orden_operativo ?? 0)" type="number" min="0" class="company-input mt-1 h-9 w-full rounded-md" :disabled="!puedeGestionarCatalogos || store.isUpdating(`metodo-${item.id}`)" @change="updateMethodOrder(item, $event)" /></div>
                        <div><p class="text-xs text-[#657269]">Visible en ventas</p><button type="button" :class="['mt-1 inline-flex min-w-28 items-center justify-center rounded-full px-3 py-1.5 text-xs font-semibold', item.estado && item.habilitado_venta ? 'bg-[#e6f6eb] text-[#08752f]' : 'bg-[#edf1ee] text-[#657269]']" :disabled="!puedeGestionarCatalogos || !item.estado || store.isUpdating(`metodo-${item.id}`)" @click="store.toggleMetodoPago(item)">{{ item.habilitado_venta ? 'Visible' : 'Oculto' }}</button></div>
                        <div class="flex justify-end"><Button variant="outline" size="sm" class="border-[#b9c9be] text-[#145c2d]" :disabled="!puedeGestionarCatalogos || !item.estado || item.es_predeterminado || store.isUpdating(`metodo-${item.id}`)" @click="store.updateMetodoPagoOperativo(item, { es_predeterminado: true })"><LoaderCircle v-if="store.isUpdating(`metodo-${item.id}`)" class="mr-2 size-4 animate-spin" /><Settings2 v-else class="mr-2 size-4" />{{ item.es_predeterminado ? 'Predeterminado' : 'Usar por defecto' }}</Button></div>
                    </article>
                    <div v-if="metodosPaginados.length === 0" class="px-6 py-12 text-center text-sm text-[#657269]">No hay métodos que coincidan con la búsqueda.</div>
                </div>
                <footer class="flex flex-col gap-3 border-t border-[#e1e8e3] px-5 py-4 text-sm text-[#657269] sm:flex-row sm:items-center sm:justify-between"><span>{{ metodosVisibles }} visibles de {{ metodosPago.length }} métodos</span><div class="flex items-center gap-2"><Button variant="outline" size="sm" :disabled="methodsPage <= 1" @click="methodsPage--"><ChevronLeft class="size-4" />Anterior</Button><span>{{ methodsPage }} / {{ totalMethodPages }}</span><Button variant="outline" size="sm" :disabled="methodsPage >= totalMethodPages" @click="methodsPage++">Siguiente<ChevronRight class="size-4" /></Button></div></footer>
            </section>

            <section v-else class="overflow-hidden rounded-2xl border border-[#dce5df] bg-white">
                <header class="flex flex-col gap-4 border-b border-[#e1e8e3] p-5 lg:flex-row lg:items-center lg:justify-between"><div><h2 class="flex items-center gap-2 font-semibold"><Ruler class="size-5 text-[#08752f]" />Unidades de medida SIAT</h2><p class="mt-1 text-sm text-[#657269]">Habilita para uso local solo las unidades vigentes en el catálogo fiscal.</p></div><div class="relative w-full lg:max-w-sm"><Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#77837b]" /><input v-model="unitsSearch" class="company-input h-10 w-full rounded-md pl-9" placeholder="Buscar por código o descripción…" /></div></header>
                <div v-if="!puedeGestionarCatalogos" class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">Tu usuario puede consultar las unidades, pero solo un administrador puede cambiar su disponibilidad.</div>
                <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="item in unidadesPaginadas" :key="String(item.id)" class="flex items-center justify-between gap-4 rounded-xl border border-[#dce5df] p-4"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="truncate font-medium">{{ item.descripcion }}</h3><span v-if="!item.estado" class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">Inactiva en SIAT</span></div><p class="mt-1 text-xs text-[#77837b]">Código {{ item.codigo_clasificador }}</p><p :class="['mt-2 text-xs font-semibold', item.estado && item.habilitado_uso ? 'text-[#08752f]' : 'text-[#77837b]']">{{ item.estado && item.habilitado_uso ? 'Habilitada para uso local' : 'No disponible para uso local' }}</p></div><Button variant="outline" size="sm" :disabled="!puedeGestionarCatalogos || !item.estado || store.isUpdating(`unidad-${item.id}`)" @click="store.toggleUnidadMedida(item)"><LoaderCircle v-if="store.isUpdating(`unidad-${item.id}`)" class="mr-2 size-4 animate-spin" />{{ item.habilitado_uso ? 'Desactivar' : 'Activar' }}</Button></article>
                    <div v-if="unidadesPaginadas.length === 0" class="col-span-full px-6 py-12 text-center text-sm text-[#657269]">No hay unidades que coincidan con la búsqueda.</div>
                </div>
                <footer class="flex flex-col gap-3 border-t border-[#e1e8e3] px-5 py-4 text-sm text-[#657269] sm:flex-row sm:items-center sm:justify-between"><span>{{ unidadesActivas }} activas de {{ unidadesMedida.length }} unidades</span><div class="flex items-center gap-2"><Button variant="outline" size="sm" :disabled="unitsPage <= 1" @click="unitsPage--"><ChevronLeft class="size-4" />Anterior</Button><span>{{ unitsPage }} / {{ totalUnitPages }}</span><Button variant="outline" size="sm" :disabled="unitsPage >= totalUnitPages" @click="unitsPage++">Siguiente<ChevronRight class="size-4" /></Button></div></footer>
            </section>

            <section v-if="!catalogOnly" :class="['flex flex-col gap-3 rounded-2xl border px-5 py-4 sm:flex-row sm:items-center sm:justify-between', contingenciaActiva ? 'border-amber-200 bg-amber-50' : 'border-[#dce5df] bg-white']">
                <div class="flex items-start gap-3"><AlertTriangle :class="['mt-0.5 size-5 shrink-0', contingenciaActiva ? 'text-amber-700' : 'text-[#77837b]']" /><div><p class="font-semibold">{{ contingenciaActiva ? 'Contingencia activa registrada' : 'Sin contingencia activa registrada' }}</p><p class="mt-1 text-sm text-[#657269]">{{ contingenciaActiva ? `${contingenciaActiva.descripcion || 'Evento significativo activo'} · desde ${formatDate(contingenciaActiva.fecha_inicio)}` : 'No existe un evento local activo para la sucursal y punto seleccionados.' }}</p></div></div>
                <a href="/facturacion/eventos-significativos" class="inline-flex items-center gap-2 text-sm font-semibold text-[#08752f]"><MapPin class="size-4" />Ver contingencias</a>
            </section>
        </div>
    </ModulePageLayout>
</template>

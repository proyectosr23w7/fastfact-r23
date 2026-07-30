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
import { ApiError } from '@/src/services/apiClient';
import { puntoVentaService } from '@/src/services/puntoVentaService';
import { sucursalService } from '@/src/services/sucursalService';
import { usePermissionStore } from '@/src/stores/permissionStore';
import {
    Building2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    House,
    MapPin,
    Pencil,
    Plus,
    Power,
    Printer,
    RefreshCw,
    Search,
    Store,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Sucursal = {
    id: number;
    codigo: number;
    nombre: string;
    municipio: string | null;
    direccion: string | null;
    telefono: string | null;
    estado: boolean;
    puntos_venta_count?: number;
};

type PuntoVenta = {
    id: number;
    sucursal_id: number;
    codigo: number;
    nombre: string;
    descripcion: string | null;
    tipo_impresion: 'carta' | 'media_carta' | 'ticket';
    estado: boolean;
    cuis_vigente?: {
        codigo: string;
        ambiente_facturacion: string;
        fecha_vigencia: string | null;
    } | null;
};

type ConfirmAction = {
    title: string;
    description: string;
    confirmLabel: string;
    run: () => Promise<void>;
};

defineProps<{ initialFocus?: 'sucursales' | 'puntos' }>();

const permissionStore = usePermissionStore();
const canManageSucursales = computed(() =>
    permissionStore.hasPermission('configuracion.sucursales.manage'),
);
const canManagePuntos = computed(() =>
    permissionStore.hasPermission('configuracion.puntos_venta.manage'),
);

const sucursales = ref<Sucursal[]>([]);
const puntosVenta = ref<PuntoVenta[]>([]);
const loading = ref(true);
const loadError = ref('');
const feedback = ref('');
const selectedSucursalId = ref<number | null>(null);
const expandedSucursalId = ref<number | null>(null);
const search = ref('');
const estadoFilter = ref('todos');
const impresionFilter = ref('todos');
const currentPage = ref(1);
const rowsPerPage = ref(10);

const sucursalDialogOpen = ref(false);
const puntoDialogOpen = ref(false);
const confirmDialogOpen = ref(false);
const savingSucursal = ref(false);
const savingPunto = ref(false);
const confirming = ref(false);
const editingSucursalId = ref<number | null>(null);
const editingPuntoId = ref<number | null>(null);
const sucursalErrors = ref<Record<string, string[]>>({});
const puntoErrors = ref<Record<string, string[]>>({});
const confirmAction = ref<ConfirmAction | null>(null);

const sucursalForm = reactive({
    codigo: 1,
    municipio: 'LA PAZ',
    direccion: '',
    telefono: '',
    estado: true,
});

const puntoForm = reactive({
    sucursal_id: '' as string | number,
    codigo: 0,
    nombre: '',
    descripcion: '',
    tipo_impresion: 'ticket' as PuntoVenta['tipo_impresion'],
    estado: true,
});

const normalize = (value: unknown) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const puntosBySucursal = (sucursalId: number) =>
    puntosVenta.value.filter(
        (punto) => Number(punto.sucursal_id) === Number(sucursalId),
    );

const formatImpresion = (tipo: PuntoVenta['tipo_impresion']) =>
    ({ ticket: 'Rollo', media_carta: 'Media carta', carta: 'Carta' })[tipo] ??
    tipo;

const formatDate = (value: unknown) => {
    if (!value) return 'Sin fecha';
    const date = new Date(String(value));
    if (Number.isNaN(date.getTime())) return 'Sin fecha';

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
};

const selectedSucursal = computed(
    () =>
        sucursales.value.find(
            (sucursal) =>
                Number(sucursal.id) === Number(selectedSucursalId.value),
        ) ?? null,
);

const selectedPuntos = computed(() =>
    selectedSucursal.value ? puntosBySucursal(selectedSucursal.value.id) : [],
);

const kpis = computed(() => ({
    sucursalesActivas: sucursales.value.filter((item) => item.estado).length,
    puntos: puntosVenta.value.length,
    casaMatriz: sucursales.value.filter((item) => Number(item.codigo) === 0)
        .length,
    puntosInactivos: puntosVenta.value.filter((item) => !item.estado).length,
}));

const filteredSucursales = computed(() => {
    const query = normalize(search.value.trim());

    return sucursales.value.filter((sucursal) => {
        const puntos = puntosBySucursal(sucursal.id);
        const branchMatchesSearch = [
            sucursal.codigo,
            sucursal.nombre,
            sucursal.municipio,
            sucursal.direccion,
            sucursal.telefono,
        ].some((value) => normalize(value).includes(query));
        const pointMatchesSearch = puntos.some((punto) =>
            [
                punto.codigo,
                punto.nombre,
                punto.descripcion,
                punto.cuis_vigente?.codigo,
            ].some((value) => normalize(value).includes(query)),
        );
        const searchMatches =
            !query || branchMatchesSearch || pointMatchesSearch;
        const stateMatches =
            estadoFilter.value === 'todos' ||
            sucursal.estado === (estadoFilter.value === 'activos') ||
            puntos.some(
                (punto) => punto.estado === (estadoFilter.value === 'activos'),
            );
        const printMatches =
            impresionFilter.value === 'todos' ||
            puntos.some(
                (punto) => punto.tipo_impresion === impresionFilter.value,
            );

        return searchMatches && stateMatches && printMatches;
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredSucursales.value.length / rowsPerPage.value)),
);

const paginatedSucursales = computed(() => {
    const start = (currentPage.value - 1) * rowsPerPage.value;
    return filteredSucursales.value.slice(start, start + rowsPerPage.value);
});

const visibleFrom = computed(() =>
    filteredSucursales.value.length
        ? (currentPage.value - 1) * rowsPerPage.value + 1
        : 0,
);
const visibleTo = computed(() =>
    Math.min(
        currentPage.value * rowsPerPage.value,
        filteredSucursales.value.length,
    ),
);

const filteredPointsFor = (sucursalId: number) => {
    const query = normalize(search.value.trim());

    return puntosBySucursal(sucursalId).filter((punto) => {
        const searchMatches =
            !query ||
            [
                punto.codigo,
                punto.nombre,
                punto.descripcion,
                punto.cuis_vigente?.codigo,
            ].some((value) => normalize(value).includes(query));
        const stateMatches =
            estadoFilter.value === 'todos' ||
            punto.estado === (estadoFilter.value === 'activos');
        const printMatches =
            impresionFilter.value === 'todos' ||
            punto.tipo_impresion === impresionFilter.value;

        return searchMatches && stateMatches && printMatches;
    });
};

const generatedSucursalName = computed(() =>
    Number(sucursalForm.codigo) === 0
        ? 'CASA MATRIZ'
        : `SUCURSAL ${Number(sucursalForm.codigo || 0)}`,
);

const load = async (preferredSucursalId?: number) => {
    loading.value = true;
    loadError.value = '';

    try {
        const [sucursalResponse, puntoResponse] = await Promise.all([
            sucursalService.list(),
            puntoVentaService.list(),
        ]);
        sucursales.value = sucursalResponse.data as Sucursal[];
        puntosVenta.value = puntoResponse.data as PuntoVenta[];

        const nextId =
            preferredSucursalId ??
            selectedSucursalId.value ??
            sucursales.value.find((item) => Number(item.codigo) === 0)?.id ??
            sucursales.value[0]?.id ??
            null;
        selectedSucursalId.value = sucursales.value.some(
            (item) => Number(item.id) === Number(nextId),
        )
            ? nextId
            : (sucursales.value[0]?.id ?? null);

        if (
            expandedSucursalId.value === null &&
            selectedSucursalId.value !== null
        ) {
            expandedSucursalId.value = selectedSucursalId.value;
        }
    } catch (error) {
        loadError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo cargar la informacion de sucursales.';
    } finally {
        loading.value = false;
    }
};

const clearFilters = () => {
    search.value = '';
    estadoFilter.value = 'todos';
    impresionFilter.value = 'todos';
};

const selectSucursal = (sucursalId: number) => {
    selectedSucursalId.value = sucursalId;
};

const toggleExpansion = (sucursalId: number) => {
    selectedSucursalId.value = sucursalId;
    expandedSucursalId.value =
        expandedSucursalId.value === sucursalId ? null : sucursalId;
};

const resetSucursalForm = () => {
    editingSucursalId.value = null;
    sucursalErrors.value = {};
    sucursalForm.codigo = 1;
    sucursalForm.municipio = 'LA PAZ';
    sucursalForm.direccion = '';
    sucursalForm.telefono = '';
    sucursalForm.estado = true;
};

const openCreateSucursal = () => {
    resetSucursalForm();
    const maxCodigo = sucursales.value.reduce(
        (max, item) => Math.max(max, Number(item.codigo)),
        0,
    );
    sucursalForm.codigo = maxCodigo + 1;
    sucursalDialogOpen.value = true;
};

const openEditSucursal = (sucursal: Sucursal) => {
    editingSucursalId.value = sucursal.id;
    sucursalErrors.value = {};
    sucursalForm.codigo = Number(sucursal.codigo);
    sucursalForm.municipio = sucursal.municipio ?? 'LA PAZ';
    sucursalForm.direccion = sucursal.direccion ?? '';
    sucursalForm.telefono = sucursal.telefono ?? '';
    sucursalForm.estado = Boolean(sucursal.estado);
    sucursalDialogOpen.value = true;
};

const saveSucursal = async () => {
    savingSucursal.value = true;
    sucursalErrors.value = {};

    try {
        const payload = {
            codigo: Number(sucursalForm.codigo),
            municipio: sucursalForm.municipio,
            direccion: sucursalForm.direccion,
            telefono: sucursalForm.telefono,
            estado: sucursalForm.estado,
        };
        const response = editingSucursalId.value
            ? await sucursalService.update(editingSucursalId.value, payload)
            : await sucursalService.create(payload);
        const saved = response.data as Sucursal;
        await load(saved.id);
        feedback.value = editingSucursalId.value
            ? 'Sucursal actualizada correctamente.'
            : 'Sucursal creada correctamente.';
        sucursalDialogOpen.value = false;
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            sucursalErrors.value = error.errors;
        } else {
            sucursalErrors.value = {
                general: [
                    error instanceof Error
                        ? error.message
                        : 'No se pudo guardar la sucursal.',
                ],
            };
        }
    } finally {
        savingSucursal.value = false;
    }
};

const resetPuntoForm = (sucursalId?: number) => {
    editingPuntoId.value = null;
    puntoErrors.value = {};
    puntoForm.sucursal_id =
        sucursalId ?? selectedSucursalId.value ?? sucursales.value[0]?.id ?? '';
    puntoForm.codigo = 0;
    puntoForm.nombre = '';
    puntoForm.descripcion = '';
    puntoForm.tipo_impresion = 'ticket';
    puntoForm.estado = true;
};

const openCreatePunto = (sucursalId?: number) => {
    resetPuntoForm(sucursalId);
    const siblings = puntoForm.sucursal_id
        ? puntosBySucursal(Number(puntoForm.sucursal_id))
        : [];
    puntoForm.codigo =
        siblings.reduce((max, item) => Math.max(max, Number(item.codigo)), -1) +
        1;
    puntoDialogOpen.value = true;
};

const openEditPunto = (punto: PuntoVenta) => {
    editingPuntoId.value = punto.id;
    puntoErrors.value = {};
    puntoForm.sucursal_id = punto.sucursal_id;
    puntoForm.codigo = Number(punto.codigo);
    puntoForm.nombre = punto.nombre;
    puntoForm.descripcion = punto.descripcion ?? '';
    puntoForm.tipo_impresion = punto.tipo_impresion;
    puntoForm.estado = Boolean(punto.estado);
    puntoDialogOpen.value = true;
};

const savePunto = async () => {
    savingPunto.value = true;
    puntoErrors.value = {};

    try {
        const payload = {
            sucursal_id: Number(puntoForm.sucursal_id),
            codigo: Number(puntoForm.codigo),
            nombre: puntoForm.nombre,
            descripcion: puntoForm.descripcion,
            tipo_impresion: puntoForm.tipo_impresion,
            estado: puntoForm.estado,
        };
        const response = editingPuntoId.value
            ? await puntoVentaService.update(editingPuntoId.value, payload)
            : await puntoVentaService.create(payload);
        const saved = response.data as PuntoVenta;
        await load(saved.sucursal_id);
        expandedSucursalId.value = saved.sucursal_id;
        feedback.value = editingPuntoId.value
            ? 'Punto de venta actualizado correctamente.'
            : 'Punto de venta creado correctamente.';
        puntoDialogOpen.value = false;
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            puntoErrors.value = error.errors;
        } else {
            puntoErrors.value = {
                general: [
                    error instanceof Error
                        ? error.message
                        : 'No se pudo guardar el punto de venta.',
                ],
            };
        }
    } finally {
        savingPunto.value = false;
    }
};

const requestToggleSucursal = (sucursal: Sucursal) => {
    const nextState = !sucursal.estado;
    confirmAction.value = {
        title: `${nextState ? 'Activar' : 'Desactivar'} sucursal`,
        description: `${nextState ? 'Se habilitara' : 'Se deshabilitara'} ${sucursal.nombre}. El codigo y sus datos se conservaran.`,
        confirmLabel: nextState ? 'Activar sucursal' : 'Desactivar sucursal',
        run: async () => {
            await sucursalService.update(sucursal.id, {
                codigo: Number(sucursal.codigo),
                municipio: sucursal.municipio ?? 'LA PAZ',
                direccion: sucursal.direccion ?? '',
                telefono: sucursal.telefono ?? '',
                estado: nextState,
            });
            await load(sucursal.id);
            feedback.value = `Sucursal ${nextState ? 'activada' : 'desactivada'} correctamente.`;
        },
    };
    confirmDialogOpen.value = true;
};

const requestTogglePunto = (punto: PuntoVenta) => {
    const nextState = !punto.estado;
    confirmAction.value = {
        title: `${nextState ? 'Activar' : 'Desactivar'} punto de venta`,
        description: `${nextState ? 'Se habilitara' : 'Se deshabilitara'} ${punto.nombre} sin eliminar su configuracion.`,
        confirmLabel: nextState ? 'Activar punto' : 'Desactivar punto',
        run: async () => {
            await puntoVentaService.update(punto.id, {
                sucursal_id: punto.sucursal_id,
                codigo: Number(punto.codigo),
                nombre: punto.nombre,
                descripcion: punto.descripcion ?? '',
                tipo_impresion: punto.tipo_impresion,
                estado: nextState,
            });
            await load(punto.sucursal_id);
            feedback.value = `Punto de venta ${nextState ? 'activado' : 'desactivado'} correctamente.`;
        },
    };
    confirmDialogOpen.value = true;
};

const executeConfirmation = async () => {
    if (!confirmAction.value) return;

    confirming.value = true;
    loadError.value = '';

    try {
        await confirmAction.value.run();
        confirmDialogOpen.value = false;
    } catch (error) {
        loadError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo completar la accion.';
        confirmDialogOpen.value = false;
    } finally {
        confirming.value = false;
    }
};

watch([search, estadoFilter, impresionFilter, rowsPerPage], () => {
    currentPage.value = 1;
});

watch(totalPages, (pages) => {
    if (currentPage.value > pages) currentPage.value = pages;
});

watch(feedback, (message) => {
    if (!message) return;
    window.setTimeout(() => {
        if (feedback.value === message) feedback.value = '';
    }, 4500);
});

onMounted(() => load());
</script>

<template>
    <ModulePageLayout
        title="Sucursales y puntos de venta"
        description="Organiza donde opera la empresa y como factura cada caja."
        :breadcrumbs="[
            { title: 'Configuracion', href: '/configuracion/configuracion' },
            {
                title: 'Sucursales y puntos de venta',
                href: '/configuracion/sucursales',
            },
        ]"
        compact
    >
        <template #actions>
            <div class="flex flex-wrap gap-2 sm:justify-end">
                <Button
                    v-if="canManageSucursales"
                    type="button"
                    class="company-action-primary h-11 px-5"
                    @click="openCreateSucursal"
                >
                    <Plus aria-hidden="true" />
                    Nueva sucursal
                </Button>
                <Button
                    v-if="canManagePuntos"
                    type="button"
                    class="company-action-primary h-11 px-5"
                    :disabled="!sucursales.length"
                    @click="openCreatePunto()"
                >
                    <Plus aria-hidden="true" />
                    Nuevo punto de venta
                </Button>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="feedback"
                role="status"
                class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                <span>{{ feedback }}</span>
                <button
                    type="button"
                    class="rounded-md p-1 hover:bg-emerald-100 focus-visible:ring-2 focus-visible:ring-emerald-600"
                    aria-label="Cerrar mensaje"
                    @click="feedback = ''"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div
                v-if="loadError"
                role="alert"
                class="flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <span class="flex items-center gap-2">
                    <CircleAlert class="size-5 shrink-0" aria-hidden="true" />
                    {{ loadError }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="load()"
                >
                    <RefreshCw aria-hidden="true" />
                    Reintentar
                </Button>
            </div>

            <section
                aria-label="Resumen de sucursales"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article class="company-panel flex items-center gap-4 p-4">
                    <span
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"
                    >
                        <Store class="size-7" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Sucursales activas
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ kpis.sucursalesActivas }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            De {{ sucursales.length }} sucursales
                        </p>
                    </div>
                </article>
                <article class="company-panel flex items-center gap-4 p-4">
                    <span
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"
                    >
                        <Printer class="size-7" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Puntos de venta
                        </p>
                        <p class="mt-1 text-2xl font-bold">{{ kpis.puntos }}</p>
                        <p class="text-xs text-muted-foreground">
                            En todas las sucursales
                        </p>
                    </div>
                </article>
                <article class="company-panel flex items-center gap-4 p-4">
                    <span
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700"
                    >
                        <House class="size-7" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Casa matriz
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ kpis.casaMatriz }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Codigo principal 0
                        </p>
                    </div>
                </article>
                <article class="company-panel flex items-center gap-4 p-4">
                    <span
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700"
                    >
                        <Power class="size-7" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">
                            Puntos inactivos
                        </p>
                        <p class="mt-1 text-2xl font-bold">
                            {{ kpis.puntosInactivos }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Fuera de servicio
                        </p>
                    </div>
                </article>
            </section>

            <section
                aria-label="Filtros"
                class="company-panel grid gap-3 p-3 md:grid-cols-[minmax(16rem,1fr)_12rem_14rem_auto]"
            >
                <label class="relative block">
                    <span class="sr-only"
                        >Buscar sucursal o punto de venta</span
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="search"
                        class="company-input h-11 pl-10"
                        placeholder="Buscar sucursal o punto de venta"
                    />
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-foreground"
                        >Estado</span
                    >
                    <select v-model="estadoFilter" class="company-select h-11">
                        <option value="todos">Todos</option>
                        <option value="activos">Activos</option>
                        <option value="inactivos">Inactivos</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-xs font-semibold text-foreground"
                        >Tipo de impresion</span
                    >
                    <select
                        v-model="impresionFilter"
                        class="company-select h-11"
                    >
                        <option value="todos">Todos</option>
                        <option value="ticket">Rollo</option>
                        <option value="media_carta">Media carta</option>
                        <option value="carta">Carta</option>
                    </select>
                </label>
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 self-end"
                    @click="clearFilters"
                >
                    <RefreshCw aria-hidden="true" />
                    Limpiar
                </Button>
            </section>

            <div
                class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_21rem]"
            >
                <section
                    aria-label="Listado de sucursales"
                    class="company-panel overflow-hidden"
                >
                    <div
                        v-if="loading"
                        class="space-y-3 p-5"
                        aria-live="polite"
                    >
                        <div
                            v-for="index in 4"
                            :key="index"
                            class="h-16 animate-pulse rounded-xl bg-muted"
                        />
                        <p class="sr-only">
                            Cargando sucursales y puntos de venta
                        </p>
                    </div>

                    <div
                        v-else-if="!filteredSucursales.length"
                        class="flex min-h-64 flex-col items-center justify-center p-8 text-center"
                    >
                        <span
                            class="flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <Search class="size-6" aria-hidden="true" />
                        </span>
                        <h2 class="mt-4 font-semibold">No hay resultados</h2>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            Ajusta la busqueda o limpia los filtros para volver
                            a ver las sucursales.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-4"
                            @click="clearFilters"
                            >Limpiar filtros</Button
                        >
                    </div>

                    <template v-else>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[940px] text-sm">
                                <thead
                                    class="border-b border-border bg-muted/45 text-left"
                                >
                                    <tr>
                                        <th class="w-12 px-4 py-3">
                                            <span class="sr-only"
                                                >Seleccionar</span
                                            >
                                        </th>
                                        <th class="px-3 py-3 font-semibold">
                                            Codigo
                                        </th>
                                        <th class="px-3 py-3 font-semibold">
                                            Sucursal
                                        </th>
                                        <th class="px-3 py-3 font-semibold">
                                            Municipio fiscal
                                        </th>
                                        <th class="px-3 py-3 font-semibold">
                                            Direccion
                                        </th>
                                        <th class="px-3 py-3 font-semibold">
                                            Telefono
                                        </th>
                                        <th
                                            class="px-3 py-3 text-center font-semibold"
                                        >
                                            Puntos de venta
                                        </th>
                                        <th class="px-3 py-3 font-semibold">
                                            Estado
                                        </th>
                                        <th class="w-12 px-3 py-3">
                                            <span class="sr-only"
                                                >Expandir</span
                                            >
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="sucursal in paginatedSucursales"
                                        :key="sucursal.id"
                                    >
                                        <tr
                                            :class="[
                                                'cursor-pointer border-b border-border/70 transition hover:bg-emerald-50/60',
                                                selectedSucursalId ===
                                                sucursal.id
                                                    ? 'bg-emerald-50/75'
                                                    : 'bg-card',
                                            ]"
                                            @click="selectSucursal(sucursal.id)"
                                        >
                                            <td class="px-4 py-4">
                                                <button
                                                    type="button"
                                                    role="radio"
                                                    :aria-checked="
                                                        selectedSucursalId ===
                                                        sucursal.id
                                                    "
                                                    :aria-label="`Seleccionar ${sucursal.nombre}`"
                                                    class="rounded-full focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2"
                                                    @click.stop="
                                                        selectSucursal(
                                                            sucursal.id,
                                                        )
                                                    "
                                                >
                                                    <span
                                                        :class="[
                                                            'block size-4 rounded-full border-2',
                                                            selectedSucursalId ===
                                                            sucursal.id
                                                                ? 'border-emerald-700 bg-emerald-700 ring-2 ring-emerald-100'
                                                                : 'border-border bg-white',
                                                        ]"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                            </td>
                                            <td class="px-3 py-4 font-medium">
                                                {{ sucursal.codigo }}
                                            </td>
                                            <td class="px-3 py-4 font-semibold">
                                                {{ sucursal.nombre }}
                                            </td>
                                            <td class="px-3 py-4 font-medium">
                                                {{
                                                    sucursal.municipio ||
                                                    'Sin municipio'
                                                }}
                                            </td>
                                            <td
                                                class="max-w-56 px-3 py-4 text-muted-foreground"
                                            >
                                                {{
                                                    sucursal.direccion ||
                                                    'Sin direccion'
                                                }}
                                            </td>
                                            <td
                                                class="px-3 py-4 text-muted-foreground"
                                            >
                                                {{ sucursal.telefono || '-' }}
                                            </td>
                                            <td
                                                class="px-3 py-4 text-center font-semibold"
                                            >
                                                {{
                                                    puntosBySucursal(
                                                        sucursal.id,
                                                    ).length
                                                }}
                                            </td>
                                            <td class="px-3 py-4">
                                                <span
                                                    :class="[
                                                        'inline-flex rounded-md px-2.5 py-1 text-xs font-semibold',
                                                        sucursal.estado
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : 'bg-rose-100 text-rose-800',
                                                    ]"
                                                >
                                                    {{
                                                        sucursal.estado
                                                            ? 'Activa'
                                                            : 'Inactiva'
                                                    }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-4">
                                                <button
                                                    type="button"
                                                    class="rounded-md p-2 hover:bg-emerald-100 focus-visible:ring-2 focus-visible:ring-emerald-700"
                                                    :aria-label="`${expandedSucursalId === sucursal.id ? 'Contraer' : 'Expandir'} ${sucursal.nombre}`"
                                                    :aria-expanded="
                                                        expandedSucursalId ===
                                                        sucursal.id
                                                    "
                                                    @click.stop="
                                                        toggleExpansion(
                                                            sucursal.id,
                                                        )
                                                    "
                                                >
                                                    <ChevronDown
                                                        :class="[
                                                            'size-4 transition-transform',
                                                            expandedSucursalId ===
                                                            sucursal.id
                                                                ? 'rotate-180'
                                                                : '',
                                                        ]"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                expandedSucursalId ===
                                                sucursal.id
                                            "
                                            class="border-b border-border bg-muted/20"
                                        >
                                            <td colspan="9" class="p-3">
                                                <div
                                                    class="rounded-xl border border-border bg-card p-3 shadow-sm"
                                                >
                                                    <div
                                                        class="mb-3 flex flex-wrap items-center justify-between gap-2"
                                                    >
                                                        <h3
                                                            class="font-semibold"
                                                        >
                                                            Puntos de venta de
                                                            {{
                                                                sucursal.nombre
                                                            }}
                                                        </h3>
                                                        <Button
                                                            v-if="
                                                                canManagePuntos
                                                            "
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            @click="
                                                                openCreatePunto(
                                                                    sucursal.id,
                                                                )
                                                            "
                                                        >
                                                            <Plus
                                                                aria-hidden="true"
                                                            />
                                                            Agregar punto
                                                        </Button>
                                                    </div>
                                                    <div
                                                        v-if="
                                                            filteredPointsFor(
                                                                sucursal.id,
                                                            ).length
                                                        "
                                                        class="overflow-x-auto"
                                                    >
                                                        <table
                                                            class="w-full min-w-[920px] text-xs"
                                                        >
                                                            <thead
                                                                class="border-y border-border bg-muted/40 text-left"
                                                            >
                                                                <tr>
                                                                    <th
                                                                        class="px-3 py-2 font-semibold"
                                                                    >
                                                                        Codigo
                                                                    </th>
                                                                    <th
                                                                        class="px-3 py-2 font-semibold"
                                                                    >
                                                                        Punto de
                                                                        venta
                                                                    </th>
                                                                    <th
                                                                        class="px-3 py-2 font-semibold"
                                                                    >
                                                                        Descripcion
                                                                    </th>
                                                                    <th
                                                                        class="px-3 py-2 font-semibold"
                                                                    >
                                                                        Impresion
                                                                    </th>
                                                                    <th
                                                                        class="px-3 py-2 font-semibold"
                                                                    >
                                                                        CUIS
                                                                        vigente
                                                                    </th>
                                                                    <th
                                                                        class="px-3 py-2 font-semibold"
                                                                    >
                                                                        Estado
                                                                    </th>
                                                                    <th
                                                                        class="px-3 py-2 text-right font-semibold"
                                                                    >
                                                                        Acciones
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr
                                                                    v-for="punto in filteredPointsFor(
                                                                        sucursal.id,
                                                                    )"
                                                                    :key="
                                                                        punto.id
                                                                    "
                                                                    class="border-b border-border/70 last:border-0"
                                                                >
                                                                    <td
                                                                        class="px-3 py-3 font-medium"
                                                                    >
                                                                        {{
                                                                            punto.codigo
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="px-3 py-3 font-semibold"
                                                                    >
                                                                        {{
                                                                            punto.nombre
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="max-w-52 px-3 py-3 text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            punto.descripcion ||
                                                                            '-'
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="px-3 py-3"
                                                                    >
                                                                        <span
                                                                            class="rounded-md border border-emerald-200 px-2 py-1 font-medium text-emerald-800"
                                                                        >
                                                                            {{
                                                                                formatImpresion(
                                                                                    punto.tipo_impresion,
                                                                                )
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                    <td
                                                                        class="px-3 py-3"
                                                                    >
                                                                        <div
                                                                            v-if="
                                                                                punto.cuis_vigente
                                                                            "
                                                                            class="space-y-1"
                                                                        >
                                                                            <p
                                                                                class="font-mono text-[11px] font-semibold text-emerald-800"
                                                                                :title="
                                                                                    punto
                                                                                        .cuis_vigente
                                                                                        .codigo
                                                                                "
                                                                            >
                                                                                {{
                                                                                    punto
                                                                                        .cuis_vigente
                                                                                        .codigo
                                                                                }}
                                                                            </p>
                                                                            <p
                                                                                class="text-[11px] text-muted-foreground"
                                                                            >
                                                                                {{
                                                                                    punto
                                                                                        .cuis_vigente
                                                                                        .ambiente_facturacion
                                                                                }}
                                                                                ·
                                                                                {{
                                                                                    formatDate(
                                                                                        punto
                                                                                            .cuis_vigente
                                                                                            .fecha_vigencia,
                                                                                    )
                                                                                }}
                                                                            </p>
                                                                        </div>
                                                                        <span
                                                                            v-else
                                                                            class="rounded-md bg-amber-50 px-2 py-1 font-semibold text-amber-800"
                                                                        >
                                                                            Sin
                                                                            CUIS
                                                                            vigente
                                                                        </span>
                                                                    </td>
                                                                    <td
                                                                        class="px-3 py-3"
                                                                    >
                                                                        <span
                                                                            :class="[
                                                                                'rounded-md px-2 py-1 font-semibold',
                                                                                punto.estado
                                                                                    ? 'bg-emerald-100 text-emerald-800'
                                                                                    : 'bg-rose-100 text-rose-800',
                                                                            ]"
                                                                        >
                                                                            {{
                                                                                punto.estado
                                                                                    ? 'Activo'
                                                                                    : 'Inactivo'
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                    <td
                                                                        class="px-3 py-3"
                                                                    >
                                                                        <div
                                                                            v-if="
                                                                                canManagePuntos
                                                                            "
                                                                            class="flex justify-end gap-1"
                                                                        >
                                                                            <Button
                                                                                type="button"
                                                                                variant="outline"
                                                                                size="sm"
                                                                                @click="
                                                                                    openEditPunto(
                                                                                        punto,
                                                                                    )
                                                                                "
                                                                            >
                                                                                <Pencil
                                                                                    aria-hidden="true"
                                                                                />
                                                                                Editar
                                                                            </Button>
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                @click="
                                                                                    requestTogglePunto(
                                                                                        punto,
                                                                                    )
                                                                                "
                                                                            >
                                                                                <Power
                                                                                    aria-hidden="true"
                                                                                />
                                                                                {{
                                                                                    punto.estado
                                                                                        ? 'Desactivar'
                                                                                        : 'Activar'
                                                                                }}
                                                                            </Button>
                                                                        </div>
                                                                        <span
                                                                            v-else
                                                                            class="block text-right text-muted-foreground"
                                                                            >Solo
                                                                            lectura</span
                                                                        >
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <p
                                                        v-else
                                                        class="rounded-lg bg-muted/50 px-4 py-6 text-center text-sm text-muted-foreground"
                                                    >
                                                        No hay puntos de venta
                                                        que coincidan con los
                                                        filtros.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <footer
                            class="flex flex-col gap-3 border-t border-border px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-muted-foreground">
                                Mostrando {{ visibleFrom }}-{{ visibleTo }} de
                                {{ filteredSucursales.length }} sucursales
                            </p>
                            <div class="flex items-center gap-2">
                                <label
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    Filas
                                    <select
                                        v-model.number="rowsPerPage"
                                        class="company-select h-9 w-20"
                                    >
                                        <option :value="5">5</option>
                                        <option :value="10">10</option>
                                        <option :value="20">20</option>
                                    </select>
                                </label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon-sm"
                                    :disabled="currentPage === 1"
                                    aria-label="Pagina anterior"
                                    @click="currentPage--"
                                >
                                    <ChevronLeft aria-hidden="true" />
                                </Button>
                                <span
                                    class="flex size-8 items-center justify-center rounded-md bg-emerald-700 font-semibold text-white"
                                    >{{ currentPage }}</span
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon-sm"
                                    :disabled="currentPage === totalPages"
                                    aria-label="Pagina siguiente"
                                    @click="currentPage++"
                                >
                                    <ChevronRight aria-hidden="true" />
                                </Button>
                            </div>
                        </footer>
                    </template>
                </section>

                <aside
                    class="company-panel overflow-hidden xl:sticky xl:top-4"
                    aria-label="Detalle de la sucursal"
                >
                    <div class="border-b border-border px-5 py-4">
                        <h2 class="text-lg font-semibold">
                            Detalle de la sucursal
                        </h2>
                    </div>
                    <div v-if="selectedSucursal" class="space-y-5 p-5">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-14 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"
                            >
                                <House
                                    v-if="Number(selectedSucursal.codigo) === 0"
                                    class="size-7"
                                    aria-hidden="true"
                                />
                                <Building2
                                    v-else
                                    class="size-7"
                                    aria-hidden="true"
                                />
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-lg font-bold">
                                        {{ selectedSucursal.nombre }}
                                    </h3>
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-1 text-xs font-semibold',
                                            selectedSucursal.estado
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-rose-100 text-rose-800',
                                        ]"
                                    >
                                        {{
                                            selectedSucursal.estado
                                                ? 'Activa'
                                                : 'Inactiva'
                                        }}
                                    </span>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    Codigo: {{ selectedSucursal.codigo }}
                                </p>
                            </div>
                        </div>

                        <dl
                            class="space-y-3 border-y border-border py-4 text-sm"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-semibold">
                                    Municipio fiscal
                                </dt>
                                <dd class="text-right text-muted-foreground">
                                    {{
                                        selectedSucursal.municipio ||
                                        'Sin municipio registrado'
                                    }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-semibold">Direccion</dt>
                                <dd class="text-right text-muted-foreground">
                                    {{
                                        selectedSucursal.direccion ||
                                        'Sin direccion registrada'
                                    }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-semibold">Telefono</dt>
                                <dd class="text-right text-muted-foreground">
                                    {{
                                        selectedSucursal.telefono ||
                                        'Sin telefono'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <h3 class="mb-3 text-sm font-semibold">
                                Resumen comercial
                            </h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    class="rounded-xl border border-border bg-muted/25 p-3"
                                >
                                    <Printer
                                        class="size-5 text-emerald-700"
                                        aria-hidden="true"
                                    />
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Puntos de venta
                                    </p>
                                    <p class="text-xl font-bold">
                                        {{ selectedPuntos.length }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-border bg-muted/25 p-3"
                                >
                                    <Store
                                        class="size-5 text-emerald-700"
                                        aria-hidden="true"
                                    />
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Puntos activos
                                    </p>
                                    <p class="text-xl font-bold">
                                        {{
                                            selectedPuntos.filter(
                                                (punto) => punto.estado,
                                            ).length
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-3 text-sm font-semibold">
                                Puntos de venta
                            </h3>
                            <div
                                v-if="selectedPuntos.length"
                                class="max-h-64 space-y-2 overflow-y-auto pr-1"
                            >
                                <article
                                    v-for="punto in selectedPuntos"
                                    :key="punto.id"
                                    class="flex items-center gap-3 rounded-xl border border-border p-3"
                                >
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                                    >
                                        <Printer
                                            class="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-semibold"
                                        >
                                            {{ punto.nombre }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Codigo: {{ punto.codigo }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatImpresion(
                                                    punto.tipo_impresion,
                                                )
                                            }}
                                        </p>
                                        <span
                                            :class="[
                                                'text-xs font-semibold',
                                                punto.estado
                                                    ? 'text-emerald-700'
                                                    : 'text-rose-700',
                                            ]"
                                        >
                                            {{
                                                punto.estado
                                                    ? 'Activo'
                                                    : 'Inactivo'
                                            }}
                                        </span>
                                    </div>
                                </article>
                            </div>
                            <p
                                v-else
                                class="rounded-xl border border-dashed border-border p-4 text-center text-sm text-muted-foreground"
                            >
                                Sin puntos de venta
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Button
                                v-if="canManageSucursales"
                                type="button"
                                variant="outline"
                                class="h-11 border-emerald-700 text-emerald-800 hover:bg-emerald-50"
                                @click="openEditSucursal(selectedSucursal)"
                            >
                                <Pencil aria-hidden="true" /> Editar sucursal
                            </Button>
                            <Button
                                v-if="canManageSucursales"
                                type="button"
                                variant="ghost"
                                class="h-10"
                                @click="requestToggleSucursal(selectedSucursal)"
                            >
                                <Power aria-hidden="true" />
                                {{
                                    selectedSucursal.estado
                                        ? 'Desactivar sucursal'
                                        : 'Activar sucursal'
                                }}
                            </Button>
                            <Button
                                v-if="canManagePuntos"
                                type="button"
                                class="company-action-primary h-11"
                                @click="openCreatePunto(selectedSucursal.id)"
                            >
                                <Plus aria-hidden="true" /> Agregar punto de
                                venta
                            </Button>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex min-h-72 flex-col items-center justify-center p-6 text-center text-muted-foreground"
                    >
                        <MapPin class="size-8" aria-hidden="true" />
                        <p class="mt-3 text-sm">
                            Selecciona una sucursal para ver el detalle.
                        </p>
                    </div>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="sucursalDialogOpen">
            <DialogContent class="max-w-2xl overflow-hidden p-0">
                <DialogHeader
                    class="company-hero p-6 pr-12 text-left text-white"
                >
                    <DialogTitle class="text-xl text-white">{{
                        editingSucursalId ? 'Editar sucursal' : 'Nueva sucursal'
                    }}</DialogTitle>
                    <DialogDescription class="text-emerald-50/80"
                        >El nombre se genera automaticamente segun el codigo
                        definido por el backend.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-5 p-6" @submit.prevent="saveSucursal">
                    <div
                        v-if="sucursalErrors.general?.[0]"
                        role="alert"
                        class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800"
                    >
                        {{ sucursalErrors.general[0] }}
                    </div>
                    <div class="company-form-grid">
                        <div class="company-field">
                            <Label for="sucursal-codigo" class="company-label"
                                >Codigo</Label
                            >
                            <Input
                                id="sucursal-codigo"
                                v-model.number="sucursalForm.codigo"
                                type="number"
                                min="0"
                                class="company-input"
                                :disabled="
                                    editingSucursalId !== null &&
                                    Number(sucursalForm.codigo) === 0
                                "
                                required
                            />
                            <InputError :message="sucursalErrors.codigo?.[0]" />
                        </div>
                        <div class="company-field">
                            <Label for="sucursal-nombre" class="company-label"
                                >Nombre generado</Label
                            >
                            <Input
                                id="sucursal-nombre"
                                :model-value="generatedSucursalName"
                                class="company-input"
                                disabled
                            />
                            <p class="text-xs text-muted-foreground">
                                Casa Matriz conserva siempre el codigo 0.
                            </p>
                        </div>
                        <div class="company-field md:col-span-2">
                            <Label
                                for="sucursal-municipio"
                                class="company-label"
                                >Municipio fiscal</Label
                            >
                            <Input
                                id="sucursal-municipio"
                                v-model="sucursalForm.municipio"
                                class="company-input"
                                maxlength="100"
                                placeholder="Ej. LA PAZ"
                                required
                            />
                            <p class="text-xs text-muted-foreground">
                                Este valor se envia en el XML fiscal como
                                municipio de la sucursal.
                            </p>
                            <InputError
                                :message="sucursalErrors.municipio?.[0]"
                            />
                        </div>
                        <div class="company-field md:col-span-2">
                            <Label
                                for="sucursal-direccion"
                                class="company-label"
                                >Direccion</Label
                            >
                            <textarea
                                id="sucursal-direccion"
                                v-model="sucursalForm.direccion"
                                class="company-textarea"
                                maxlength="255"
                                placeholder="Direccion de la sucursal"
                            />
                            <InputError
                                :message="sucursalErrors.direccion?.[0]"
                            />
                        </div>
                        <div class="company-field">
                            <Label for="sucursal-telefono" class="company-label"
                                >Telefono</Label
                            >
                            <Input
                                id="sucursal-telefono"
                                v-model="sucursalForm.telefono"
                                class="company-input"
                                maxlength="30"
                                placeholder="Telefono de contacto"
                            />
                            <InputError
                                :message="sucursalErrors.telefono?.[0]"
                            />
                        </div>
                        <label class="company-switch self-end">
                            <input
                                v-model="sucursalForm.estado"
                                type="checkbox"
                                class="size-4 accent-emerald-700"
                            />
                            <span
                                ><span class="block text-sm font-semibold"
                                    >Sucursal activa</span
                                ><span
                                    class="block text-xs text-muted-foreground"
                                    >Disponible para operaciones.</span
                                ></span
                            >
                        </label>
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="sucursalDialogOpen = false"
                            >Cancelar</Button
                        >
                        <Button
                            type="submit"
                            class="company-action-primary"
                            :disabled="savingSucursal"
                        >
                            <RefreshCw
                                v-if="savingSucursal"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                savingSucursal
                                    ? 'Guardando...'
                                    : editingSucursalId
                                      ? 'Actualizar sucursal'
                                      : 'Crear sucursal'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="puntoDialogOpen">
            <DialogContent class="max-w-2xl overflow-hidden p-0">
                <DialogHeader
                    class="company-hero p-6 pr-12 text-left text-white"
                >
                    <DialogTitle class="text-xl text-white">{{
                        editingPuntoId
                            ? 'Editar punto de venta'
                            : 'Nuevo punto de venta'
                    }}</DialogTitle>
                    <DialogDescription class="text-emerald-50/80"
                        >Configura la caja y su formato de impresion sin alterar
                        los codigos existentes.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-5 p-6" @submit.prevent="savePunto">
                    <div
                        v-if="puntoErrors.general?.[0]"
                        role="alert"
                        class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800"
                    >
                        {{ puntoErrors.general[0] }}
                    </div>
                    <div class="company-form-grid">
                        <div class="company-field md:col-span-2">
                            <Label for="punto-sucursal" class="company-label"
                                >Sucursal</Label
                            >
                            <select
                                id="punto-sucursal"
                                v-model="puntoForm.sucursal_id"
                                class="company-select"
                                required
                            >
                                <option value="" disabled>
                                    Selecciona una sucursal
                                </option>
                                <option
                                    v-for="sucursal in sucursales"
                                    :key="sucursal.id"
                                    :value="sucursal.id"
                                >
                                    {{ sucursal.codigo }} -
                                    {{ sucursal.nombre }}
                                </option>
                            </select>
                            <InputError
                                :message="puntoErrors.sucursal_id?.[0]"
                            />
                        </div>
                        <div class="company-field">
                            <Label for="punto-codigo" class="company-label"
                                >Codigo</Label
                            >
                            <Input
                                id="punto-codigo"
                                v-model.number="puntoForm.codigo"
                                type="number"
                                min="0"
                                class="company-input"
                                required
                            />
                            <InputError :message="puntoErrors.codigo?.[0]" />
                        </div>
                        <div class="company-field">
                            <Label for="punto-nombre" class="company-label"
                                >Nombre</Label
                            >
                            <Input
                                id="punto-nombre"
                                v-model="puntoForm.nombre"
                                class="company-input"
                                maxlength="150"
                                placeholder="Nombre comercial del punto"
                                required
                            />
                            <InputError :message="puntoErrors.nombre?.[0]" />
                        </div>
                        <div class="company-field md:col-span-2">
                            <Label for="punto-descripcion" class="company-label"
                                >Descripcion</Label
                            >
                            <textarea
                                id="punto-descripcion"
                                v-model="puntoForm.descripcion"
                                class="company-textarea"
                                maxlength="255"
                                placeholder="Detalle operativo del punto de venta"
                            />
                            <InputError
                                :message="puntoErrors.descripcion?.[0]"
                            />
                        </div>
                        <div class="company-field">
                            <Label for="punto-impresion" class="company-label"
                                >Tipo de impresion</Label
                            >
                            <select
                                id="punto-impresion"
                                v-model="puntoForm.tipo_impresion"
                                class="company-select"
                                required
                            >
                                <option value="ticket">Rollo</option>
                                <option value="media_carta">Media carta</option>
                                <option value="carta">Carta</option>
                            </select>
                            <InputError
                                :message="puntoErrors.tipo_impresion?.[0]"
                            />
                        </div>
                        <label class="company-switch self-end">
                            <input
                                v-model="puntoForm.estado"
                                type="checkbox"
                                class="size-4 accent-emerald-700"
                            />
                            <span
                                ><span class="block text-sm font-semibold"
                                    >Punto activo</span
                                ><span
                                    class="block text-xs text-muted-foreground"
                                    >Habilitado para operaciones.</span
                                ></span
                            >
                        </label>
                    </div>
                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="puntoDialogOpen = false"
                            >Cancelar</Button
                        >
                        <Button
                            type="submit"
                            class="company-action-primary"
                            :disabled="savingPunto"
                        >
                            <RefreshCw
                                v-if="savingPunto"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                savingPunto
                                    ? 'Guardando...'
                                    : editingPuntoId
                                      ? 'Actualizar punto'
                                      : 'Crear punto'
                            }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="confirmDialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <span
                        class="mb-2 flex size-11 items-center justify-center rounded-full bg-amber-100 text-amber-800"
                    >
                        <Power class="size-5" aria-hidden="true" />
                    </span>
                    <DialogTitle>{{ confirmAction?.title }}</DialogTitle>
                    <DialogDescription>{{
                        confirmAction?.description
                    }}</DialogDescription>
                </DialogHeader>
                <div class="flex justify-end gap-3 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="confirming"
                        @click="confirmDialogOpen = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="button"
                        class="company-action-primary"
                        :disabled="confirming"
                        @click="executeConfirmation"
                    >
                        <RefreshCw
                            v-if="confirming"
                            class="animate-spin"
                            aria-hidden="true"
                        />
                        {{
                            confirming
                                ? 'Procesando...'
                                : confirmAction?.confirmLabel
                        }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

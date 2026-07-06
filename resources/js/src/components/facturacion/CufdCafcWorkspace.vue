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
import { useCafcStore } from '@/src/stores/facturacion/cafcStore';
import { useCufdStore } from '@/src/stores/facturacion/cufdStore';
import {
    AlertTriangle,
    Building2,
    CalendarClock,
    Check,
    CheckCircle2,
    ClipboardCheck,
    Eye,
    FileKey2,
    Filter,
    Landmark,
    LoaderCircle,
    MoreVertical,
    Plus,
    RefreshCcw,
    ShieldCheck,
    Store,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type DataItem = Record<string, any>;
type TabKey = 'cufd' | 'cafc';

const props = withDefaults(
    defineProps<{
        initialTab?: TabKey;
    }>(),
    {
        initialTab: 'cufd',
    },
);

const cufdStore = useCufdStore();
const cafcStore = useCafcStore();

const activeTab = ref<TabKey>(props.initialTab);
const cufdDialogOpen = ref(false);
const cafcDialogOpen = ref(false);
const selectedCufd = ref<DataItem | null>(null);
const filters = ref({
    sucursal_id: '',
    punto_venta_id: '',
    ambiente_facturacion: '',
    estado: '',
});

const canManageCafc = computed(() =>
    Boolean(cafcStore.state.meta.puede_gestionar ?? false),
);
const cufdItems = computed<DataItem[]>(() => cufdStore.state.items as DataItem[]);
const cafcItems = computed<DataItem[]>(() => cafcStore.state.items as DataItem[]);
const sucursales = computed<DataItem[]>(
    () =>
        ((cufdStore.state.meta.sucursales as DataItem[] | undefined) ??
            (cafcStore.state.meta.sucursales as DataItem[] | undefined) ??
            []),
);
const puntosVenta = computed<DataItem[]>(
    () =>
        ((cufdStore.state.meta.puntos_venta as DataItem[] | undefined) ??
            (cafcStore.state.meta.puntos_venta as DataItem[] | undefined) ??
            []),
);
const ambientes = computed<DataItem[]>(() => {
    const values =
        ((cufdStore.state.meta.ambientes as DataItem[] | undefined) ??
            (cafcStore.state.meta.ambientes as DataItem[] | undefined) ??
            []);

    return values.map((ambiente) => ({
        ...ambiente,
        label:
            String(ambiente.value) === 'produccion'
                ? 'Producción'
                : String(ambiente.label ?? ambiente.value),
    }));
});
const siatProfile = computed<DataItem>(
    () =>
        ((cufdStore.state.meta.siat as DataItem | undefined) ??
            (cafcStore.state.meta.siat as DataItem | undefined) ??
            {}),
);
const puntosVentaFiltro = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            !filters.value.sucursal_id ||
            String(punto.sucursal_id) === filters.value.sucursal_id,
    ),
);
const puntosVentaCufdForm = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            String(punto.sucursal_id ?? '') ===
            String(cufdStore.state.form.sucursal_id ?? ''),
    ),
);
const puntosVentaCafcForm = computed(() =>
    puntosVenta.value.filter(
        (punto) =>
            String(punto.sucursal_id ?? '') ===
            String(cafcStore.state.form.sucursal_id ?? ''),
    ),
);

const now = () => new Date();
const parseDate = (value: unknown): Date | null => {
    if (!value) return null;
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
};
const formatDateTime = (value: unknown) => {
    const date = parseDate(value);
    if (!date) return 'No disponible';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};
const formatDate = (value: unknown) => {
    const date = parseDate(value);
    if (!date) return 'No disponible';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
};
const hoursUntil = (value: unknown) => {
    const date = parseDate(value);
    if (!date) return null;

    return Math.ceil((date.getTime() - now().getTime()) / 36e5);
};
const truncarCodigo = (value: unknown, start = 10, end = 4) => {
    const code = String(value ?? '');
    if (!code) return '-';
    if (code.length <= start + end + 3) return code;

    return `${code.slice(0, start)}...${code.slice(-end)}`;
};
const ambienteLabel = (value?: string | null) =>
    ambientes.value.find((ambiente) => String(ambiente.value ?? '') === String(value ?? ''))
        ?.label ??     value ??
    '-';

const cufdEstado = (item: DataItem) => {
    const expiry = parseDate(item.fecha_vigencia);
    const remainingHours = hoursUntil(item.fecha_vigencia);

    if (!item.codigo || !item.codigo_control) return 'observado';
    if (expiry && expiry < now()) return 'vencido';
    if (!item.estado) return 'inactivo';
    if (expiry && remainingHours !== null && remainingHours <= 24) {
        return 'por_vencer';
    }

    return 'vigente';
};
const cufdEstadoLabel = (item: DataItem) =>
    ({
        vigente: 'Vigente',
        por_vencer: 'Por vencer',
        vencido: 'Vencido',
        inactivo: 'Inactivo',
        observado: 'Observado',
    })[cufdEstado(item)] ?? 'Sin estado';
const cufdEstadoClass = (item: DataItem) =>
    ({
        vigente: 'border-[#bfe7cc] bg-[#EAF7EF] text-[#0f6d38]',
        por_vencer: 'border-amber-200 bg-amber-50 text-amber-700',
        vencido: 'border-red-200 bg-red-50 text-red-700',
        inactivo: 'border-[#dfe7e2] bg-[#F5F8F6] text-[#536158]',
        observado: 'border-amber-200 bg-amber-50 text-amber-800',
    })[cufdEstado(item)] ?? 'border-[#dfe7e2] bg-[#F5F8F6] text-[#536158]';
const cafcEstadoClass = (item: DataItem) =>
    ({
        activo: 'border-[#bfe7cc] bg-[#EAF7EF] text-[#0f6d38]',
        por_vencer: 'border-amber-200 bg-amber-50 text-amber-700',
        por_agotarse: 'border-amber-200 bg-amber-50 text-amber-700',
        agotado: 'border-red-200 bg-red-50 text-red-700',
        vencido: 'border-red-200 bg-red-50 text-red-700',
        inactivo: 'border-[#dfe7e2] bg-[#F5F8F6] text-[#536158]',
        programado: 'border-[#bfe7cc] bg-[#F5F8F6] text-[#168447]',
    })[String(item.resumen?.estado_operativo ?? 'activo')] ??
    'border-[#dfe7e2] bg-[#F5F8F6] text-[#536158]';

const cufdVigente = computed(
    () =>
        cufdItems.value.find((item) => cufdEstado(item) === 'vigente') ??
        cufdItems.value.find((item) => cufdEstado(item) === 'por_vencer') ?? null,
);
const ultimaSolicitud = computed(() =>
    [...cufdItems.value].sort(
        (a, b) =>
            (parseDate(b.created_at)?.getTime() ?? 0) -
            (parseDate(a.created_at)?.getTime() ?? 0),
    )[0] ?? null,
);
const cufdVigentes = computed(
    () =>
        cufdItems.value.filter((item) =>
            ['vigente', 'por_vencer'].includes(cufdEstado(item)),
        ).length,
);
const cufdPorVencer = computed(
    () => cufdItems.value.filter((item) => cufdEstado(item) === 'por_vencer').length,
);
const cafcActivos = computed(
    () => cafcItems.value.filter((item) => item.resumen?.vigente_hoy).length,
);
const cafcPorAgotarse = computed(
    () =>
        cafcItems.value.filter(
            (item) => String(item.resumen?.estado_operativo ?? '') === 'por_agotarse',
        ).length,
);
const selectedCufdStatus = computed(() =>
    selectedCufd.value ? cufdEstado(selectedCufd.value) : 'sin_datos',
);
const cufdRecommendation = computed(() => {
    if (!selectedCufd.value) {
        return 'Selecciona un CUFD del historial para revisar su estado operativo.';
    }

    return (
        {
            vigente:
                'El CUFD está vigente. Monitorea su vencimiento y evita solicitar otro mientras no sea necesario.',
            por_vencer:
                'Solicita un nuevo CUFD antes del vencimiento para evitar interrupciones en la facturación.',
            vencido:
                'Solicita un nuevo CUFD para este contexto antes de emitir nuevas facturas.',
            inactivo:
                'Revisa el historial y confirma cuál es el CUFD vigente para este punto de venta.',
            observado:
                'Revisa la respuesta de SIAT y corrige el contexto antes de solicitar nuevamente.',
        }[selectedCufdStatus.value] ??
        'Revisa el historial antes de ejecutar una nueva solicitud.'
    );
});
const cufdProgress = computed(() => {
    if (!selectedCufd.value) return 0;

    const created = parseDate(selectedCufd.value.created_at);
    const expiry = parseDate(selectedCufd.value.fecha_vigencia);

    if (!created || !expiry) return 100;

    const total = expiry.getTime() - created.getTime();
    const elapsed = now().getTime() - created.getTime();

    if (total <= 0) return 100;

    return Math.min(Math.max((elapsed / total) * 100, 0), 100);
});
const healthRemaining = computed(() => {
    if (!cufdVigente.value) return 'No disponible';
    const hours = hoursUntil(cufdVigente.value.fecha_vigencia);
    if (hours === null) return 'Sin vigencia';
    if (hours <= 0) return 'Vencido';
    if (hours < 24) return `${hours} ${hours === 1 ? 'hora' : 'horas'}`;

    const days = Math.ceil(hours / 24);
    return `${days} ${days === 1 ? 'día' : 'días'}`;
});
const uniquePuntosConCufd = computed(
    () => new Set(cufdItems.value.map((item) => String(item.punto_venta_id))).size,
);
const totalPuntosVenta = computed(() => puntosVenta.value.length);
const loading = computed(() => cufdStore.state.loading || cafcStore.state.loading);
const generalError = computed(
    () => cufdStore.state.generalError || cafcStore.state.generalError,
);
const successMessage = computed(
    () => cufdStore.state.successMessage || cafcStore.state.successMessage,
);

const applyFilters = async () => {
    cufdStore.state.filters = { ...filters.value };
    cafcStore.state.filters = { ...filters.value };
    await Promise.all([cufdStore.load(), cafcStore.load()]);
};
const clearFilters = async () => {
    filters.value = {
        sucursal_id: '',
        punto_venta_id: '',
        ambiente_facturacion: String(siatProfile.value.environment?.key ?? ''),
        estado: '',
    };
    await applyFilters();
};
const selectCufd = (item: DataItem) => {
    selectedCufd.value = item;
};
const prepareCufdFormContext = () => {
    if (!cufdStore.state.form.sucursal_id && sucursales.value[0]) {
        cufdStore.state.form.sucursal_id = String(sucursales.value[0].id ?? '');
    }

    if (
        !puntosVentaCufdForm.value.some(
            (punto) =>
                String(punto.id ?? '') ===
                String(cufdStore.state.form.punto_venta_id ?? ''),
        )
    ) {
        cufdStore.state.form.punto_venta_id = String(
            puntosVentaCufdForm.value[0]?.id ?? '',
        );
    }
};
const openCufdDialog = () => {
    cufdStore.resetForm();
    prepareCufdFormContext();
    cufdDialogOpen.value = true;
};
const submitCufd = async () => {
    const ok = await cufdStore.save();
    if (ok) cufdDialogOpen.value = false;
};
const openCafcDialog = () => {
    cafcStore.resetForm();
    const primeraSucursal = sucursales.value[0];
    if (primeraSucursal) {
        cafcStore.state.form.sucursal_id = String(primeraSucursal.id ?? '');
        cafcStore.state.form.punto_venta_id = String(
            puntosVentaCafcForm.value[0]?.id ?? '',
        );
    }
    cafcStore.state.form.ambiente_facturacion = String(
        siatProfile.value.environment?.key ?? 'piloto',
    );
    cafcDialogOpen.value = true;
};
const openEditCafcDialog = (item: DataItem) => {
    cafcStore.startEdit(item);
    cafcDialogOpen.value = true;
};
const submitCafc = async () => {
    const ok = await cafcStore.save();
    if (ok) cafcDialogOpen.value = false;
};
const toggleCafcEstado = async (item: DataItem) => {
    await cafcStore.updateEstado(item, !Boolean(item.estado));
};

watch(
    () => filters.value.sucursal_id,
    () => {
        if (
            filters.value.punto_venta_id &&
            !puntosVentaFiltro.value.some(
                (punto) => String(punto.id) === filters.value.punto_venta_id,
            )
        ) {
            filters.value.punto_venta_id = '';
        }
    },
);
watch(
    () => cufdStore.state.form.sucursal_id,
    () => {
        if (!isGlobalManager.value) return;
        if (
            !puntosVentaCufdForm.value.some(
                (punto) =>
                    String(punto.id ?? '') ===
                    String(cufdStore.state.form.punto_venta_id ?? ''),
            )
        ) {
            cufdStore.state.form.punto_venta_id = String(
                puntosVentaCufdForm.value[0]?.id ?? '',
            );
        }
    },
);
watch(
    () => cafcStore.state.form.sucursal_id,
    () => {
        if (
            !puntosVentaCafcForm.value.some(
                (punto) =>
                    String(punto.id ?? '') ===
                    String(cafcStore.state.form.punto_venta_id ?? ''),
            )
        ) {
            cafcStore.state.form.punto_venta_id = String(
                puntosVentaCafcForm.value[0]?.id ?? '',
            );
        }
    },
);
watch(cufdItems, (items) => {
    if (!selectedCufd.value || !items.some((item) => item.id === selectedCufd.value?.id)) {
        selectedCufd.value = cufdVigente.value ?? items[0] ?? null;
    }
});

onMounted(async () => {
    await Promise.all([cufdStore.load(), cafcStore.load()]);
    filters.value.ambiente_facturacion = String(
        cufdStore.state.filters.ambiente_facturacion ||
            cafcStore.state.filters.ambiente_facturacion ||
            siatProfile.value.environment?.key ||
            '',
    );
    selectedCufd.value = cufdVigente.value ?? cufdItems.value[0] ?? null;
});
</script>

<template>
    <ModulePageLayout
        title="CUFD y CAFC"
        description="Controla los códigos de facturación y contingencia por punto de venta."
        compact
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'CUFD y CAFC', href: '/facturacion/cufd' },
        ]"
    >
        <template #actions>
            <div class="flex flex-wrap gap-3">
                <Button class="company-action-primary gap-2" @click="openCufdDialog">
                    <FileKey2 class="size-4" />
                    Solicitar nuevo CUFD
                </Button>
                <Button
                    v-if="canManageCafc"
                    class="company-action-primary gap-2"
                    @click="openCafcDialog"
                >
                    <Plus class="size-4" />
                    Registrar CAFC
                </Button>
            </div>
        </template>

        <div class="space-y-4">
            <div
                v-if="successMessage"
                class="rounded-lg border border-[#bfe7cc] bg-[#EAF7EF] px-4 py-3 text-sm font-medium text-[#0f6d38]"
                role="status"
                aria-live="polite"
            >
                {{ successMessage }}
            </div>
            <div
                v-if="generalError"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
                role="alert"
            >
                {{ generalError }}
            </div>

            <section class="rounded-lg border border-[#dfe7e2] bg-white">
                <div class="grid gap-0 lg:grid-cols-5">
                    <div class="flex items-center gap-4 border-[#dfe7e2] p-4 lg:border-r">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#168447] text-white"
                        >
                            <Check class="size-6" />
                        </div>
                        <div>
                            <p class="font-semibold text-[#101713]">
                                {{ cufdVigente ? 'CUFD vigente' : 'Sin CUFD vigente' }}
                            </p>
                            <p class="text-sm text-[#536158]">
                                {{ cufdVigente ? 'Todo en orden' : 'Requiere atención' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 border-[#dfe7e2] p-4 lg:border-r">
                        <Building2 class="size-8 text-[#168447]" />
                        <div>
                            <p class="text-xs text-[#536158]">Sucursal</p>
                            <p class="font-medium text-[#101713]">
                                {{ cufdVigente?.sucursal?.nombre || 'No definida' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 border-[#dfe7e2] p-4 lg:border-r">
                        <Store class="size-8 text-[#168447]" />
                        <div>
                            <p class="text-xs text-[#536158]">Punto de venta</p>
                            <p class="font-medium text-[#101713]">
                                {{ cufdVigente?.punto_venta?.nombre || 'No definido' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 border-[#dfe7e2] p-4 lg:border-r">
                        <CalendarClock
                            class="size-8"
                            :class="cufdPorVencer > 0 ? 'text-amber-600' : 'text-[#168447]'"
                        />
                        <div>
                            <p class="text-xs text-[#536158]">Vence en</p>
                            <p class="font-semibold text-[#101713]">{{ healthRemaining }}</p>
                            <p class="text-xs text-[#536158]">
                                {{ formatDateTime(cufdVigente?.fecha_vigencia) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 p-4">
                        <ClipboardCheck class="size-8 text-[#168447]" />
                        <div>
                            <p class="text-xs text-[#536158]">Última solicitud</p>
                            <p class="font-semibold text-[#101713]">
                                {{ formatDateTime(ultimaSolicitud?.created_at) }}
                            </p>
                            <p class="text-xs text-[#536158]">
                                Por {{ ultimaSolicitud?.usuario?.name || 'No registrado' }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-[#dfe7e2] bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-[#EAF7EF] p-3 text-[#168447]">
                            <ShieldCheck class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm text-[#536158]">CUFD vigentes</p>
                            <p class="text-2xl font-bold text-[#101713]">{{ cufdVigentes }}</p>
                            <p class="text-xs text-[#536158]">
                                De {{ totalPuntosVenta || uniquePuntosConCufd }} puntos de venta
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-[#dfe7e2] bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-amber-50 p-3 text-amber-600">
                            <CalendarClock class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm text-[#536158]">Por vencer</p>
                            <p class="text-2xl font-bold text-[#101713]">{{ cufdPorVencer }}</p>
                            <p class="text-xs text-[#536158]">En próximas 24 horas</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-[#dfe7e2] bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-[#EAF7EF] p-3 text-[#168447]">
                            <FileKey2 class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm text-[#536158]">CAFC activos</p>
                            <p class="text-2xl font-bold text-[#101713]">{{ cafcActivos }}</p>
                            <p class="text-xs text-[#536158]">Disponibles para contingencia</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-[#dfe7e2] bg-white p-4">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-amber-50 p-3 text-amber-600">
                            <AlertTriangle class="size-6" />
                        </div>
                        <div>
                            <p class="text-sm text-[#536158]">Rangos por agotarse</p>
                            <p class="text-2xl font-bold text-[#101713]">{{ cafcPorAgotarse }}</p>
                            <p class="text-xs text-[#536158]">Menos del umbral disponible</p>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_22rem]">
                <main class="space-y-4">
                    <section class="rounded-lg border border-[#dfe7e2] bg-white">
                        <div class="border-b border-[#dfe7e2] px-4">
                            <div class="flex gap-5">
                                <button
                                    type="button"
                                    class="border-b-2 px-1 py-4 text-sm font-semibold transition"
                                    :class="
                                        activeTab === 'cufd'
                                            ? 'border-[#168447] text-[#168447]'
                                            : 'border-transparent text-[#536158] hover:text-[#101713]'
                                    "
                                    @click="activeTab = 'cufd'"
                                >
                                    CUFD
                                </button>
                                <button
                                    type="button"
                                    class="border-b-2 px-1 py-4 text-sm font-semibold transition"
                                    :class="
                                        activeTab === 'cafc'
                                            ? 'border-[#168447] text-[#168447]'
                                            : 'border-transparent text-[#536158] hover:text-[#101713]'
                                    "
                                    @click="activeTab = 'cafc'"
                                >
                                    CAFC
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 p-4 md:grid-cols-2 2xl:grid-cols-5">
                            <div class="company-field">
                                <Label class="company-label">Sucursal</Label>
                                <select v-model="filters.sucursal_id" class="company-select">
                                    <option value="">Todas</option>
                                    <option
                                        v-for="sucursal in sucursales"
                                        :key="String(sucursal.id)"
                                        :value="String(sucursal.id)"
                                    >
                                        {{ sucursal.nombre }}
                                    </option>
                                </select>
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Punto de venta</Label>
                                <select v-model="filters.punto_venta_id" class="company-select">
                                    <option value="">Todos</option>
                                    <option
                                        v-for="punto in puntosVentaFiltro"
                                        :key="String(punto.id)"
                                        :value="String(punto.id)"
                                    >
                                        {{ punto.nombre }}
                                    </option>
                                </select>
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Ambiente</Label>
                                <select v-model="filters.ambiente_facturacion" class="company-select">
                                    <option value="">Todos</option>
                                    <option
                                        v-for="ambiente in ambientes"
                                        :key="String(ambiente.value)"
                                        :value="String(ambiente.value)"
                                    >
                                        {{ ambiente.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Estado</Label>
                                <select v-model="filters.estado" class="company-select">
                                    <option value="">Todos</option>
                                    <option value="true">Activos</option>
                                    <option value="false">Inactivos</option>
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <Button class="company-action-primary flex-1 gap-2" @click="applyFilters">
                                    <Filter class="size-4" />
                                    Filtrar
                                </Button>
                                <Button
                                    variant="outline"
                                    class="company-action-secondary gap-2"
                                    @click="clearFilters"
                                >
                                    <RefreshCcw class="size-4" />
                                    Limpiar
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'cufd'"
                        class="overflow-hidden rounded-lg border border-[#dfe7e2] bg-white"
                    >
                        <div
                            v-if="loading"
                            class="flex items-center justify-center gap-2 px-4 py-10 text-sm text-[#536158]"
                        >
                            <LoaderCircle class="size-5 animate-spin" />
                            Cargando historial de CUFD
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full min-w-[860px] text-sm">
                                <thead class="bg-[#F5F8F6] text-left text-xs text-[#536158]">
                                    <tr>
                                        <th class="px-4 py-3">Código CUFD</th>
                                        <th class="px-4 py-3">Sucursal</th>
                                        <th class="px-4 py-3">Punto de venta</th>
                                        <th class="px-4 py-3">Código control</th>
                                        <th class="px-4 py-3">Vigencia</th>
                                        <th class="px-4 py-3">Ambiente</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in cufdItems"
                                        :key="String(item.id)"
                                        class="border-t border-[#dfe7e2]"
                                    >
                                        <td class="px-4 py-3 font-medium text-[#101713]">
                                            {{ truncarCodigo(item.codigo) }}
                                        </td>
                                        <td class="px-4 py-3">{{ item.sucursal?.nombre || '-' }}</td>
                                        <td class="px-4 py-3">{{ item.punto_venta?.nombre || '-' }}</td>
                                        <td class="px-4 py-3">
                                            {{ truncarCodigo(item.codigo_control, 8, 4) }}
                                        </td>
                                        <td class="px-4 py-3">{{ formatDateTime(item.fecha_vigencia) }}</td>
                                        <td class="px-4 py-3">
                                            {{ ambienteLabel(item.ambiente_facturacion) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                                                :class="cufdEstadoClass(item)"
                                            >
                                                {{ cufdEstadoLabel(item) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    class="company-action-secondary h-9 gap-2"
                                                    @click="selectCufd(item)"
                                                >
                                                    <Eye class="size-4" />
                                                    Ver
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    class="company-action-secondary size-9 p-0"
                                                    aria-label="Más acciones CUFD"
                                                    @click="selectCufd(item)"
                                                >
                                                    <MoreVertical class="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="cufdItems.length === 0">
                                        <td
                                            colspan="8"
                                            class="px-4 py-10 text-center text-[#536158]"
                                        >
                                            No hay CUFD registrados para los filtros seleccionados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-[#dfe7e2] px-4 py-3 text-sm text-[#536158]">
                            Mostrando {{ cufdItems.length }} códigos CUFD
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'cufd'"
                        class="overflow-hidden rounded-lg border border-[#dfe7e2] bg-white"
                    >
                        <div class="flex items-center justify-between border-b border-[#dfe7e2] px-4 py-3">
                            <h2 class="font-semibold text-[#101713]">CAFC disponibles</h2>
                            <button
                                type="button"
                                class="text-sm font-semibold text-[#168447] hover:text-[#0f6d38]"
                                @click="activeTab = 'cafc'"
                            >
                                Ver todos los CAFC
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead class="bg-[#F5F8F6] text-left text-xs text-[#536158]">
                                    <tr>
                                        <th class="px-4 py-3">Código CAFC</th>
                                        <th class="px-4 py-3">Rango</th>
                                        <th class="px-4 py-3">Facturas usadas</th>
                                        <th class="px-4 py-3">Facturas disponibles</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in cafcItems.slice(0, 4)"
                                        :key="String(item.id)"
                                        class="border-t border-[#dfe7e2]"
                                    >
                                        <td class="px-4 py-3 font-medium">{{ item.codigo }}</td>
                                        <td class="px-4 py-3">
                                            {{ item.numero_inicial || '-' }} - {{ item.numero_final || '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ item.resumen?.facturas_utilizadas ?? 0 }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ item.resumen?.disponibles_restantes ?? 'Sin rango' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                                                :class="cafcEstadoClass(item)"
                                            >
                                                {{
                                                    item.resumen?.estado_label ||
                                                    (item.estado ? 'Activo' : 'Inactivo')
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    class="company-action-secondary h-9 gap-2"
                                                    @click="
                                                        activeTab = 'cafc';
                                                        openEditCafcDialog(item);
                                                    "
                                                >
                                                    <Eye class="size-4" />
                                                    Ver
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="cafcItems.length === 0">
                                        <td
                                            colspan="6"
                                            class="px-4 py-8 text-center text-[#536158]"
                                        >
                                            No hay CAFC disponibles para los filtros seleccionados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'cafc'"
                        class="overflow-hidden rounded-lg border border-[#dfe7e2] bg-white"
                    >
                        <div
                            v-if="loading"
                            class="flex items-center justify-center gap-2 px-4 py-10 text-sm text-[#536158]"
                        >
                            <LoaderCircle class="size-5 animate-spin" />
                            Cargando códigos CAFC
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full min-w-[1020px] text-sm">
                                <thead class="bg-[#F5F8F6] text-left text-xs text-[#536158]">
                                    <tr>
                                        <th class="px-4 py-3">Código</th>
                                        <th class="px-4 py-3">Ambiente</th>
                                        <th class="px-4 py-3">Sucursal</th>
                                        <th class="px-4 py-3">Punto de venta</th>
                                        <th class="px-4 py-3">Rango</th>
                                        <th class="px-4 py-3">Uso</th>
                                        <th class="px-4 py-3">Vigencia</th>
                                        <th class="px-4 py-3">Estado</th>
                                        <th class="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in cafcItems"
                                        :key="String(item.id)"
                                        class="border-t border-[#dfe7e2]"
                                    >
                                        <td class="px-4 py-3 font-medium text-[#101713]">
                                            {{ item.codigo }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ ambienteLabel(item.ambiente_facturacion) }}
                                        </td>
                                        <td class="px-4 py-3">{{ item.sucursal?.nombre || '-' }}</td>
                                        <td class="px-4 py-3">{{ item.punto_venta?.nombre || '-' }}</td>
                                        <td class="px-4 py-3">
                                            <div>{{ item.numero_inicial || '-' }} - {{ item.numero_final || '-' }}</div>
                                            <div class="text-xs text-[#536158]">
                                                Sig.: {{ item.resumen?.siguiente_numero_sugerido || '-' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div>{{ item.resumen?.facturas_utilizadas || 0 }} usadas</div>
                                            <div class="text-xs text-[#536158]">
                                                Disponibles:
                                                {{ item.resumen?.disponibles_restantes ?? 'Sin rango' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div>{{ formatDate(item.fecha_inicio_vigencia) }}</div>
                                            <div class="text-xs text-[#536158]">
                                                {{ formatDate(item.fecha_fin_vigencia) }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                                                :class="cafcEstadoClass(item)"
                                            >
                                                {{
                                                    item.resumen?.estado_label ||
                                                    (item.estado ? 'Activo' : 'Inactivo')
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    v-if="canManageCafc"
                                                    variant="outline"
                                                    class="company-action-secondary"
                                                    @click="openEditCafcDialog(item)"
                                                >
                                                    Editar
                                                </Button>
                                                <Button
                                                    v-if="canManageCafc"
                                                    variant="outline"
                                                    class="company-action-secondary"
                                                    :disabled="
                                                        cafcStore.state.updatingEstadoId === Number(item.id)
                                                    "
                                                    @click="toggleCafcEstado(item)"
                                                >
                                                    {{ item.estado ? 'Desactivar' : 'Activar' }}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="cafcItems.length === 0">
                                        <td
                                            colspan="9"
                                            class="px-4 py-10 text-center text-[#536158]"
                                        >
                                            No hay CAFC registrados para los filtros seleccionados.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-[#dfe7e2] px-4 py-3 text-sm text-[#536158]">
                            Mostrando {{ cafcItems.length }} códigos CAFC
                        </div>
                    </section>
                </main>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-[#dfe7e2] bg-white p-4">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-semibold text-[#101713]">Detalle del CUFD</h2>
                                <p class="text-sm text-[#536158]">Estado operativo del registro seleccionado</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-md p-1 text-[#536158] hover:bg-[#F5F8F6]"
                                aria-label="Cerrar detalle"
                                @click="selectedCufd = null"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <div v-if="selectedCufd" class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-[#EAF7EF] p-3 text-[#168447]">
                                    <ShieldCheck class="size-6" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-[#101713]">
                                        {{ truncarCodigo(selectedCufd.codigo, 12, 6) }}
                                    </p>
                                    <p class="text-sm text-[#536158]">
                                        {{ ambienteLabel(selectedCufd.ambiente_facturacion) }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                                    :class="cufdEstadoClass(selectedCufd)"
                                >
                                    {{ cufdEstadoLabel(selectedCufd) }}
                                </span>
                            </div>

                            <dl class="grid gap-3 border-y border-[#dfe7e2] py-4 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-[#536158]">Código control</dt>
                                    <dd class="text-right font-medium text-[#101713]">
                                        {{ selectedCufd.codigo_control || '-' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-[#536158]">Sucursal</dt>
                                    <dd class="text-right font-medium text-[#101713]">
                                        {{ selectedCufd.sucursal?.nombre || '-' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-[#536158]">Punto de venta</dt>
                                    <dd class="text-right font-medium text-[#101713]">
                                        {{ selectedCufd.punto_venta?.nombre || '-' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-[#536158]">Dirección</dt>
                                    <dd class="text-right font-medium text-[#101713]">
                                        {{ selectedCufd.direccion || '-' }}
                                    </dd>
                                </div>
                            </dl>

                            <div>
                                <h3 class="mb-3 text-sm font-semibold text-[#101713]">
                                    Vigencia del CUFD
                                </h3>
                                <div class="h-2 overflow-hidden rounded-full bg-[#EAF7EF]">
                                    <div
                                        class="h-full rounded-full"
                                        :class="
                                            selectedCufdStatus === 'vencido'
                                                ? 'bg-red-600'
                                                : selectedCufdStatus === 'por_vencer'
                                                  ? 'bg-amber-500'
                                                  : 'bg-[#168447]'
                                        "
                                        :style="{ width: `${cufdProgress}%` }"
                                    />
                                </div>
                                <div class="mt-3 flex justify-between gap-3 text-xs text-[#536158]">
                                    <span>
                                        {{ formatDateTime(selectedCufd.created_at) }}<br />
                                        Inicio
                                    </span>
                                    <span class="text-right">
                                        {{ formatDateTime(selectedCufd.fecha_vigencia) }}<br />
                                        Fin
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                <div class="flex gap-2">
                                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                                    <p>{{ cufdRecommendation }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="rounded-lg bg-[#F5F8F6] p-4 text-sm text-[#536158]">
                            Selecciona un CUFD del historial para ver su contexto y vigencia.
                        </div>
                    </section>

                    <section class="rounded-lg border border-[#dfe7e2] bg-white p-4">
                        <div class="flex gap-3">
                            <div class="rounded-lg bg-[#EAF7EF] p-3 text-[#168447]">
                                <Landmark class="size-6" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-[#101713]">Información CAFC</h2>
                                <p class="mt-1 text-sm text-[#536158]">
                                    Los CAFC se usan únicamente en modo contingencia cuando los servicios
                                    SIAT no están disponibles. Registra rangos reales y revisa su consumo
                                    antes de operar manualmente.
                                </p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="cufdDialogOpen">
            <DialogContent class="max-w-3xl border-border/80 p-0">
                <div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader>
                            <DialogTitle class="text-left text-lg font-semibold text-white">
                                Solicitar nuevo CUFD
                            </DialogTitle>
                            <DialogDescription class="text-left text-emerald-50/80">
                                El sistema solicitará un CUFD a SIAT usando la sucursal y punto de venta operativos.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <form class="space-y-5 p-5 md:p-6" @submit.prevent="submitCufd">
                        <div
                            v-if="cufdStore.state.generalError"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                        >
                            {{ cufdStore.state.generalError }}
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Sucursal</Label>
                                <select v-model="cufdStore.state.form.sucursal_id" class="company-select">
                                    <option value="">Seleccione una sucursal</option>
                                    <option
                                        v-for="sucursal in sucursales"
                                        :key="String(sucursal.id)"
                                        :value="String(sucursal.id)"
                                    >
                                        {{ sucursal.nombre }}
                                    </option>
                                </select>
                                <InputError :message="cufdStore.state.errors.sucursal_id?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Punto de venta</Label>
                                <select v-model="cufdStore.state.form.punto_venta_id" class="company-select">
                                    <option value="">Seleccione un punto de venta</option>
                                    <option
                                        v-for="punto in puntosVentaCufdForm"
                                        :key="String(punto.id)"
                                        :value="String(punto.id)"
                                    >
                                        {{ punto.nombre }}
                                    </option>
                                </select>
                                <InputError :message="cufdStore.state.errors.punto_venta_id?.[0]" />
                            </div>
                        </div>
                        <div class="rounded-lg border border-[#dfe7e2] bg-[#F5F8F6] p-4 text-sm text-[#536158]">
                            Esta acción solicitará un nuevo CUFD al SIAT para el ambiente activo. Si SIAT devuelve
                            una observación o presenta una caída, el CUFD vigente actual no será reemplazado.
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" class="company-action-secondary" @click="cufdDialogOpen = false">
                                Cancelar
                            </Button>
                            <Button type="submit" class="company-action-primary" :disabled="cufdStore.state.saving">
                                {{ cufdStore.state.saving ? 'Solicitando...' : 'Solicitar CUFD' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="cafcDialogOpen">
            <DialogContent class="max-w-3xl border-border/80 p-0">
                <div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader>
                            <DialogTitle class="text-left text-lg font-semibold text-white">
                                {{ cafcStore.state.form.id ? 'Editar CAFC' : 'Registrar CAFC' }}
                            </DialogTitle>
                            <DialogDescription class="text-left text-emerald-50/80">
                                Este código se usará para facturas manuales de contingencia en el contexto seleccionado.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <form class="space-y-5 p-5 md:p-6" @submit.prevent="submitCafc">
                        <div
                            v-if="cafcStore.state.generalError"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                        >
                            {{ cafcStore.state.generalError }}
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Código CAFC</Label>
                                <Input v-model="cafcStore.state.form.codigo" class="company-input" />
                                <InputError :message="cafcStore.state.errors.codigo?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">PIN</Label>
                                <Input v-model="cafcStore.state.form.pin" class="company-input" />
                                <InputError :message="cafcStore.state.errors.pin?.[0]" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Descripción</Label>
                                <Input v-model="cafcStore.state.form.descripcion" class="company-input" />
                                <InputError :message="cafcStore.state.errors.descripcion?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Ambiente</Label>
                                <select v-model="cafcStore.state.form.ambiente_facturacion" class="company-select">
                                    <option value="">Seleccione un ambiente</option>
                                    <option
                                        v-for="ambiente in ambientes"
                                        :key="String(ambiente.value)"
                                        :value="String(ambiente.value)"
                                    >
                                        {{ ambiente.label }}
                                    </option>
                                </select>
                                <InputError :message="cafcStore.state.errors.ambiente_facturacion?.[0]" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Sucursal</Label>
                                <select v-model="cafcStore.state.form.sucursal_id" class="company-select">
                                    <option value="">Seleccione una sucursal</option>
                                    <option
                                        v-for="sucursal in sucursales"
                                        :key="String(sucursal.id)"
                                        :value="String(sucursal.id)"
                                    >
                                        {{ sucursal.nombre }}
                                    </option>
                                </select>
                                <InputError :message="cafcStore.state.errors.sucursal_id?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Punto de venta</Label>
                                <select v-model="cafcStore.state.form.punto_venta_id" class="company-select">
                                    <option value="">Seleccione un punto de venta</option>
                                    <option
                                        v-for="punto in puntosVentaCafcForm"
                                        :key="String(punto.id)"
                                        :value="String(punto.id)"
                                    >
                                        {{ punto.nombre }}
                                    </option>
                                </select>
                                <InputError :message="cafcStore.state.errors.punto_venta_id?.[0]" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Fecha inicio vigencia</Label>
                                <Input v-model="cafcStore.state.form.fecha_inicio_vigencia" type="datetime-local" class="company-input" />
                                <InputError :message="cafcStore.state.errors.fecha_inicio_vigencia?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Fecha fin vigencia</Label>
                                <Input v-model="cafcStore.state.form.fecha_fin_vigencia" type="datetime-local" class="company-input" />
                                <InputError :message="cafcStore.state.errors.fecha_fin_vigencia?.[0]" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Número inicial autorizado</Label>
                                <Input v-model="cafcStore.state.form.numero_inicial" type="number" min="1" step="1" class="company-input" />
                                <InputError :message="cafcStore.state.errors.numero_inicial?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Número final autorizado</Label>
                                <Input v-model="cafcStore.state.form.numero_final" type="number" min="1" step="1" class="company-input" />
                                <InputError :message="cafcStore.state.errors.numero_final?.[0]" />
                            </div>
                        </div>

                        <div class="company-field">
                            <Label class="company-label">Observación</Label>
                            <textarea v-model="cafcStore.state.form.observacion" class="company-textarea" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" class="company-action-secondary" @click="cafcDialogOpen = false">
                                Cancelar
                            </Button>
                            <Button type="submit" class="company-action-primary" :disabled="cafcStore.state.saving">
                                {{
                                    cafcStore.state.saving
                                        ? 'Guardando...'
                                        : cafcStore.state.form.id
                                          ? 'Guardar cambios'
                                          : 'Registrar CAFC'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

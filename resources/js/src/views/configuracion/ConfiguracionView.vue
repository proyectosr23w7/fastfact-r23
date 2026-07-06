<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { useConfiguracionStore } from '@/src/stores/configuracionStore';
import { router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    CheckCircle2,
    ChevronRight,
    CloudCog,
    FileCheck2,
    FileKey2,
    FileSignature,
    Gauge,
    Info,
    KeyRound,
    LoaderCircle,
    PackageCheck,
    ReceiptText,
    RotateCcw,
    Save,
    Settings2,
    ShieldCheck,
    UploadCloud,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from 'vue';

type SectionKey = 'general' | 'productos' | 'facturacion' | 'firma';
type FormValue = string | number | boolean | File | null | undefined;

const store = useConfiguracionStore();
const activeSection = ref<SectionKey>('general');
const baseline = ref<Record<string, unknown>>({});
const discardDialogOpen = ref(false);
const fileInputKey = ref(0);
const tokenEditors = reactive({
    legacy: false,
    piloto: false,
    produccion: false,
});
const codigosSistemaPorTipo: Record<number, string> = {
    1: '7C70CB5C65357C323C8B20F',
    2: '7C7391F0B6F85E18C9EB20F',
};
const credentials = [
    {
        editor: 'piloto',
        field: 'token_siat_piloto',
        configured: 'token_siat_piloto_configurado',
        expiration: 'token_siat_piloto_vigencia',
        label: 'Token piloto',
        description: 'Credencial para el ambiente de pruebas.',
    },
    {
        editor: 'produccion',
        field: 'token_siat_produccion',
        configured: 'token_siat_produccion_configurado',
        expiration: 'token_siat_produccion_vigencia',
        label: 'Token producción',
        description: 'Credencial utilizada para emitir en producción.',
    },
    {
        editor: 'legacy',
        field: 'token_siat',
        configured: 'token_siat_configurado',
        expiration: null,
        label: 'Token general (compatibilidad)',
        description:
            'Credencial heredada usada como respaldo cuando el ambiente no tiene un token específico.',
    },
] as const;

const enumOptions = {
    tipoFacturacion: [
        { label: 'No emite factura', value: 0 },
        { label: 'Factura computarizada en línea', value: 2 },
        { label: 'Factura electrónica en línea', value: 1 },
    ],
    ambiente: [
        { label: 'Piloto', value: 'piloto' },
        { label: 'Producción', value: 'produccion' },
    ],
};

const sections = [
    { key: 'general' as const, label: 'General', icon: Settings2 },
    { key: 'productos' as const, label: 'Productos', icon: Boxes },
    {
        key: 'facturacion' as const,
        label: 'Facturación SIAT',
        icon: ReceiptText,
    },
    { key: 'firma' as const, label: 'Firma digital', icon: FileSignature },
];

const editableFields = [
    'facturacion_habilitada',
    'tipo_facturacion',
    'ambiente_facturacion',
    'token_siat',
    'codigo_sistema',
    'token_siat_piloto',
    'token_siat_piloto_vigencia',
    'token_siat_produccion',
    'token_siat_produccion_vigencia',
    'firma_digital_archivo',
    'firma_digital_password',
    'multiples_precios',
    'precios_por_cantidad',
    'productos_categorias_habilitadas',
    'productos_marcas_habilitadas',
    'productos_busqueda_avanzada_habilitada',
    'productos_codigo_barras_habilitado',
    'estado',
] as const;

const fieldLabels: Record<(typeof editableFields)[number], string> = {
    facturacion_habilitada: 'Facturación habilitada',
    tipo_facturacion: 'Tipo de facturación',
    ambiente_facturacion: 'Ambiente SIAT',
    token_siat: 'Token SIAT heredado',
    codigo_sistema: 'Código de sistema',
    token_siat_piloto: 'Token piloto',
    token_siat_piloto_vigencia: 'Vigencia del token piloto',
    token_siat_produccion: 'Token producción',
    token_siat_produccion_vigencia: 'Vigencia del token producción',
    firma_digital_archivo: 'Archivo de firma digital',
    firma_digital_password: 'Contraseña de firma digital',
    multiples_precios: 'Múltiples precios',
    precios_por_cantidad: 'Precios por cantidad',
    productos_categorias_habilitadas: 'Categorías de productos',
    productos_marcas_habilitadas: 'Marcas de productos',
    productos_busqueda_avanzada_habilitada: 'Búsqueda avanzada de productos',
    productos_codigo_barras_habilitado: 'Código de barras de productos',
    estado: 'Configuración activa',
};

const fieldSections: Record<string, SectionKey> = {
    estado: 'general',
    multiples_precios: 'productos',
    precios_por_cantidad: 'productos',
    productos_categorias_habilitadas: 'productos',
    productos_marcas_habilitadas: 'productos',
    productos_busqueda_avanzada_habilitada: 'productos',
    productos_codigo_barras_habilitado: 'productos',
    facturacion_habilitada: 'facturacion',
    tipo_facturacion: 'facturacion',
    ambiente_facturacion: 'facturacion',
    codigo_sistema: 'facturacion',
    token_siat: 'facturacion',
    token_siat_piloto: 'facturacion',
    token_siat_piloto_vigencia: 'facturacion',
    token_siat_produccion: 'facturacion',
    token_siat_produccion_vigencia: 'facturacion',
    firma_digital_archivo: 'firma',
    firma_digital_password: 'firma',
};

const singletonItem = computed(() => store.state.items[0] ?? null);
const facturacionActiva = computed(() =>
    Boolean(store.state.form.facturacion_habilitada),
);
const tipoFacturacion = computed(() =>
    Number(store.state.form.tipo_facturacion ?? 0),
);
const requiereParametrosFacturacion = computed(
    () => facturacionActiva.value && tipoFacturacion.value !== 0,
);
const requiereFirmaDigital = computed(
    () => facturacionActiva.value && tipoFacturacion.value === 1,
);
const codigoSistemaAutomatico = computed(
    () => codigosSistemaPorTipo[tipoFacturacion.value] ?? '',
);
const codigoSistemaBloqueado = computed(() => Boolean(codigoSistemaAutomatico.value));
const ambienteProduccion = computed(
    () =>
        requiereParametrosFacturacion.value &&
        store.state.form.ambiente_facturacion === 'produccion',
);
const selectedFirmaFile = computed(() =>
    store.state.form.firma_digital_archivo instanceof File
        ? store.state.form.firma_digital_archivo
        : null,
);

const tipoFacturacionLabel = computed(
    () =>
        enumOptions.tipoFacturacion.find(
            (option) => Number(option.value) === tipoFacturacion.value,
        )?.label ?? 'No definido',
);
const ambienteLabel = computed(
    () =>
        enumOptions.ambiente.find(
            (option) => option.value === store.state.form.ambiente_facturacion,
        )?.label ?? 'No definido',
);
const updatedAtLabel = computed(() => {
    const value = singletonItem.value?.updated_at;
    if (!value) return 'Sin registrar';

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(String(value)));
});

const normalizeValue = (value: FormValue): unknown => {
    if (value instanceof File)
        return { name: value.name, size: value.size, type: value.type };
    return value ?? '';
};

const formSnapshot = () =>
    Object.fromEntries(
        editableFields.map((key) => [
            key,
            normalizeValue(store.state.form[key] as FormValue),
        ]),
    );

const changedFields = computed(() =>
    editableFields
        .filter(
            (key) =>
                JSON.stringify(
                    normalizeValue(store.state.form[key] as FormValue),
                ) !== JSON.stringify(baseline.value[key]),
        )
        .map((key) => ({
            key,
            label: fieldLabels[key],
            before: formatChangeValue(key, baseline.value[key]),
            after: formatChangeValue(
                key,
                normalizeValue(store.state.form[key] as FormValue),
            ),
        })),
);
const isDirty = computed(() => changedFields.value.length > 0);

function formatChangeValue(key: string, value: unknown): string {
    if (key.startsWith('token_') || key === 'firma_digital_password') {
        return value ? 'Se reemplazar? al guardar' : 'Sin reemplazo';
    }
    if (key === 'firma_digital_archivo') {
        return typeof value === 'object' && value
            ? String((value as { name?: string }).name ?? 'Nuevo archivo')
            : 'Sin archivo nuevo';
    }
    if (typeof value === 'boolean') return value ? 'S?' : 'No';
    if (key === 'tipo_facturacion') {
        return (
            enumOptions.tipoFacturacion.find(
                (option) => Number(option.value) === Number(value),
            )?.label ?? 'No definido'
        );
    }
    if (key === 'ambiente_facturacion') {
        return (
            enumOptions.ambiente.find((option) => option.value === value)
                ?.label ?? 'No definido'
        );
    }
    return value ? String(value) : 'Vacío';
}

const toggle = (key: string) => {
    store.clearMessages();
    store.state.form[key] = !Boolean(store.state.form[key]);
};

const captureBaseline = () => {
    baseline.value = formSnapshot();
};

const resetTokenEditors = () => {
    tokenEditors.legacy = false;
    tokenEditors.piloto = false;
    tokenEditors.produccion = false;
};

const aplicarCodigoSistemaAutomatico = () => {
    if (!codigoSistemaAutomatico.value) return;
    store.state.form.codigo_sistema = codigoSistemaAutomatico.value;
};

const discardChanges = async () => {
    if (singletonItem.value) store.startEdit(singletonItem.value);
    else store.startCreate();

    resetTokenEditors();
    fileInputKey.value += 1;
    store.clearMessages();
    await nextTick();
    captureBaseline();
    discardDialogOpen.value = false;
};

const submit = async () => {
    const saved = await store.save({ ...store.state.form });

    if (!saved) {
        const firstErrorField = Object.keys(store.state.errors)[0];
        if (firstErrorField && fieldSections[firstErrorField])
            activeSection.value = fieldSections[firstErrorField];
        return;
    }

    resetTokenEditors();
    fileInputKey.value += 1;
    await nextTick();
    captureBaseline();
};

const handleFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    store.clearMessages();
    store.state.form.firma_digital_archivo = input.files?.[0] ?? null;
};

const credentialStatus = (configuredKey: string, expirationKey?: string) => {
    if (!Boolean(store.state.form[configuredKey]))
        return { label: 'No configurado', tone: 'neutral' };
    if (!expirationKey || !store.state.form[expirationKey])
        return { label: 'Configurado', tone: 'success' };

    const expiration = new Date(String(store.state.form[expirationKey]));
    return expiration.getTime() > Date.now()
        ? { label: 'Vigente', tone: 'success' }
        : { label: 'Vencido', tone: 'danger' };
};

const openTokenEditor = (key: keyof typeof tokenEditors) => {
    tokenEditors[key] = true;
};

const cancelTokenEditor = (
    editor: keyof typeof tokenEditors,
    field: string,
) => {
    tokenEditors[editor] = false;
    store.state.form[field] = '';
};

const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!isDirty.value) return;
    event.preventDefault();
    event.returnValue = '';
};

let removeInertiaGuard: VoidFunction | undefined;

onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload);
    removeInertiaGuard = router.on('before', () => {
        if (!isDirty.value) return;
        return window.confirm(
            'Tienes cambios sin guardar. ¿Deseas salir de esta página?',
        );
    });

    await store.load();
    captureBaseline();
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    removeInertiaGuard?.();
});
</script>

<template>
    <ModulePageLayout
        title="Configuración general"
        description="Administra el comportamiento operativo de esta empresa."
        compact
        :breadcrumbs="[
            { title: 'Panel principal', href: '/dashboard' },
            {
                title: 'Configuración general',
                href: '/configuracion/configuracion',
            },
        ]"
    >
        <template #actions>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 border-[#b8c5bc] bg-white px-4 text-[#19221d] hover:bg-[#f5f8f6]"
                    :disabled="!isDirty || store.state.saving"
                    @click="discardDialogOpen = true"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    <span class="hidden sm:inline">Descartar cambios</span>
                    <span class="sm:hidden">Descartar</span>
                </Button>
                <Button
                    type="button"
                    class="h-10 bg-[#168447] px-4 text-white hover:bg-[#116c3a]"
                    :disabled="
                        !isDirty || store.state.saving || store.state.loading
                    "
                    @click="submit"
                >
                    <LoaderCircle
                        v-if="store.state.saving"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    <Save v-else class="mr-2 h-4 w-4" />
                    {{
                        store.state.saving ? 'Guardando...' : 'Guardar cambios'
                    }}
                </Button>
            </div>
        </template>

        <div
            v-if="store.state.loading"
            class="flex min-h-[420px] items-center justify-center rounded-2xl border border-[#dce5df] bg-white"
        >
            <div class="text-center text-[#536158]">
                <LoaderCircle
                    class="mx-auto mb-3 h-7 w-7 animate-spin text-[#168447]"
                />
                Cargando configuración...
            </div>
        </div>

        <div v-else class="space-y-4">
            <div
                v-if="store.state.successMessage"
                role="status"
                class="flex items-start gap-3 rounded-xl border border-[#b8dfc6] bg-[#eaf7ef] px-4 py-3 text-sm text-[#145d36]"
            >
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="font-semibold">Cambios guardados</p>
                    <p>{{ store.state.successMessage }}</p>
                </div>
            </div>

            <div
                v-if="store.state.generalError"
                role="alert"
                class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="font-semibold">
                        No se pudo completar la operación
                    </p>
                    <p>{{ store.state.generalError }}</p>
                </div>
            </div>

            <section
                aria-label="Resumen de configuración"
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            >
                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><FileCheck2 class="h-5 w-5"
                        /></span>
                        <div>
                            <p class="text-xs font-medium text-[#68776d]">
                                Facturación
                            </p>
                            <p
                                class="font-semibold"
                                :class="
                                    facturacionActiva
                                        ? 'text-[#168447]'
                                        : 'text-[#536158]'
                                "
                            >
                                {{ facturacionActiva ? 'Activa' : 'Inactiva' }}
                            </p>
                        </div>
                    </div>
                </article>
                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><Gauge class="h-5 w-5"
                        /></span>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-[#68776d]">
                                Modalidad
                            </p>
                            <p class="truncate font-semibold text-[#19221d]">
                                {{ tipoFacturacionLabel }}
                            </p>
                        </div>
                    </div>
                </article>
                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><CloudCog class="h-5 w-5"
                        /></span>
                        <div>
                            <p class="text-xs font-medium text-[#68776d]">
                                Ambiente
                            </p>
                            <p
                                class="font-semibold"
                                :class="
                                    ambienteProduccion
                                        ? 'text-amber-700'
                                        : 'text-[#168447]'
                                "
                            >
                                {{ ambienteLabel }}
                            </p>
                        </div>
                    </div>
                </article>
                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                            ><PackageCheck class="h-5 w-5"
                        /></span>
                        <div>
                            <p class="text-xs font-medium text-[#68776d]">
                                Última actualización
                            </p>
                            <p class="font-semibold text-[#19221d]">
                                {{ updatedAtLabel }}
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <div
                v-if="ambienteProduccion"
                role="alert"
                class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900"
            >
                <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <p class="font-semibold">
                        Los cambios en producción pueden afectar la emisión de
                        facturas
                    </p>
                    <p class="text-sm">
                        Revisa el ambiente, la modalidad y la vigencia del token
                        antes de guardar.
                    </p>
                </div>
            </div>

            <div
                class="grid items-start gap-4 lg:grid-cols-[240px_minmax(0,1fr)_280px]"
            >
                <nav
                    aria-label="Secciones de configuración"
                    class="overflow-x-auto rounded-xl border border-[#dce5df] bg-white p-2 shadow-sm lg:sticky lg:top-4"
                >
                    <div class="flex min-w-max gap-1 lg:min-w-0 lg:flex-col">
                        <button
                            v-for="section in sections"
                            :key="section.key"
                            type="button"
                            :aria-current="
                                activeSection === section.key
                                    ? 'page'
                                    : undefined
                            "
                            class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-[#20a85b] focus-visible:outline-none lg:w-full"
                            :class="
                                activeSection === section.key
                                    ? 'bg-[#eaf7ef] text-[#168447]'
                                    : 'text-[#34433a] hover:bg-[#f5f8f6]'
                            "
                            @click="activeSection = section.key"
                        >
                            <component
                                :is="section.icon"
                                class="h-4 w-4 shrink-0"
                            />
                            <span>{{ section.label }}</span>
                            <ChevronRight
                                class="ml-auto hidden h-4 w-4 lg:block"
                                :class="
                                    activeSection === section.key
                                        ? 'opacity-100'
                                        : 'opacity-0'
                                "
                            />
                        </button>
                    </div>
                </nav>

                <form class="min-w-0" @submit.prevent="submit">
                    <section
                        v-if="activeSection === 'general'"
                        aria-labelledby="general-title"
                        class="rounded-xl border border-[#dce5df] bg-white shadow-sm"
                    >
                        <header class="border-b border-[#e4ebe6] px-5 py-4">
                            <h2
                                id="general-title"
                                class="font-semibold text-[#101713]"
                            >
                                Configuración general
                            </h2>
                            <p class="mt-1 text-sm text-[#68776d]">
                                Estado del registro ?nico que gobierna la
                                operación del sistema.
                            </p>
                        </header>
                        <div class="space-y-5 p-5">
                            <div
                                class="flex items-start gap-4 rounded-xl border border-[#dce5df] bg-[#f5f8f6] p-4"
                            >
                                <span
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white text-[#168447]"
                                    ><Settings2 class="h-5 w-5"
                                /></span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p
                                                class="font-semibold text-[#19221d]"
                                            >
                                                Configuración activa
                                            </p>
                                            <p class="text-sm text-[#68776d]">
                                                Mantiene vigente este registro
                                                general del sistema.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="
                                                Boolean(store.state.form.estado)
                                            "
                                            aria-label="Activar o desactivar la configuración"
                                            class="relative h-6 w-11 shrink-0 rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-[#20a85b] focus-visible:ring-offset-2 focus-visible:outline-none"
                                            :class="
                                                store.state.form.estado
                                                    ? 'bg-[#168447]'
                                                    : 'bg-[#a9b5ad]'
                                            "
                                            @click="toggle('estado')"
                                        >
                                            <span
                                                class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                                :class="
                                                    store.state.form.estado
                                                        ? 'translate-x-5'
                                                        : 'translate-x-0'
                                                "
                                            />
                                        </button>
                                    </div>
                                    <InputError
                                        :message="
                                            store.state.errors.estado?.[0]
                                        "
                                    />
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-[#cde1d3] bg-[#eaf7ef] p-4 text-sm text-[#28533a]"
                            >
                                <div class="flex gap-3">
                                    <Info
                                        class="mt-0.5 h-5 w-5 shrink-0 text-[#168447]"
                                    />
                                    <p>
                                        Esta instalación utiliza un solo
                                        registro de configuración. Los cambios
                                        se aplican a toda la empresa al guardar.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-else-if="activeSection === 'productos'"
                        aria-labelledby="productos-title"
                        class="rounded-xl border border-[#dce5df] bg-white shadow-sm"
                    >
                        <header class="border-b border-[#e4ebe6] px-5 py-4">
                            <h2 id="productos-title" class="font-semibold text-[#101713]">
                                Productos
                            </h2>
                            <p class="mt-1 text-sm text-[#68776d]">
                                Define los campos visibles en el registro de productos.
                            </p>
                        </header>
                        <div class="grid gap-3 p-5 md:grid-cols-2">
                            <button
                                v-for="option in [
                                    {
                                        key: 'multiples_precios',
                                        label: 'Múltiples precios',
                                        description: 'Permite registrar más de un precio por producto.',
                                    },
                                    {
                                        key: 'precios_por_cantidad',
                                        label: 'Precios por cantidad',
                                        description: 'Activa escalas de precio por volumen.',
                                    },
                                    {
                                        key: 'productos_categorias_habilitadas',
                                        label: 'Categorías',
                                        description: 'Muestra categoría en filtros y formulario de productos.',
                                    },
                                    {
                                        key: 'productos_marcas_habilitadas',
                                        label: 'Marcas',
                                        description: 'Muestra marca en filtros y formulario de productos.',
                                    },
                                    {
                                        key: 'productos_busqueda_avanzada_habilitada',
                                        label: 'Atributos y palabras clave',
                                        description: 'Activa atributos dinámicos y palabras clave.',
                                    },
                                    {
                                        key: 'productos_codigo_barras_habilitado',
                                        label: 'Código de barras',
                                        description: 'Muestra el campo código de barras en el registro de productos.',
                                    },
                                ]"
                                :key="option.key"
                                type="button"
                                class="rounded-xl border p-4 text-left transition"
                                :class="
                                    store.state.form[option.key]
                                        ? 'border-[#9dd5ad] bg-[#eaf7ef]'
                                        : 'border-[#dce5df] bg-white hover:bg-[#f5f8f6]'
                                "
                                @click="toggle(option.key)"
                            >
                                <span class="text-sm font-semibold text-[#19221d]">
                                    {{ option.label }}
                                </span>
                                <span class="mt-1 block text-xs text-[#68776d]">
                                    {{ option.description }}
                                </span>
                            </button>
                        </div>
                    </section>

                    <section
                        v-else-if="activeSection === 'facturacion'"
                        aria-labelledby="facturacion-title"
                        class="space-y-4"
                    >
                        <div
                            class="rounded-xl border border-[#dce5df] bg-white shadow-sm"
                        >
                            <header class="border-b border-[#e4ebe6] px-5 py-4">
                                <h2
                                    id="facturacion-title"
                                    class="font-semibold text-[#101713]"
                                >
                                    Operación de facturación
                                </h2>
                                <p class="mt-1 text-sm text-[#68776d]">
                                    Modalidad y ambiente utilizados para emitir
                                    documentos fiscales.
                                </p>
                            </header>
                            <div class="divide-y divide-[#e4ebe6] px-5">
                                <div
                                    class="flex items-center justify-between gap-4 py-4"
                                >
                                    <div>
                                        <p class="font-semibold text-[#19221d]">
                                            Facturación habilitada
                                        </p>
                                        <p class="text-sm text-[#68776d]">
                                            Permite la emisión y el envío de
                                            facturas al SIAT.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="facturacionActiva"
                                        aria-label="Facturación habilitada"
                                        class="relative h-6 w-11 shrink-0 rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-[#20a85b] focus-visible:ring-offset-2 focus-visible:outline-none"
                                        :class="
                                            facturacionActiva
                                                ? 'bg-[#168447]'
                                                : 'bg-[#a9b5ad]'
                                        "
                                        @click="
                                            toggle('facturacion_habilitada')
                                        "
                                    >
                                        <span
                                            class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform"
                                            :class="
                                                facturacionActiva
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0'
                                            "
                                        />
                                    </button>
                                </div>
                                <InputError
                                    :message="
                                        store.state.errors
                                            .facturacion_habilitada?.[0]
                                    "
                                />
                                <div
                                    v-if="facturacionActiva"
                                    class="grid gap-4 py-4 md:grid-cols-[1fr_280px] md:items-center"
                                >
                                    <div>
                                        <p class="font-semibold text-[#19221d]">
                                            Tipo de facturación
                                        </p>
                                        <p class="text-sm text-[#68776d]">
                                            Define el método de facturación
                                            utilizado por el sistema.
                                        </p>
                                    </div>
                                    <div>
                                        <select
                                            id="tipo_facturacion"
                                            v-model="
                                                store.state.form
                                                    .tipo_facturacion
                                            "
                                            class="h-10 w-full rounded-lg border border-[#cbd7cf] bg-white px-3 text-sm text-[#19221d] outline-none focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20"
                                        >
                                            <option
                                                v-for="option in enumOptions.tipoFacturacion"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            :message="
                                                store.state.errors
                                                    .tipo_facturacion?.[0]
                                            "
                                        />
                                    </div>
                                </div>
                                <div
                                    v-if="requiereParametrosFacturacion"
                                    class="grid gap-4 py-4 md:grid-cols-[1fr_280px] md:items-center"
                                >
                                    <div>
                                        <p class="font-semibold text-[#19221d]">
                                            Ambiente SIAT
                                        </p>
                                        <p class="text-sm text-[#68776d]">
                                            Destino al que se enviarán las
                                            facturas.
                                        </p>
                                    </div>
                                    <div>
                                        <select
                                            id="ambiente_facturacion"
                                            v-model="
                                                store.state.form
                                                    .ambiente_facturacion
                                            "
                                            class="h-10 w-full rounded-lg border border-[#cbd7cf] bg-white px-3 text-sm text-[#19221d] outline-none focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20"
                                        >
                                            <option
                                                v-for="option in enumOptions.ambiente"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            :message="
                                                store.state.errors
                                                    .ambiente_facturacion?.[0]
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="requiereParametrosFacturacion"
                            class="rounded-xl border border-[#dce5df] bg-white shadow-sm"
                        >
                            <header class="border-b border-[#e4ebe6] px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <KeyRound class="h-5 w-5 text-[#168447]" />
                                    <h3 class="font-semibold text-[#101713]">
                                        Credenciales SIAT
                                    </h3>
                                </div>
                                <p class="mt-1 text-sm text-[#68776d]">
                                    Los valores guardados permanecen ocultos.
                                    Escribe uno nuevo únicamente para
                                    reemplazarlo al guardar.
                                </p>
                            </header>
                            <div class="space-y-4 p-5">
                                <div class="space-y-2">
                                    <Label
                                        for="codigo_sistema"
                                        class="font-semibold text-[#19221d]"
                                        >Código de sistema SIAT</Label
                                    >
                                    <Input
                                        id="codigo_sistema"
                                        v-model="
                                            store.state.form.codigo_sistema
                                        "
                                        autocomplete="off"
                                        class="border-[#cbd7cf] focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                        placeholder="Código asignado por el SIAT"
                                    />
                                    <InputError
                                        :message="
                                            store.state.errors
                                                .codigo_sistema?.[0]
                                        "
                                    />
                                </div>

                                <article
                                    v-for="credential in credentials"
                                    :key="credential.field"
                                    class="rounded-xl border border-[#dce5df] p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div
                                            class="flex min-w-0 items-start gap-3"
                                        >
                                            <span
                                                class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#eaf7ef] text-[#168447]"
                                                ><ShieldCheck class="h-4 w-4"
                                            /></span>
                                            <div>
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <p
                                                        class="font-semibold text-[#19221d]"
                                                    >
                                                        {{ credential.label }}
                                                    </p>
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                        :class="
                                                            credentialStatus(
                                                                credential.configured,
                                                                credential.expiration ?? undefined,
                                                            ).tone === 'success'
                                                                ? 'bg-[#eaf7ef] text-[#168447]'
                                                                : credentialStatus(
                                                                        credential.configured,
                                                                        credential.expiration ?? undefined,
                                                                    ).tone ===
                                                                    'danger'
                                                                  ? 'bg-red-50 text-red-700'
                                                                  : 'bg-[#eef2ef] text-[#68776d]'
                                                        "
                                                    >
                                                        {{
                                                            credentialStatus(
                                                                credential.configured,
                                                                credential.expiration ?? undefined,
                                                            ).label
                                                        }}
                                                    </span>
                                                </div>
                                                <p
                                                    class="text-sm text-[#68776d]"
                                                >
                                                    {{ credential.description }}
                                                </p>
                                                <p
                                                    v-if="
                                                        store.state.form[
                                                            credential
                                                                .configured
                                                        ]
                                                    "
                                                    class="mt-1 font-mono text-sm tracking-[0.18em] text-[#34433a]"
                                                >
                                                       
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            v-if="
                                                Boolean(
                                                    store.state.form[
                                                        credential.configured
                                                    ],
                                                ) &&
                                                !tokenEditors[credential.editor]
                                            "
                                            type="button"
                                            variant="outline"
                                            class="border-[#8fc6a3] text-[#168447] hover:bg-[#eaf7ef]"
                                            @click="
                                                openTokenEditor(
                                                    credential.editor,
                                                )
                                            "
                                            >Reemplazar token</Button
                                        >
                                    </div>
                                    <div
                                        v-if="
                                            tokenEditors[credential.editor] ||
                                            !Boolean(
                                                store.state.form[
                                                    credential.configured
                                                ],
                                            )
                                        "
                                        class="mt-4 grid gap-3"
                                        :class="
                                            credential.expiration
                                                ? 'md:grid-cols-2'
                                                : ''
                                        "
                                    >
                                        <div class="space-y-2">
                                            <Label :for="credential.field"
                                                >Nuevo
                                                {{
                                                    credential.label.toLowerCase()
                                                }}</Label
                                            >
                                            <Input
                                                :id="credential.field"
                                                v-model="
                                                    store.state.form[
                                                        credential.field
                                                    ]
                                                "
                                                type="password"
                                                autocomplete="new-password"
                                                class="border-[#cbd7cf] focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                                placeholder="Se aplicar al guardar"
                                            />
                                            <InputError
                                                :message="
                                                    store.state.errors[
                                                        credential.field
                                                    ]?.[0]
                                                "
                                            />
                                        </div>
                                        <div
                                            v-if="credential.expiration"
                                            class="space-y-2"
                                        >
                                            <Label :for="credential.expiration"
                                                >Vigencia</Label
                                            >
                                            <Input
                                                :id="credential.expiration"
                                                v-model="
                                                    store.state.form[
                                                        credential.expiration
                                                    ]
                                                "
                                                type="datetime-local"
                                                class="border-[#cbd7cf] focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                            />
                                            <InputError
                                                :message="
                                                    store.state.errors[
                                                        credential.expiration
                                                    ]?.[0]
                                                "
                                            />
                                        </div>
                                        <button
                                            v-if="
                                                Boolean(
                                                    store.state.form[
                                                        credential.configured
                                                    ],
                                                )
                                            "
                                            type="button"
                                            class="w-fit text-sm font-semibold text-[#536158] underline-offset-4 hover:underline"
                                            @click="
                                                cancelTokenEditor(
                                                    credential.editor,
                                                    credential.field,
                                                )
                                            "
                                        >
                                            Cancelar reemplazo
                                        </button>
                                    </div>
                                </article>
                            </div>
                        </div>
                        <div
                            v-else
                            class="rounded-xl border border-[#dce5df] bg-white p-5 text-sm text-[#68776d] shadow-sm"
                        >
                            <div class="flex gap-3">
                                <Info
                                    class="mt-0.5 h-5 w-5 shrink-0 text-[#168447]"
                                />
                                <p>
                                    Selecciona una modalidad de facturación
                                    distinta de No emite factura para
                                    administrar el ambiente y las credenciales
                                    SIAT.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-else
                        aria-labelledby="firma-title"
                        class="rounded-xl border border-[#dce5df] bg-white shadow-sm"
                    >
                        <header class="border-b border-[#e4ebe6] px-5 py-4">
                            <h2
                                id="firma-title"
                                class="font-semibold text-[#101713]"
                            >
                                Firma digital
                            </h2>
                            <p class="mt-1 text-sm text-[#68776d]">
                                Certificado usado para firmar XML en facturación
                                electrónica.
                            </p>
                        </header>
                        <div v-if="requiereFirmaDigital" class="space-y-5 p-5">
                            <div
                                class="flex items-start gap-4 rounded-xl border border-[#dce5df] bg-[#f5f8f6] p-4"
                            >
                                <span
                                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-[#168447]"
                                    ><FileKey2 class="h-5 w-5"
                                /></span>
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p class="font-semibold text-[#19221d]">
                                            Estado actual
                                        </p>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                store.state.form
                                                    .firma_digital_configurada
                                                    ? 'bg-[#eaf7ef] text-[#168447]'
                                                    : 'bg-amber-50 text-amber-700'
                                            "
                                            >{{
                                                store.state.form
                                                    .firma_digital_configurada
                                                    ? 'Configurada'
                                                    : 'Pendiente'
                                            }}</span
                                        >
                                    </div>
                                    <p class="mt-1 text-sm text-[#68776d]">
                                        {{
                                            store.state.form
                                                .firma_digital_nombre ||
                                            'Aún no se registró un certificado.'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="rounded-xl border border-[#cde1d3] bg-[#eaf7ef] p-4 text-sm text-[#28533a]"
                            >
                                <div class="flex gap-3">
                                    <Info
                                        class="mt-0.5 h-5 w-5 shrink-0 text-[#168447]"
                                    />
                                    <p>
                                        El archivo se almacena de forma privada
                                        en el servidor. La contraseña nunca se
                                        devuelve al navegador y solo se
                                        reemplaza cuando escribes una nueva.
                                    </p>
                                </div>
                            </div>
                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label
                                        for="firma_digital_archivo"
                                        class="font-semibold text-[#19221d]"
                                        >Certificado .p12 o .pfx</Label
                                    >
                                    <label
                                        for="firma_digital_archivo"
                                        class="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-[#9ab7a4] bg-[#f8faf9] p-4 text-center hover:bg-[#f0f6f2]"
                                    >
                                        <UploadCloud
                                            class="mb-2 h-6 w-6 text-[#168447]"
                                        />
                                        <span
                                            class="text-sm font-semibold text-[#19221d]"
                                            >Seleccionar certificado</span
                                        >
                                        <span
                                            class="mt-1 text-xs text-[#68776d]"
                                            >Máximo 5 MB</span
                                        >
                                    </label>
                                    <input
                                        :key="fileInputKey"
                                        id="firma_digital_archivo"
                                        type="file"
                                        accept=".p12,.pfx"
                                        class="sr-only"
                                        @change="handleFileChange"
                                    />
                                    <p
                                        v-if="selectedFirmaFile"
                                        class="text-sm text-[#168447]"
                                    >
                                        {{ selectedFirmaFile.name }}
                                    </p>
                                    <InputError
                                        :message="
                                            store.state.errors
                                                .firma_digital_archivo?.[0]
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label
                                        for="firma_digital_password"
                                        class="font-semibold text-[#19221d]"
                                        >Contraseña del certificado</Label
                                    >
                                    <Input
                                        id="firma_digital_password"
                                        v-model="
                                            store.state.form
                                                .firma_digital_password
                                        "
                                        type="password"
                                        autocomplete="new-password"
                                        class="border-[#cbd7cf] focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                        placeholder="Solo para un certificado nuevo"
                                    />
                                    <p class="text-xs text-[#68776d]">
                                        Déjala vacía para conservar la
                                        contraseña ya registrada.
                                    </p>
                                    <InputError
                                        :message="
                                            store.state.errors
                                                .firma_digital_password?.[0]
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-5">
                            <div
                                class="flex gap-3 rounded-xl border border-[#dce5df] bg-[#f5f8f6] p-4 text-sm text-[#68776d]"
                            >
                                <Info
                                    class="mt-0.5 h-5 w-5 shrink-0 text-[#168447]"
                                />
                                <p>
                                    La firma digital solo se requiere para la
                                    modalidad Factura electrónica en línea. El
                                    certificado existente se conservar sin
                                    cambios.
                                </p>
                            </div>
                        </div>
                    </section>
                </form>

                <aside class="space-y-4 lg:sticky lg:top-4">
                    <section
                        class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                    >
                        <h2 class="font-semibold text-[#101713]">
                            Antes de guardar
                        </h2>
                        <ul class="mt-4 space-y-4 text-sm">
                            <li class="flex gap-3">
                                <CheckCircle2
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#168447]"
                                />
                                <div>
                                    <p class="font-medium text-[#19221d]">
                                        Revisa el ambiente
                                    </p>
                                    <p class="text-xs text-[#68776d]">
                                        Confirma si operarás en piloto o
                                        producción.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <CheckCircle2
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#168447]"
                                />
                                <div>
                                    <p class="font-medium text-[#19221d]">
                                        Valida la modalidad
                                    </p>
                                    <p class="text-xs text-[#68776d]">
                                        No la cambies con documentos pendientes.
                                    </p>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <CheckCircle2
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#168447]"
                                />
                                <div>
                                    <p class="font-medium text-[#19221d]">
                                        Reemplaza solo lo necesario
                                    </p>
                                    <p class="text-xs text-[#68776d]">
                                        Los secretos vacíos se conservan.
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </section>
                    <section
                        class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-semibold text-[#101713]">
                                {{ changedFields.length }}
                                {{
                                    changedFields.length === 1
                                        ? 'cambio pendiente'
                                        : 'cambios pendientes'
                                }}
                            </h2>
                            <span
                                v-if="isDirty"
                                class="h-2 w-2 rounded-full bg-amber-500"
                            />
                        </div>
                        <p class="mt-1 text-xs text-[#68776d]">
                            Se aplicarán únicamente al guardar.
                        </p>
                        <div v-if="isDirty" class="mt-3 space-y-2">
                            <div
                                v-for="change in changedFields.slice(0, 4)"
                                :key="change.key"
                                class="rounded-lg bg-[#f5f8f6] px-3 py-2 text-xs"
                            >
                                <p class="font-semibold text-[#28533a]">
                                    {{ change.label }}
                                </p>
                                <p class="mt-1 text-[#68776d]">
                                    {{ change.before }}
                                    <span class="px-1 text-[#168447]"></span>
                                    {{ change.after }}
                                </p>
                            </div>
                            <p
                                v-if="changedFields.length > 4"
                                class="text-xs font-medium text-[#536158]"
                            >
                                Y {{ changedFields.length - 4 }} cambios más.
                            </p>
                        </div>
                        <p
                            v-else
                            class="mt-3 rounded-lg bg-[#eaf7ef] px-3 py-2 text-sm text-[#168447]"
                        >
                            Todo est? actualizado.
                        </p>
                    </section>
                </aside>
            </div>

            <div
                v-if="isDirty"
                class="sticky bottom-3 z-20 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/95 px-4 py-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3 text-amber-900">
                    <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Cambios sin guardar</p>
                        <p class="text-sm">
                            Tienes {{ changedFields.length }}
                            {{
                                changedFields.length === 1
                                    ? 'modificación pendiente'
                                    : 'modificaciones pendientes'
                            }}.
                        </p>
                    </div>
                </div>
                <div class="flex gap-2 sm:shrink-0">
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1 border-amber-300 bg-white text-[#19221d] sm:flex-none"
                        :disabled="store.state.saving"
                        @click="discardDialogOpen = true"
                        >Descartar</Button
                    >
                    <Button
                        type="button"
                        class="flex-1 bg-[#168447] text-white hover:bg-[#116c3a] sm:flex-none"
                        :disabled="store.state.saving"
                        @click="submit"
                        ><LoaderCircle
                            v-if="store.state.saving"
                            class="mr-2 h-4 w-4 animate-spin"
                        /><Save v-else class="mr-2 h-4 w-4" />{{
                            store.state.saving
                                ? 'Guardando...'
                                : 'Guardar cambios'
                        }}</Button
                    >
                </div>
            </div>
        </div>

        <Dialog v-model:open="discardDialogOpen">
            <DialogContent class="max-w-md border-[#dce5df] bg-white">
                <DialogHeader>
                    <div
                        class="mb-2 grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-700"
                    >
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <DialogTitle>Descartar cambios"</DialogTitle>
                    <DialogDescription
                        >Se restaurarán los últimos valores guardados. Esta
                        acción no modifica la configuración del
                        servidor.</DialogDescription
                    >
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="discardDialogOpen = false"
                        >Continuar editando</Button
                    >
                    <Button
                        type="button"
                        class="bg-amber-600 text-white hover:bg-amber-700"
                        @click="discardChanges"
                        >Descartar cambios</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

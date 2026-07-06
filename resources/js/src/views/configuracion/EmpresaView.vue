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
import { useEmpresaStore } from '@/src/stores/empresaStore';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    CalendarClock,
    Check,
    CheckCircle2,
    FileCheck2,
    Image as ImageIcon,
    LoaderCircle,
    Mail,
    MapPin,
    Phone,
    RefreshCw,
    RotateCcw,
    Save,
    ShieldCheck,
    Upload,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const editableFields = [
    'nombre_empresa',
    'razon_social',
    'nit',
    'propietario',
    'telefono',
    'correo',
    'direccion',
    'logo',
    'estado',
] as const;

const store = useEmpresaStore();
const baseline = ref<Record<string, unknown>>({});
const discardDialogOpen = ref(false);
const logoInputKey = ref(0);
const logoPreviewUrl = ref('');

const normalizeValue = (value: unknown): unknown => {
    if (value instanceof File) {
        return { name: value.name, size: value.size, type: value.type };
    }

    return value ?? '';
};

const formSnapshot = () =>
    Object.fromEntries(
        editableFields.map((field) => [
            field,
            normalizeValue(store.state.form[field]),
        ]),
    );

const changedFields = computed(() =>
    editableFields.filter(
        (field) =>
            JSON.stringify(normalizeValue(store.state.form[field])) !==
            JSON.stringify(baseline.value[field]),
    ),
);
const isDirty = computed(() => changedFields.value.length > 0);
const hasFiscalData = computed(
    () =>
        store.state.form.nombre_empresa.trim() !== '' &&
        store.state.form.razon_social.trim() !== '' &&
        store.state.form.nit.trim() !== '',
);
const hasLogo = computed(
    () =>
        store.state.form.logo instanceof File ||
        Boolean(store.state.item?.logo_configurado),
);
const currentLogoUrl = computed(
    () =>
        logoPreviewUrl.value ||
        (store.state.item?.logo_configurado
            ? String(store.state.item.logo_url ?? '')
            : ''),
);
const updatedAtLabel = computed(() => {
    const value = store.state.item?.updated_at;

    if (!value) return 'Sin registrar';

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(String(value)));
});

const captureBaseline = () => {
    baseline.value = formSnapshot();
};

const releaseLogoPreview = () => {
    if (!logoPreviewUrl.value) return;
    URL.revokeObjectURL(logoPreviewUrl.value);
    logoPreviewUrl.value = '';
};

const fieldError = (field: string) => store.state.errors[field]?.[0];

const clearField = (field: string) => {
    store.clearFieldError(field);
};

const handleLogoChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    clearField('logo');

    if (!file) return;

    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
        store.state.errors.logo = ['Selecciona una imagen PNG, JPG o WEBP.'];
        input.value = '';
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        store.state.errors.logo = ['El logo no puede superar los 2 MB.'];
        input.value = '';
        return;
    }

    releaseLogoPreview();
    store.state.form.logo = file;
    logoPreviewUrl.value = URL.createObjectURL(file);
};

const validateForm = (): boolean => {
    const errors: Record<string, string[]> = {};
    const logoError = fieldError('logo');

    if (logoError) errors.logo = [logoError];

    if (!store.state.form.nombre_empresa.trim()) {
        errors.nombre_empresa = ['El nombre comercial es obligatorio.'];
    }

    if (!store.state.form.razon_social.trim()) {
        errors.razon_social = ['La razón social es obligatoria.'];
    }

    if (
        store.state.form.correo.trim() &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(store.state.form.correo)
    ) {
        errors.correo = ['Ingresa un correo electrónico válido.'];
    }

    store.state.errors = errors;

    if (Object.keys(errors).length === 0) return true;

    nextTick(() => {
        const firstField = Object.keys(errors)[0];
        document.getElementById(firstField)?.focus();
    });

    return false;
};

const submit = async () => {
    store.clearMessages();
    if (!validateForm()) return;

    const payload: Record<string, unknown> = {
        nombre_empresa: store.state.form.nombre_empresa.trim(),
        razon_social: store.state.form.razon_social.trim(),
        nit: store.state.form.nit.trim(),
        propietario: store.state.form.propietario.trim(),
        telefono: store.state.form.telefono.trim(),
        correo: store.state.form.correo.trim(),
        direccion: store.state.form.direccion.trim(),
        estado: store.state.form.estado,
    };

    if (store.state.form.logo instanceof File) {
        payload.logo = store.state.form.logo;
    }

    const saved = await store.save(payload);

    if (!saved) {
        await nextTick();
        const firstField = Object.keys(store.state.errors)[0];
        if (firstField) document.getElementById(firstField)?.focus();
        return;
    }

    releaseLogoPreview();
    logoInputKey.value += 1;
    await nextTick();
    captureBaseline();
};

const discardChanges = async () => {
    if (store.state.item) store.startEdit(store.state.item);
    else store.startCreate();

    releaseLogoPreview();
    logoInputKey.value += 1;
    store.clearMessages();
    discardDialogOpen.value = false;
    await nextTick();
    captureBaseline();
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
    releaseLogoPreview();
});
</script>

<template>
    <Head title="Perfil de empresa" />

    <ModulePageLayout
        title="Perfil de empresa"
        description="Administra la identidad comercial y los datos fiscales de esta instalación."
        compact
        :breadcrumbs="[
            { title: 'Panel principal', href: '/dashboard' },
            { title: 'Perfil de empresa', href: '/configuracion/empresa' },
        ]"
    >
        <template #actions>
            <div class="flex w-full gap-2 sm:w-auto">
                <Button
                    type="button"
                    variant="outline"
                    class="h-10 flex-1 border-[#b8c5bc] bg-white px-4 text-[#19221d] hover:bg-[#f5f8f6] sm:flex-none"
                    :disabled="!isDirty || store.state.saving"
                    @click="discardDialogOpen = true"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    Descartar cambios
                </Button>
                <Button
                    type="button"
                    class="h-10 flex-1 bg-[#168447] px-4 text-white hover:bg-[#116c3a] sm:flex-none"
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
            class="flex min-h-[520px] items-center justify-center rounded-2xl border border-[#dce5df] bg-white"
            aria-live="polite"
        >
            <div class="text-center text-[#536158]">
                <LoaderCircle
                    class="mx-auto mb-3 h-8 w-8 animate-spin text-[#168447]"
                />
                <p class="font-medium">Cargando perfil de empresa...</p>
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
                aria-label="Resumen de empresa"
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                        >
                            <CheckCircle2
                                v-if="store.state.form.estado"
                                class="h-6 w-6"
                            />
                            <AlertTriangle v-else class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-xs font-medium text-[#667269]">
                                Empresa activa
                            </p>
                            <p class="mt-0.5 text-lg font-bold text-[#101713]">
                                {{
                                    store.state.form.estado
                                        ? 'Activa'
                                        : 'Inactiva'
                                }}
                            </p>
                            <p class="text-xs text-[#667269]">Estado actual</p>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                        >
                            <FileCheck2 class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-xs font-medium text-[#667269]">
                                Datos fiscales completos
                            </p>
                            <p class="mt-0.5 text-lg font-bold text-[#101713]">
                                {{ hasFiscalData ? 'Completos' : 'Pendientes' }}
                            </p>
                            <p class="text-xs text-[#667269]">
                                Razón social y NIT
                            </p>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                        >
                            <ImageIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-xs font-medium text-[#667269]">
                                Logo configurado
                            </p>
                            <p class="mt-0.5 text-lg font-bold text-[#101713]">
                                {{ hasLogo ? 'Sí' : 'No' }}
                            </p>
                            <p class="text-xs text-[#667269]">
                                {{
                                    hasLogo ? 'Imagen disponible' : 'Sin imagen'
                                }}
                            </p>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-xl border border-[#dce5df] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                        >
                            <CalendarClock class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-[#667269]">
                                Última actualización
                            </p>
                            <p class="mt-0.5 truncate font-bold text-[#101713]">
                                {{ updatedAtLabel }}
                            </p>
                            <p class="text-xs text-[#667269]">
                                Registro principal
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <div
                class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.8fr)]"
            >
                <div class="space-y-4">
                    <section
                        aria-labelledby="identity-title"
                        class="rounded-xl border border-[#dce5df] bg-white p-5 shadow-sm md:p-6"
                    >
                        <div
                            class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <h2
                                    id="identity-title"
                                    class="text-lg font-bold text-[#101713]"
                                >
                                    Identidad empresarial
                                </h2>
                                <p class="mt-1 text-sm text-[#667269]">
                                    Información que identifica legal y
                                    comercialmente a la empresa.
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="store.state.form.estado"
                                class="inline-flex items-center gap-3 self-start rounded-lg border border-[#dce5df] bg-[#f5f8f6] px-3 py-2 text-sm font-semibold text-[#19221d] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:ring-offset-2"
                                @click="
                                    store.state.form.estado =
                                        !store.state.form.estado;
                                    clearField('estado');
                                "
                            >
                                <span
                                    :class="[
                                        'relative h-5 w-9 rounded-full transition-colors',
                                        store.state.form.estado
                                            ? 'bg-[#168447]'
                                            : 'bg-[#9aa59e]',
                                    ]"
                                    aria-hidden="true"
                                >
                                    <span
                                        :class="[
                                            'absolute top-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition-transform',
                                            store.state.form.estado
                                                ? 'translate-x-[18px]'
                                                : 'translate-x-0.5',
                                        ]"
                                    />
                                </span>
                                {{
                                    store.state.form.estado
                                        ? 'Empresa activa'
                                        : 'Empresa inactiva'
                                }}
                            </button>
                        </div>

                        <div
                            class="grid gap-5 lg:grid-cols-[180px_minmax(0,1fr)]"
                        >
                            <div class="space-y-3">
                                <Label
                                    for="logo"
                                    class="font-semibold text-[#19221d]"
                                    >Logo de la empresa</Label
                                >
                                <div
                                    class="grid aspect-square w-full place-items-center overflow-hidden rounded-xl border-2 border-dashed border-[#b8c5bc] bg-[#f5f8f6]"
                                >
                                    <img
                                        v-if="currentLogoUrl"
                                        :src="currentLogoUrl"
                                        alt="Vista previa del logo de la empresa"
                                        class="h-full w-full object-contain p-4"
                                    />
                                    <Building2
                                        v-else
                                        class="h-16 w-16 text-[#168447]"
                                        aria-hidden="true"
                                    />
                                </div>
                                <label
                                    for="logo"
                                    class="flex h-10 cursor-pointer items-center justify-center rounded-md border border-[#b8c5bc] bg-white px-3 text-sm font-semibold text-[#168447] transition-colors focus-within:ring-2 focus-within:ring-[#168447] focus-within:ring-offset-2 hover:bg-[#eaf7ef]"
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    {{
                                        hasLogo ? 'Cambiar logo' : 'Subir logo'
                                    }}
                                    <input
                                        :key="logoInputKey"
                                        id="logo"
                                        type="file"
                                        class="sr-only"
                                        accept="image/png,image/jpeg,image/webp"
                                        :aria-invalid="
                                            Boolean(fieldError('logo'))
                                        "
                                        aria-describedby="logo-help logo-error"
                                        @change="handleLogoChange"
                                    />
                                </label>
                                <p
                                    id="logo-help"
                                    class="text-xs leading-relaxed text-[#667269]"
                                >
                                    PNG, JPG o WEBP. Máximo 2 MB. Recomendado:
                                    300 × 300 px.
                                </p>
                                <InputError
                                    id="logo-error"
                                    :message="fieldError('logo')"
                                />
                            </div>

                            <div
                                class="grid content-start gap-4 md:grid-cols-2"
                            >
                                <div class="space-y-2">
                                    <Label
                                        for="nombre_empresa"
                                        class="font-semibold text-[#19221d]"
                                    >
                                        Nombre comercial
                                        <span class="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="nombre_empresa"
                                        v-model="
                                            store.state.form.nombre_empresa
                                        "
                                        placeholder="Ej. Comercial Andina S.R.L."
                                        class="h-10 border-[#cbd6cf] bg-white focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                        :aria-invalid="
                                            Boolean(
                                                fieldError('nombre_empresa'),
                                            )
                                        "
                                        aria-describedby="nombre_empresa-error"
                                        @input="clearField('nombre_empresa')"
                                    />
                                    <InputError
                                        id="nombre_empresa-error"
                                        :message="fieldError('nombre_empresa')"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label
                                        for="razon_social"
                                        class="font-semibold text-[#19221d]"
                                    >
                                        Razón social
                                        <span class="text-red-600">*</span>
                                    </Label>
                                    <Input
                                        id="razon_social"
                                        v-model="store.state.form.razon_social"
                                        placeholder="Razón social registrada"
                                        class="h-10 border-[#cbd6cf] bg-white focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                        :aria-invalid="
                                            Boolean(fieldError('razon_social'))
                                        "
                                        aria-describedby="razon_social-error"
                                        @input="clearField('razon_social')"
                                    />
                                    <InputError
                                        id="razon_social-error"
                                        :message="fieldError('razon_social')"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label
                                        for="nit"
                                        class="font-semibold text-[#19221d]"
                                        >NIT</Label
                                    >
                                    <Input
                                        id="nit"
                                        v-model="store.state.form.nit"
                                        inputmode="numeric"
                                        placeholder="Número de identificación tributaria"
                                        class="h-10 border-[#cbd6cf] bg-white focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                        :aria-invalid="
                                            Boolean(fieldError('nit'))
                                        "
                                        aria-describedby="nit-error"
                                        @input="clearField('nit')"
                                    />
                                    <InputError
                                        id="nit-error"
                                        :message="fieldError('nit')"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label
                                        for="propietario"
                                        class="font-semibold text-[#19221d]"
                                        >Propietario</Label
                                    >
                                    <Input
                                        id="propietario"
                                        v-model="store.state.form.propietario"
                                        placeholder="Responsable principal"
                                        class="h-10 border-[#cbd6cf] bg-white focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                        :aria-invalid="
                                            Boolean(fieldError('propietario'))
                                        "
                                        aria-describedby="propietario-error"
                                        @input="clearField('propietario')"
                                    />
                                    <InputError
                                        id="propietario-error"
                                        :message="fieldError('propietario')"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        aria-labelledby="contact-title"
                        class="rounded-xl border border-[#dce5df] bg-white p-5 shadow-sm md:p-6"
                    >
                        <div class="mb-5">
                            <h2
                                id="contact-title"
                                class="text-lg font-bold text-[#101713]"
                            >
                                Información de contacto
                            </h2>
                            <p class="mt-1 text-sm text-[#667269]">
                                Datos visibles en documentos y comunicaciones
                                comerciales.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label
                                    for="telefono"
                                    class="font-semibold text-[#19221d]"
                                    >Teléfono</Label
                                >
                                <Input
                                    id="telefono"
                                    v-model="store.state.form.telefono"
                                    inputmode="tel"
                                    placeholder="Ej. 2 2452333"
                                    class="h-10 border-[#cbd6cf] bg-white focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                    :aria-invalid="
                                        Boolean(fieldError('telefono'))
                                    "
                                    aria-describedby="telefono-error"
                                    @input="clearField('telefono')"
                                />
                                <InputError
                                    id="telefono-error"
                                    :message="fieldError('telefono')"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label
                                    for="correo"
                                    class="font-semibold text-[#19221d]"
                                    >Correo</Label
                                >
                                <Input
                                    id="correo"
                                    v-model="store.state.form.correo"
                                    type="email"
                                    placeholder="contacto@empresa.com"
                                    class="h-10 border-[#cbd6cf] bg-white focus-visible:border-[#168447] focus-visible:ring-[#168447]/20"
                                    :aria-invalid="
                                        Boolean(fieldError('correo'))
                                    "
                                    aria-describedby="correo-error"
                                    @input="clearField('correo')"
                                />
                                <InputError
                                    id="correo-error"
                                    :message="fieldError('correo')"
                                />
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label
                                    for="direccion"
                                    class="font-semibold text-[#19221d]"
                                    >Dirección fiscal</Label
                                >
                                <textarea
                                    id="direccion"
                                    v-model="store.state.form.direccion"
                                    rows="3"
                                    placeholder="Dirección legal de la empresa para documentos fiscales"
                                    class="w-full rounded-md border border-[#cbd6cf] bg-white px-3 py-2 text-sm text-[#19221d] shadow-xs outline-none placeholder:text-[#7b877f] focus:border-[#168447] focus:ring-3 focus:ring-[#168447]/20"
                                    :aria-invalid="
                                        Boolean(fieldError('direccion'))
                                    "
                                    aria-describedby="direccion-help direccion-error"
                                    @input="clearField('direccion')"
                                />
                                <p
                                    id="direccion-help"
                                    class="text-xs text-[#667269]"
                                >
                                    Dirección legal utilizada en facturas y
                                    reportes.
                                </p>
                                <InputError
                                    id="direccion-error"
                                    :message="fieldError('direccion')"
                                />
                            </div>
                        </div>
                    </section>

                    <aside
                        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900"
                    >
                        <AlertTriangle
                            class="mt-0.5 h-6 w-6 shrink-0 text-amber-700"
                        />
                        <div>
                            <p class="font-semibold">
                                Los cambios fiscales se aplicarán a los
                                documentos emitidos en adelante
                            </p>
                            <p class="mt-1 text-sm text-amber-800">
                                Verifica la razón social, el NIT y la dirección
                                antes de guardar. Los documentos ya emitidos no
                                se modificarán.
                            </p>
                        </div>
                    </aside>
                </div>

                <aside
                    aria-labelledby="preview-title"
                    class="rounded-xl border border-[#dce5df] bg-white p-5 shadow-sm xl:sticky xl:top-4"
                >
                    <h2
                        id="preview-title"
                        class="text-lg font-bold text-[#101713]"
                    >
                        Vista previa de identidad
                    </h2>
                    <p class="mt-1 text-sm text-[#667269]">
                        Se actualiza mientras editas, sin guardar datos.
                    </p>

                    <div
                        class="mt-5 rounded-xl border border-[#dce5df] bg-[#f5f8f6] p-5"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-xl border border-[#dce5df] bg-white"
                            >
                                <img
                                    v-if="currentLogoUrl"
                                    :src="currentLogoUrl"
                                    alt="Logo en la vista previa"
                                    class="h-full w-full object-contain p-2"
                                />
                                <Building2
                                    v-else
                                    class="h-11 w-11 text-[#168447]"
                                    aria-hidden="true"
                                />
                            </div>
                            <div class="min-w-0 pt-1">
                                <p
                                    class="text-lg font-bold break-words text-[#101713]"
                                >
                                    {{
                                        store.state.form.nombre_empresa ||
                                        'Nombre comercial'
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-sm leading-relaxed break-words text-[#536158]"
                                >
                                    {{
                                        store.state.form.razon_social ||
                                        'Razón social de la empresa'
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-sm font-semibold text-[#19221d]"
                                >
                                    NIT:
                                    {{
                                        store.state.form.nit || 'Sin registrar'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="my-5 h-px bg-[#d6e0da]" />

                        <dl class="space-y-3 text-sm text-[#445149]">
                            <div class="flex gap-3">
                                <MapPin
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#168447]"
                                />
                                <dd class="break-words">
                                    {{
                                        store.state.form.direccion ||
                                        'Dirección fiscal sin registrar'
                                    }}
                                </dd>
                            </div>
                            <div class="flex gap-3">
                                <Phone
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#168447]"
                                />
                                <dd>
                                    {{
                                        store.state.form.telefono ||
                                        'Teléfono sin registrar'
                                    }}
                                </dd>
                            </div>
                            <div class="flex gap-3">
                                <Mail
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#168447]"
                                />
                                <dd class="break-all">
                                    {{
                                        store.state.form.correo ||
                                        'Correo sin registrar'
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <span
                            :class="[
                                'mt-5 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                                store.state.form.estado
                                    ? 'bg-[#eaf7ef] text-[#168447]'
                                    : 'bg-[#e3e8e5] text-[#536158]',
                            ]"
                        >
                            <Check
                                v-if="store.state.form.estado"
                                class="mr-1 h-3.5 w-3.5"
                            />
                            {{
                                store.state.form.estado ? 'Activa' : 'Inactiva'
                            }}
                        </span>
                    </div>

                    <div
                        class="mt-4 flex items-start gap-3 rounded-xl border border-[#c7e4d1] bg-[#eaf7ef] p-4 text-[#145d36]"
                    >
                        <ShieldCheck class="mt-0.5 h-6 w-6 shrink-0" />
                        <div>
                            <p class="font-semibold">
                                Así se mostrará la información de tu empresa
                            </p>
                            <p class="mt-1 text-sm text-[#34704f]">
                                En facturas, notas fiscales y reportes del
                                sistema.
                            </p>
                        </div>
                    </div>
                </aside>
            </div>

            <div
                v-if="isDirty"
                role="status"
                class="sticky bottom-3 z-20 flex flex-col gap-3 rounded-xl border border-[#b8c5bc] bg-white/95 px-4 py-3 shadow-[0_14px_36px_-16px_rgba(16,23,19,0.45)] backdrop-blur sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3 text-[#19221d]">
                    <span
                        class="mt-1 h-3 w-3 shrink-0 rounded-full bg-amber-400"
                    />
                    <div>
                        <p class="font-semibold">Cambios sin guardar</p>
                        <p class="text-sm text-[#667269]">
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
                        class="flex-1 border-[#b8c5bc] bg-white text-[#19221d] hover:bg-[#f5f8f6] sm:flex-none"
                        :disabled="store.state.saving"
                        @click="discardDialogOpen = true"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Descartar cambios
                    </Button>
                    <Button
                        type="button"
                        class="flex-1 bg-[#168447] text-white hover:bg-[#116c3a] sm:flex-none"
                        :disabled="store.state.saving"
                        @click="submit"
                    >
                        <LoaderCircle
                            v-if="store.state.saving"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Save v-else class="mr-2 h-4 w-4" />
                        {{
                            store.state.saving
                                ? 'Guardando...'
                                : 'Guardar cambios'
                        }}
                    </Button>
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
                    <DialogTitle>¿Descartar cambios?</DialogTitle>
                    <DialogDescription>
                        Se restaurarán los últimos datos guardados, incluido el
                        logo seleccionado. Esta acción no modifica el servidor.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="discardDialogOpen = false"
                    >
                        Continuar editando
                    </Button>
                    <Button
                        type="button"
                        class="bg-amber-600 text-white hover:bg-amber-700"
                        @click="discardChanges"
                    >
                        Descartar cambios
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

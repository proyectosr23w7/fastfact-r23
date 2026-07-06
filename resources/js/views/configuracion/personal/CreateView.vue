<script setup lang="ts">
import EntityFormCard from '@/components/shared/EntityFormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem, Puesto, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

interface PageProps extends SharedData {
    puestos: Puesto[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Personal', href: '/configuracion/personal' },
    { title: 'Crear', href: '/configuracion/personal/create' },
];

const { props } = usePage<PageProps>();

const form = useForm({
    nombre_completo: '',
    telefono: '',
    numero_documento: '',
    fecha_ingreso: '',
    puesto_id: '',
    activo: true,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        puesto_id: data.puesto_id === '' ? null : Number(data.puesto_id),
    })).post('/configuracion/personal');
};
</script>

<template>
    <Head title="Registrar personal" />
    <ModulePageLayout
        title="Registrar personal"
        :breadcrumbs="breadcrumbs"
        description="Gestiona colaboradores con una ficha clara y preparada para crecimiento por sucursal y permisos."
    >
        <EntityFormCard
            title="Nuevo registro de personal"
            description="Captura informacion base del colaborador y vincula su puesto desde esta capa de configuracion."
        >
            <form class="space-y-6" @submit.prevent="submit">
                <div class="company-form-grid">
                    <div class="company-field md:col-span-2">
                        <Label for="nombre_completo" class="company-label">Nombre completo</Label>
                        <Input id="nombre_completo" v-model="form.nombre_completo" class="company-input" />
                        <InputError :message="form.errors.nombre_completo" />
                    </div>

                    <div class="company-field">
                        <Label for="numero_documento" class="company-label">Documento</Label>
                        <Input id="numero_documento" v-model="form.numero_documento" class="company-input" />
                        <InputError :message="form.errors.numero_documento" />
                    </div>

                    <div class="company-field">
                        <Label for="telefono" class="company-label">Telefono</Label>
                        <Input id="telefono" v-model="form.telefono" class="company-input" />
                        <InputError :message="form.errors.telefono" />
                    </div>

                    <div class="company-field">
                        <Label for="fecha_ingreso" class="company-label">Fecha de ingreso</Label>
                        <Input id="fecha_ingreso" v-model="form.fecha_ingreso" type="date" class="company-input" />
                        <InputError :message="form.errors.fecha_ingreso" />
                    </div>

                    <div class="company-field">
                        <Label for="puesto_id" class="company-label">Puesto</Label>
                        <select id="puesto_id" v-model="form.puesto_id" class="company-select">
                            <option value="">Sin puesto asignado</option>
                            <option v-for="puesto in props.puestos" :key="puesto.id" :value="String(puesto.id)">
                                {{ puesto.nombre }}
                            </option>
                        </select>
                        <InputError :message="form.errors.puesto_id" />
                    </div>

                    <div class="company-field md:col-span-2">
                        <label class="company-switch">
                            <input v-model="form.activo" type="checkbox" class="h-4 w-4 accent-[hsl(150_54%_19%)]" />
                            <span class="space-y-1">
                                <span class="block text-sm font-semibold text-foreground">Registro activo</span>
                                <span class="block text-sm text-muted-foreground">
                                    Mantiene al colaborador disponible para futuras operaciones y asignaciones.
                                </span>
                            </span>
                        </label>
                        <InputError :message="form.errors.activo" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/personal">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Guardando...' : 'Guardar personal' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

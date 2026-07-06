<script setup lang="ts">
import EntityFormCard from '@/components/shared/EntityFormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem, Role, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

interface PageProps extends SharedData {
    role: Role;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Roles', href: '/configuracion/roles' },
    { title: 'Editar', href: '#' },
];

const { props } = usePage<PageProps>();

const form = useForm({
    nombre: props.role.nombre,
    descripcion: props.role.descripcion ?? '',
});

const submit = () => {
    form.put(`/configuracion/roles/${props.role.id}`);
};
</script>

<template>
    <Head :title="`Editar ${props.role.nombre}`" />
    <ModulePageLayout
        title="Editar rol"
        :breadcrumbs="breadcrumbs"
        description="Mantiene coherencia entre seguridad, jerarquias operativas y futuras asignaciones de permisos."
    >
        <EntityFormCard
            :title="`Editar ${props.role.nombre}`"
            description="Actualiza la definicion del rol sin salir de la estructura modular del proyecto."
        >
            <form class="space-y-6" @submit.prevent="submit">
                <div class="company-form-grid">
                    <div class="company-field">
                        <Label for="nombre" class="company-label">Nombre</Label>
                        <Input id="nombre" v-model="form.nombre" class="company-input" />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="company-field md:col-span-2">
                        <Label for="descripcion" class="company-label">Descripcion</Label>
                        <textarea id="descripcion" v-model="form.descripcion" class="company-textarea" />
                        <InputError :message="form.errors.descripcion" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/roles">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Actualizando...' : 'Actualizar rol' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

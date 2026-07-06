<script setup lang="ts">
import EntityFormCard from '@/components/shared/EntityFormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem, Permiso, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

interface PageProps extends SharedData {
    permiso: Permiso;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Permisos', href: '/configuracion/permisos' },
    { title: 'Editar', href: '#' },
];

const { props } = usePage<PageProps>();

const form = useForm({
    codigo: props.permiso.codigo,
    descripcion: props.permiso.descripcion,
    modulo: props.permiso.modulo,
});

const submit = () => {
    form.put(`/configuracion/permisos/${props.permiso.id}`);
};
</script>

<template>
    <Head :title="`Editar ${props.permiso.codigo}`" />
    <ModulePageLayout
        title="Editar permiso"
        :breadcrumbs="breadcrumbs"
        description="Mantiene una matriz de acceso ordenada entre modulos, roles y futuras reglas operativas."
    >
        <EntityFormCard
            :title="`Editar ${props.permiso.codigo}`"
            description="Ajusta el codigo o la descripcion del permiso preservando consistencia del catalogo."
        >
            <form class="space-y-6" @submit.prevent="submit">
                <div class="company-form-grid">
                    <div class="company-field">
                        <Label for="codigo" class="company-label">Codigo</Label>
                        <Input id="codigo" v-model="form.codigo" class="company-input" />
                        <InputError :message="form.errors.codigo" />
                    </div>

                    <div class="company-field">
                        <Label for="modulo" class="company-label">Modulo</Label>
                        <Input id="modulo" v-model="form.modulo" class="company-input" />
                        <InputError :message="form.errors.modulo" />
                    </div>

                    <div class="company-field md:col-span-2">
                        <Label for="descripcion" class="company-label">Descripcion</Label>
                        <textarea id="descripcion" v-model="form.descripcion" class="company-textarea" />
                        <InputError :message="form.errors.descripcion" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/permisos">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Actualizando...' : 'Actualizar permiso' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

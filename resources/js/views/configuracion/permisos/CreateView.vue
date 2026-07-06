<script setup lang="ts">
import EntityFormCard from '@/components/shared/EntityFormCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Permisos', href: '/configuracion/permisos' },
    { title: 'Crear', href: '/configuracion/permisos/create' },
];

const form = useForm({
    codigo: '',
    descripcion: '',
    modulo: 'configuracion',
});

const submit = () => {
    form.post('/configuracion/permisos');
};
</script>

<template>
    <Head title="Crear permiso" />
    <ModulePageLayout
        title="Crear permiso"
        :breadcrumbs="breadcrumbs"
        description="Centraliza permisos por modulo para preparar usuarios, roles y trazabilidad futura."
    >
        <EntityFormCard
            title="Nuevo permiso"
            description="Usa codigos consistentes para mantener control de acceso claro y escalable."
        >
            <form class="space-y-6" @submit.prevent="submit">
                <div class="company-form-grid">
                    <div class="company-field">
                        <Label for="codigo" class="company-label">Codigo</Label>
                        <Input id="codigo" v-model="form.codigo" class="company-input" placeholder="ventas.crear" />
                        <InputError :message="form.errors.codigo" />
                    </div>

                    <div class="company-field">
                        <Label for="modulo" class="company-label">Modulo</Label>
                        <Input id="modulo" v-model="form.modulo" class="company-input" placeholder="configuracion" />
                        <InputError :message="form.errors.modulo" />
                    </div>

                    <div class="company-field md:col-span-2">
                        <Label for="descripcion" class="company-label">Descripcion</Label>
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            class="company-textarea"
                            placeholder="Explica el alcance exacto de este permiso"
                        />
                        <InputError :message="form.errors.descripcion" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/permisos">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Guardando...' : 'Guardar permiso' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

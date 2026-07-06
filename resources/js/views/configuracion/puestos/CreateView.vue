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
    { title: 'Puestos', href: '/configuracion/puestos' },
    { title: 'Crear', href: '/configuracion/puestos/create' },
];

const form = useForm({
    nombre: '',
    descripcion: '',
});

const submit = () => {
    form.post('/configuracion/puestos');
};
</script>

<template>
    <Head title="Crear puesto" />
    <ModulePageLayout
        title="Crear puesto"
        :breadcrumbs="breadcrumbs"
        description="Define cargos y responsabilidades con una estructura clara para todo el sistema."
    >
        <EntityFormCard
            title="Nuevo puesto"
            description="Registra un cargo base para organizar personal, permisos operativos y futuras asignaciones."
        >
            <form class="space-y-6" @submit.prevent="submit">
                <div class="company-form-grid">
                    <div class="company-field">
                        <Label for="nombre" class="company-label">Nombre</Label>
                        <Input id="nombre" v-model="form.nombre" class="company-input" autocomplete="off" />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="company-field md:col-span-2">
                        <Label for="descripcion" class="company-label">Descripcion</Label>
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            class="company-textarea"
                            placeholder="Describe el alcance o responsabilidad principal del puesto"
                        />
                        <InputError :message="form.errors.descripcion" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/puestos">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Guardando...' : 'Guardar puesto' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

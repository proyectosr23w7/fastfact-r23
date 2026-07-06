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
    { title: 'Roles', href: '/configuracion/roles' },
    { title: 'Crear', href: '/configuracion/roles/create' },
];

const form = useForm({
    nombre: '',
    descripcion: '',
});

const submit = () => {
    form.post('/configuracion/roles');
};
</script>

<template>
    <Head title="Crear rol" />
    <ModulePageLayout
        title="Crear rol"
        :breadcrumbs="breadcrumbs"
        description="Construye perfiles operativos claros para administrar acceso, responsabilidades y crecimiento futuro."
    >
        <EntityFormCard
            title="Nuevo rol"
            description="Define un nombre comprensible para el negocio y una descripcion que facilite su administracion."
        >
            <form class="space-y-6" @submit.prevent="submit">
                <div class="company-form-grid">
                    <div class="company-field">
                        <Label for="nombre" class="company-label">Nombre</Label>
                        <Input id="nombre" v-model="form.nombre" class="company-input" placeholder="Administrador" />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="company-field md:col-span-2">
                        <Label for="descripcion" class="company-label">Descripcion</Label>
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            class="company-textarea"
                            placeholder="Resume el alcance de este rol dentro del sistema"
                        />
                        <InputError :message="form.errors.descripcion" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/roles">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Guardando...' : 'Guardar rol' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

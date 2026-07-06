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
    puesto: Puesto;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Puestos', href: '/configuracion/puestos' },
    { title: 'Editar', href: '#' },
];

const { props } = usePage<PageProps>();

const form = useForm({
    nombre: props.puesto.nombre,
    descripcion: props.puesto.descripcion ?? '',
});

const submit = () => {
    form.put(`/configuracion/puestos/${props.puesto.id}`);
};
</script>

<template>
    <Head :title="`Editar ${props.puesto.nombre}`" />
    <ModulePageLayout
        title="Editar puesto"
        :breadcrumbs="breadcrumbs"
        description="Mantiene la estructura organizacional alineada con el crecimiento operativo de la empresa."
    >
        <EntityFormCard
            :title="`Editar ${props.puesto.nombre}`"
            description="Actualiza el nombre o la descripcion del cargo sin mover la logica fuera del modulo."
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
                            placeholder="Detalle operativo del puesto"
                        />
                        <InputError :message="form.errors.descripcion" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <Button as-child type="button" variant="outline" class="company-action-secondary">
                        <Link href="/configuracion/puestos">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="company-action-primary">
                        {{ form.processing ? 'Actualizando...' : 'Actualizar puesto' }}
                    </Button>
                </div>
            </form>
        </EntityFormCard>
    </ModulePageLayout>
</template>

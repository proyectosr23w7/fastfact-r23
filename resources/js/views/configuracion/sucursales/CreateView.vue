<script setup lang="ts">
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Sucursales', href: '/configuracion/sucursales' },
    { title: 'Crear', href: '/configuracion/sucursales/create' },
];
</script>

<template>
    <Head title="Crear sucursal" />

    <ModulePageLayout title="Crear sucursal" :breadcrumbs="breadcrumbs">
        <div class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
            Vista base para el formulario de creacion de sucursales.
        </div>
    </ModulePageLayout>
</template>

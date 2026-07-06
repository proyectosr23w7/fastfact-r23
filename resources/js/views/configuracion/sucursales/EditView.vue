<script setup lang="ts">
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem, SharedData, Sucursal } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';

interface EditSucursalPageProps extends SharedData {
    sucursal: Sucursal;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Sucursales', href: '/configuracion/sucursales' },
    { title: 'Editar', href: '#' },
];

const { props } = usePage<EditSucursalPageProps>();
</script>

<template>
    <Head :title="`Editar ${props.sucursal.nombre}`" />

    <ModulePageLayout title="Editar sucursal" :breadcrumbs="breadcrumbs">
        <div class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
            Vista base para editar la sucursal {{ props.sucursal.nombre }}.
        </div>
    </ModulePageLayout>
</template>

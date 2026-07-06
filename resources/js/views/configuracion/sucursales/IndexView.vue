<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import type { BreadcrumbItem, SharedData, Sucursal } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CirclePlus, Pencil, Trash } from 'lucide-vue-next';
import { computed } from 'vue';

interface SucursalPageProps extends SharedData {
    sucursales: Sucursal[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Sucursales', href: '/configuracion/sucursales' },
];

const { props } = usePage<SucursalPageProps>();
const sucursales = computed(() => props.sucursales);

const deleteSucursal = (id: number) => {
    if (!window.confirm('Estas seguro de eliminar esta sucursal?')) {
        return;
    }

    router.delete(`/configuracion/sucursales/${id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Sucursales" />

    <ModulePageLayout title="Sucursales" :breadcrumbs="breadcrumbs">
        <div class="mb-4 flex justify-end">
            <Button as-child size="sm">
                <Link href="/configuracion/sucursales/create">
                    <CirclePlus class="mr-2 h-4 w-4" />
                    Crear sucursal
                </Link>
            </Button>
        </div>

        <Table>
            <TableCaption>Sucursales registradas en la instalacion.</TableCaption>

            <TableHeader>
                <TableRow>
                    <TableHead>Codigo</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Global</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead class="text-center">Acciones</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow v-for="sucursal in sucursales" :key="sucursal.id">
                    <TableCell>{{ sucursal.codigo_sucursal }}</TableCell>
                    <TableCell>{{ sucursal.nombre }}</TableCell>
                    <TableCell>{{ sucursal.es_global ? 'Si' : 'No' }}</TableCell>
                    <TableCell>{{ sucursal.activo ? 'Activo' : 'Inactivo' }}</TableCell>
                    <TableCell class="flex justify-center gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="`/configuracion/sucursales/${sucursal.id}/edit`">
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </Button>

                        <Button size="sm" variant="destructive" @click="deleteSucursal(sucursal.id)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </ModulePageLayout>
</template>

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
import type { BreadcrumbItem, Puesto, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CirclePlus, Pencil, Trash } from 'lucide-vue-next';
import { computed } from 'vue';

interface PageProps extends SharedData {
    puestos: Puesto[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Puestos', href: '/configuracion/puestos' },
];

const { props } = usePage<PageProps>();
const puestos = computed(() => props.puestos);

const deleteItem = (id: number) => {
    if (!window.confirm('Estas seguro de eliminar este puesto?')) return;

    router.delete(`/configuracion/puestos/${id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Puestos" />

    <ModulePageLayout title="Puestos" :breadcrumbs="breadcrumbs">
        <div class="mb-4 flex justify-end">
            <Button as-child size="sm">
                <Link href="/configuracion/puestos/create">
                    <CirclePlus class="mr-2 h-4 w-4" />
                    Crear puesto
                </Link>
            </Button>
        </div>

        <Table>
            <TableCaption>Puestos disponibles para estructura organizacional.</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Descripcion</TableHead>
                    <TableHead>Personal asignado</TableHead>
                    <TableHead class="text-center">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="puesto in puestos" :key="puesto.id">
                    <TableCell>{{ puesto.nombre }}</TableCell>
                    <TableCell>{{ puesto.descripcion || 'Sin descripcion' }}</TableCell>
                    <TableCell>{{ puesto.personal_count ?? 0 }}</TableCell>
                    <TableCell class="flex justify-center gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="`/configuracion/puestos/${puesto.id}/edit`">
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button size="sm" variant="destructive" @click="deleteItem(puesto.id)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </ModulePageLayout>
</template>

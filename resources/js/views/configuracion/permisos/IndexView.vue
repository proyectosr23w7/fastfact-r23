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
import type { BreadcrumbItem, Permiso, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CirclePlus, Pencil, Trash } from 'lucide-vue-next';
import { computed } from 'vue';

interface PageProps extends SharedData {
    permisos: Permiso[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Permisos', href: '/configuracion/permisos' },
];

const { props } = usePage<PageProps>();
const permisos = computed(() => props.permisos);

const deleteItem = (id: number) => {
    if (!window.confirm('Estas seguro de eliminar este permiso?')) return;

    router.delete(`/configuracion/permisos/${id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Permisos" />
    <ModulePageLayout title="Permisos" :breadcrumbs="breadcrumbs">
        <div class="mb-4 flex justify-end">
            <Button as-child size="sm">
                <Link href="/configuracion/permisos/create">
                    <CirclePlus class="mr-2 h-4 w-4" />
                    Crear permiso
                </Link>
            </Button>
        </div>

        <Table>
            <TableCaption>Catalogo base de permisos del sistema.</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Codigo</TableHead>
                    <TableHead>Descripcion</TableHead>
                    <TableHead>Modulo</TableHead>
                    <TableHead>Roles asociados</TableHead>
                    <TableHead class="text-center">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="permiso in permisos" :key="permiso.id">
                    <TableCell>{{ permiso.codigo }}</TableCell>
                    <TableCell>{{ permiso.descripcion }}</TableCell>
                    <TableCell>{{ permiso.modulo }}</TableCell>
                    <TableCell>{{ permiso.roles_count ?? 0 }}</TableCell>
                    <TableCell class="flex justify-center gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="`/configuracion/permisos/${permiso.id}/edit`">
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button size="sm" variant="destructive" @click="deleteItem(permiso.id)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </ModulePageLayout>
</template>

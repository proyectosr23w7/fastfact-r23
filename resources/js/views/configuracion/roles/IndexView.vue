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
import type { BreadcrumbItem, Role, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CirclePlus, Pencil, Trash } from 'lucide-vue-next';
import { computed } from 'vue';

interface PageProps extends SharedData {
    roles: Role[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Roles', href: '/configuracion/roles' },
];

const { props } = usePage<PageProps>();
const roles = computed(() => props.roles);

const deleteItem = (id: number) => {
    if (!window.confirm('Estas seguro de eliminar este rol?')) return;

    router.delete(`/configuracion/roles/${id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Roles" />
    <ModulePageLayout title="Roles" :breadcrumbs="breadcrumbs">
        <div class="mb-4 flex justify-end">
            <Button as-child size="sm">
                <Link href="/configuracion/roles/create">
                    <CirclePlus class="mr-2 h-4 w-4" />
                    Crear rol
                </Link>
            </Button>
        </div>

        <Table>
            <TableCaption>Roles base para seguridad y permisos.</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Descripcion</TableHead>
                    <TableHead>Permisos</TableHead>
                    <TableHead class="text-center">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="role in roles" :key="role.id">
                    <TableCell>{{ role.nombre }}</TableCell>
                    <TableCell>{{ role.descripcion || 'Sin descripcion' }}</TableCell>
                    <TableCell>{{ role.permisos_count ?? 0 }}</TableCell>
                    <TableCell class="flex justify-center gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="`/configuracion/roles/${role.id}/edit`">
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button size="sm" variant="destructive" @click="deleteItem(role.id)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </ModulePageLayout>
</template>

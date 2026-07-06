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
import type { BreadcrumbItem, Personal, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CirclePlus, Pencil, Trash } from 'lucide-vue-next';
import { computed } from 'vue';

interface PageProps extends SharedData {
    personal: Personal[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configuracion', href: '/dashboard' },
    { title: 'Personal', href: '/configuracion/personal' },
];

const { props } = usePage<PageProps>();
const personal = computed(() => props.personal);

const deleteItem = (id: number) => {
    if (!window.confirm('Estas seguro de eliminar este registro?')) return;

    router.delete(`/configuracion/personal/${id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Personal" />

    <ModulePageLayout title="Personal" :breadcrumbs="breadcrumbs">
        <div class="mb-4 flex justify-end">
            <Button as-child size="sm">
                <Link href="/configuracion/personal/create">
                    <CirclePlus class="mr-2 h-4 w-4" />
                    Registrar personal
                </Link>
            </Button>
        </div>

        <Table>
            <TableCaption>Personal registrado en la empresa.</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Documento</TableHead>
                    <TableHead>Puesto</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead class="text-center">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="item in personal" :key="item.id">
                    <TableCell>{{ item.nombre_completo }}</TableCell>
                    <TableCell>{{ item.numero_documento }}</TableCell>
                    <TableCell>{{ item.puesto?.nombre || 'Sin puesto' }}</TableCell>
                    <TableCell>{{ item.activo ? 'Si' : 'No' }}</TableCell>
                    <TableCell class="flex justify-center gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link :href="`/configuracion/personal/${item.id}/edit`">
                                <Pencil class="h-4 w-4" />
                            </Link>
                        </Button>
                        <Button size="sm" variant="destructive" @click="deleteItem(item.id)">
                            <Trash class="h-4 w-4" />
                        </Button>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </ModulePageLayout>
</template>

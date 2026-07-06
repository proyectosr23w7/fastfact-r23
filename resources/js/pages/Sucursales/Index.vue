<script setup lang="ts">
    import AppLayout from '@/layouts/AppLayout.vue';
    import { Sucursal, type BreadcrumbItem, type SharedData } from "@/types";
    import { Head, usePage, Link, router} from '@inertiajs/vue3';
    import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
    import { Button } from "@/components/ui/button";
    import { Pencil, Trash, CirclePlus } from "lucide-vue-next";
    import { computed } from 'vue';

    interface SucursalPageProps extends SharedData{
        sucursales: Sucursal[];
    }

    const {props} = usePage<SucursalPageProps>();
    const sucursales = computed(()=>props.sucursales);
    //Breadcrumbs
    const breadcrumbs : BreadcrumbItem[] = [{title: 'Sucursales', href: '/sucursales'}];
    //Metodo eliminar
    const deleteSucursal = async(id: number)=> {
        if(!window.confirm('Estas seguro de eliminar esta sucursal?')) return;

        router.delete(`/sucursales/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                router.visit('/sucursales', { replace: true});
            },
            onError: (errors) => {
                console.error('Error al eliminar la sucursal:', errors);
            }
        });
    }
</script>
<template>
    <Head title="Sucursales" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex">
                <Button as-child size="sm" class="bg-indigo-500 text-white hover:bg-indigo-700">
                    <Link href="/sucursales/create"><CirclePlus /> Crear</Link>
                </Button>
            </div>

            <div class="relative min-h-[100vh] flex-1 rounded-x border border-sidebar-border/70 dark:boreder-sideber-border md:min-h-min">
                <Table>
                    <TableCaption> Sucursal List.</TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Codigo Sucursal</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Global</TableHead>
                            <TableHead class="text-center">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="sucursal in sucursales" :key="sucursal.id">
                            <TableCell class="font-medium">{{ sucursal.codigo_sucursal }}</TableCell>
                            <TableCell class="font-medium">{{ sucursal.nombre }}</TableCell>
                            <TableCell class="font-medium">{{ sucursal.es_global }}</TableCell>
                            <TableCell class="flex justify-center gap-2">
                                <Button as-child size="sm" class="bg-blue-500 text-white hover:bg-blue-700">
                                    <Link :href="`/sucursales/${sucursal.id}/edit`">
                                        <Pencil />
                                    </Link>
                                </Button>
                                <Button size="sm" class="bg-rose-500 text-white hover:bg-rose-700" @click="deleteSucursal(sucursal.id)">
                                    <Trash />
                                </Button>
                                
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

            </div>
        </div>
    </AppLayout>
    
</template>
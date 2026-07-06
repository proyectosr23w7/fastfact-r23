<script setup lang="ts">
    import AppLayout from '@/layouts/AppLayout.vue';
    import { Sucursal, type BreadcrumbItem, type SharedData } from "@/types";
    import { Head, usePage, Link, router} from '@inertiajs/vue3';
    import { ref } from "vue";
    import { Button } from "@/components/ui/button";
    import { Input } from "@/components/ui/input";
    import { Label } from "@/components/ui/label";
    
    
    import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
    import { Pencil, Trash, CirclePlus } from "lucide-vue-next";
    import { computed } from 'vue';

    interface SucursalPageProps extends SharedData{
        sucursales: Sucursal[];
    }

    const {props} = usePage<SucursalPageProps>();
    const sucursales = computed(()=>props.sucursales);
    //Breadcrumbs
    const breadcrumbs : BreadcrumbItem[] = [{title: 'Sucursales', href: '/sucursales'},{title: 'Crear', href: '#'}];
    //Metodo eliminar
    
</script>
<template>
    <Head title="Crear Sucursal" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-2xl font-bold">Crear</h1>
            <form action="">
                <label>Codigo Sucursal</label>
                <input value="codigo_sucursal"/>
                <label>Nombre Sucursal</label>
                <input value="codigo_sucursal"/>
                <label>Global</label>
                <input value="codigo_sucursal"/>
                
            </form>
        </div>
    </AppLayout>
    
</template>
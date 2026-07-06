<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { useCuisStore } from '@/src/stores/facturacion/cuisStore';
import { computed, onMounted, ref } from 'vue';

const store = useCuisStore();
const dialogOpen = ref(false);
const siatProfile = computed(() => (store.state.meta.siat as Record<string, unknown> | undefined) ?? {});
const siatModules = computed(() => (siatProfile.value.modules as Record<string, unknown>[] | undefined) ?? []);

const submit = async () => {
    const ok = await store.save();
    if (ok) dialogOpen.value = false;
};

onMounted(store.load);
</script>

<template>
    <ModulePageLayout
        title="CUIS"
        description="Gestion historica y operativa de CUIS por sucursal y punto de venta."
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'CUIS', href: '/facturacion/cuis' },
        ]"
    >
        <div class="company-panel p-5">
            <div class="mb-5 rounded-2xl border border-border/60 bg-muted/35 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Modulo SIAT</div>
                        <div class="mt-1 text-base font-semibold">
                            {{ (siatProfile.environment as Record<string, unknown> | undefined)?.label || 'No definido' }}
                        </div>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        Endpoint codigos:
                        {{
                            siatModules.find((item) => String(item.key) === 'codigos')?.configured
                                ? 'configurado'
                                : 'pendiente de configuracion'
                        }}
                    </div>
                </div>
                <div class="mt-3 break-all text-xs text-muted-foreground">
                    {{ siatModules.find((item) => String(item.key) === 'codigos')?.wsdl || 'Sin WSDL configurado' }}
                </div>
            </div>

            <div class="mb-5 flex justify-between gap-3">
                <p class="text-sm text-muted-foreground">El CUIS se solicita directamente al SIAT y solo se genera si no existe uno vigente para el ambiente activo.</p>
                <Button class="company-action-primary" @click="dialogOpen = true">Solicitar CUIS</Button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/55 text-left">
                        <tr>
                            <th class="px-4 py-3">Codigo</th>
                            <th class="px-4 py-3">Sucursal</th>
                            <th class="px-4 py-3">Punto de venta</th>
                            <th class="px-4 py-3">Ambiente</th>
                            <th class="px-4 py-3">Vigencia</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in store.state.items" :key="String(item.id)" class="border-t border-border/60">
                            <td class="px-4 py-3 font-medium">{{ item.codigo }}</td>
                            <td class="px-4 py-3">{{ item.sucursal?.nombre }}</td>
                            <td class="px-4 py-3">{{ item.punto_venta?.nombre }}</td>
                            <td class="px-4 py-3">{{ item.ambiente_facturacion }}</td>
                            <td class="px-4 py-3">{{ item.fecha_vigencia || '-' }}</td>
                            <td class="px-4 py-3">{{ item.estado ? 'Vigente' : 'Inactivo' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-2xl border-border/80 p-0">
                <div class="company-panel shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader>
                            <DialogTitle class="text-left text-lg font-semibold text-white">Solicitar CUIS al SIAT</DialogTitle>
                            <DialogDescription class="text-left text-emerald-50/80">
                                El codigo y la vigencia seran devueltos por SIAT para el ambiente activo.
                            </DialogDescription>
                        </DialogHeader>
                    </div>
                    <form class="space-y-5 p-5 md:p-6" @submit.prevent="submit">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="company-field">
                                <Label class="company-label">Sucursal</Label>
                                <select v-model="store.state.form.sucursal_id" class="company-select">
                                    <option value="">Seleccione una sucursal</option>
                                    <option v-for="sucursal in (store.state.meta.sucursales ?? [])" :key="String(sucursal.id)" :value="String(sucursal.id)">
                                        {{ sucursal.nombre }}
                                    </option>
                                </select>
                                <InputError :message="store.state.errors.sucursal_id?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label class="company-label">Punto de venta</Label>
                                <select v-model="store.state.form.punto_venta_id" class="company-select">
                                    <option value="">Seleccione un punto de venta</option>
                                    <option v-for="punto in (store.state.meta.puntos_venta ?? [])" :key="String(punto.id)" :value="String(punto.id)">
                                        {{ punto.nombre }}
                                    </option>
                                </select>
                                <InputError :message="store.state.errors.punto_venta_id?.[0]" />
                            </div>
                        </div>
                        <div class="rounded-2xl border border-border/60 bg-muted/35 p-4 text-sm text-muted-foreground">
                            Si ya existe un CUIS vigente para este punto de venta en
                            {{ (siatProfile.environment as Record<string, unknown> | undefined)?.label || 'el ambiente activo' }},
                            el sistema bloqueara la solicitud para evitar duplicados.
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button type="button" variant="outline" class="company-action-secondary" @click="dialogOpen = false">Cancelar</Button>
                            <Button type="submit" class="company-action-primary" :disabled="store.state.saving">
                                {{ store.state.saving ? 'Solicitando...' : 'Solicitar CUIS' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

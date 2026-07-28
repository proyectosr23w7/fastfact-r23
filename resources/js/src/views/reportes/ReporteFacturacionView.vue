<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { reporteService } from '@/src/services/reporteService';
import {
    AlertCircle,
    BarChart3,
    FileSpreadsheet,
    FileText,
    LoaderCircle,
    RefreshCcw,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Item = Record<string, any>;

const today = new Date();
const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
const formatInputDate = (date: Date) => date.toISOString().slice(0, 10);

const filters = reactive({
    fecha_desde: formatInputDate(firstDay),
    fecha_hasta: formatInputDate(today),
    sucursal_id: '',
    punto_venta_id: '',
    user_id: '',
    estado_factura: '',
    codigo_metodo_pago: '',
});

const loading = ref(false);
const error = ref('');
const report = ref<Item | null>(null);

const meta = computed<Item>(() => (report.value?.meta as Item | undefined) ?? {});
const resumen = computed<Item>(() => (report.value?.resumen as Item | undefined) ?? {});
const sucursales = computed<Item[]>(() => (meta.value.sucursales as Item[] | undefined) ?? []);
const puntosVenta = computed<Item[]>(() => (meta.value.puntos_venta as Item[] | undefined) ?? []);
const usuarios = computed<Item[]>(() => (meta.value.usuarios as Item[] | undefined) ?? []);
const estados = computed<Item[]>(() => (meta.value.estados_factura as Item[] | undefined) ?? []);
const metodosPago = computed<Item[]>(() => (meta.value.metodos_pago as Item[] | undefined) ?? []);
const puntosVentaFiltrados = computed(() =>
    puntosVenta.value.filter((punto) =>
        filters.sucursal_id ? String(punto.sucursal_id) === String(filters.sucursal_id) : true,
    ),
);

const money = (value: unknown) =>
    new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB',
        minimumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value: unknown, decimals = 0) =>
    new Intl.NumberFormat('es-BO', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(Number(value ?? 0));

const loadReport = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await reporteService.facturacion(filters);
        report.value = response.data;
        const returnedFilters = (response.data?.meta as Item | undefined)?.filtros as Item | undefined;
        if (returnedFilters) {
            filters.fecha_desde = String(returnedFilters.fecha_desde ?? filters.fecha_desde);
            filters.fecha_hasta = String(returnedFilters.fecha_hasta ?? filters.fecha_hasta);
        }
    } catch (exception) {
        error.value = exception instanceof Error ? exception.message : 'No se pudo cargar el reporte.';
    } finally {
        loading.value = false;
    }
};

const clearFilters = () => {
    filters.fecha_desde = formatInputDate(firstDay);
    filters.fecha_hasta = formatInputDate(today);
    filters.sucursal_id = '';
    filters.punto_venta_id = '';
    filters.user_id = '';
    filters.estado_factura = '';
    filters.codigo_metodo_pago = '';
    void loadReport();
};

const downloadReport = (format: 'excel' | 'pdf') => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, String(value));
        }
    });

    window.open(
        `/api/reportes/facturacion/${format}?${params.toString()}`,
        '_blank',
        'noopener,noreferrer',
    );
};

watch(
    () => filters.sucursal_id,
    () => {
        const selected = puntosVenta.value.find((punto) => String(punto.id) === String(filters.punto_venta_id));
        if (selected && filters.sucursal_id && String(selected.sucursal_id) !== String(filters.sucursal_id)) {
            filters.punto_venta_id = '';
        }
    },
);

onMounted(loadReport);
</script>

<template>
    <ModulePageLayout
        title="Reportes de facturación"
        description="Indicadores generados desde facturación directa."
        :breadcrumbs="[
            { title: 'Reportes', href: '/reportes/facturacion' },
            { title: 'Facturación', href: '/reportes/facturacion' },
        ]"
        compact
    >
        <div class="space-y-4">
            <div v-if="error" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <AlertCircle class="mt-0.5 size-4 shrink-0" />
                <span>{{ error }}</span>
            </div>

            <section class="company-panel p-4">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                    <label class="company-field">
                        <span class="company-label">Desde</span>
                        <input v-model="filters.fecha_desde" type="date" class="company-input" />
                    </label>
                    <label class="company-field">
                        <span class="company-label">Hasta</span>
                        <input v-model="filters.fecha_hasta" type="date" class="company-input" />
                    </label>
                    <label class="company-field">
                        <span class="company-label">Sucursal</span>
                        <select v-model="filters.sucursal_id" class="company-input">
                            <option value="">Todas</option>
                            <option v-for="sucursal in sucursales" :key="sucursal.id" :value="String(sucursal.id)">
                                {{ sucursal.codigo }} - {{ sucursal.nombre }}
                            </option>
                        </select>
                    </label>
                    <label class="company-field">
                        <span class="company-label">Punto de venta</span>
                        <select v-model="filters.punto_venta_id" class="company-input">
                            <option value="">Todos</option>
                            <option v-for="punto in puntosVentaFiltrados" :key="punto.id" :value="String(punto.id)">
                                {{ punto.codigo }} - {{ punto.nombre }}
                            </option>
                        </select>
                    </label>
                    <label class="company-field">
                        <span class="company-label">Usuario</span>
                        <select v-model="filters.user_id" class="company-input">
                            <option value="">Todos</option>
                            <option v-for="usuario in usuarios" :key="usuario.id" :value="String(usuario.id)">
                                {{ usuario.name }}
                            </option>
                        </select>
                    </label>
                    <label class="company-field">
                        <span class="company-label">Estado</span>
                        <select v-model="filters.estado_factura" class="company-input">
                            <option value="">Todos</option>
                            <option v-for="estado in estados" :key="estado.value" :value="estado.value">
                                {{ estado.label }}
                            </option>
                        </select>
                    </label>
                    <label class="company-field">
                        <span class="company-label">Método</span>
                        <select v-model="filters.codigo_metodo_pago" class="company-input">
                            <option value="">Todos</option>
                            <option v-for="metodo in metodosPago" :key="metodo.codigo_clasificador" :value="String(metodo.codigo_clasificador)">
                                {{ metodo.codigo_clasificador }} - {{ metodo.descripcion }}
                            </option>
                        </select>
                    </label>
                </div>
                <div class="mt-4 flex flex-wrap justify-end gap-2">
                    <Button variant="outline" type="button" @click="clearFilters">Limpiar</Button>
                    <Button variant="outline" type="button" :disabled="loading" @click="downloadReport('excel')">
                        <FileSpreadsheet class="mr-2 size-4" />
                        Excel
                    </Button>
                    <Button variant="outline" type="button" :disabled="loading" @click="downloadReport('pdf')">
                        <FileText class="mr-2 size-4" />
                        PDF
                    </Button>
                    <Button class="company-action-primary" type="button" :disabled="loading" @click="loadReport">
                        <LoaderCircle v-if="loading" class="mr-2 size-4 animate-spin" />
                        <RefreshCcw v-else class="mr-2 size-4" />
                        Actualizar
                    </Button>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article class="company-panel p-4">
                    <p class="text-sm text-[#536158]">Total operativo</p>
                    <p class="mt-2 text-2xl font-black text-[#168447]">{{ money(resumen.monto_total) }}</p>
                    <p class="text-xs text-[#748078]">{{ number(resumen.facturas_operativas) }} facturas operativas</p>
                </article>
                <article class="company-panel p-4">
                    <p class="text-sm text-[#536158]">Facturas registradas</p>
                    <p class="mt-2 text-2xl font-black text-[#202a24]">{{ number(resumen.facturas_total) }}</p>
                    <p class="text-xs text-[#748078]">{{ number(resumen.anuladas) }} anuladas</p>
                </article>
                <article class="company-panel p-4">
                    <p class="text-sm text-[#536158]">Monto sujeto a IVA</p>
                    <p class="mt-2 text-2xl font-black text-[#202a24]">{{ money(resumen.monto_sujeto_iva) }}</p>
                    <p class="text-xs text-[#748078]">Base fiscal del periodo</p>
                </article>
                <article class="company-panel p-4">
                    <p class="text-sm text-[#536158]">Pendientes / observadas</p>
                    <p class="mt-2 text-2xl font-black text-[#202a24]">
                        {{ number(resumen.pendientes) }} / {{ number(resumen.observadas) }}
                    </p>
                    <p class="text-xs text-[#748078]">Seguimiento SIAT</p>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <div class="company-panel overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-[#dfe7e2] px-4 py-3">
                        <BarChart3 class="size-4 text-[#168447]" />
                        <h2 class="text-sm font-black text-[#202a24]">Métodos de pago</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="bg-white text-left text-xs text-[#536158]">
                                <tr>
                                    <th class="px-4 py-3">Método</th>
                                    <th class="px-4 py-3 text-right">Facturas</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in (report?.por_metodo_pago ?? [])" :key="item.codigo" class="border-t border-[#e4ebe6]">
                                    <td class="px-4 py-3 font-semibold">{{ item.descripcion }}</td>
                                    <td class="px-4 py-3 text-right">{{ number(item.cantidad) }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ money(item.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="company-panel overflow-hidden">
                    <div class="border-b border-[#dfe7e2] px-4 py-3">
                        <h2 class="text-sm font-black text-[#202a24]">Usuarios</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead class="bg-white text-left text-xs text-[#536158]">
                                <tr>
                                    <th class="px-4 py-3">Usuario</th>
                                    <th class="px-4 py-3 text-right">Facturas</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in (report?.por_usuario ?? [])" :key="item.user_id ?? item.usuario" class="border-t border-[#e4ebe6]">
                                    <td class="px-4 py-3 font-semibold">{{ item.usuario }}</td>
                                    <td class="px-4 py-3 text-right">{{ number(item.cantidad) }}</td>
                                    <td class="px-4 py-3 text-right font-bold">{{ money(item.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="company-panel overflow-hidden">
                <div class="border-b border-[#dfe7e2] px-4 py-3">
                    <h2 class="text-sm font-black text-[#202a24]">Productos facturados</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-white text-left text-xs text-[#536158]">
                            <tr>
                                <th class="px-4 py-3">Código</th>
                                <th class="px-4 py-3">Producto</th>
                                <th class="px-4 py-3 text-right">Cantidad</th>
                                <th class="px-4 py-3 text-right">Precio prom.</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in (report?.por_producto ?? [])" :key="`${item.codigo_producto}-${item.descripcion}`" class="border-t border-[#e4ebe6]">
                                <td class="px-4 py-3 font-mono text-xs">{{ item.codigo_producto }}</td>
                                <td class="px-4 py-3 font-semibold">{{ item.descripcion }}</td>
                                <td class="px-4 py-3 text-right">{{ number(item.cantidad, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(item.precio_promedio) }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ money(item.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="company-panel overflow-hidden">
                <div class="border-b border-[#dfe7e2] px-4 py-3">
                    <h2 class="text-sm font-black text-[#202a24]">Facturas recientes del periodo</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead class="bg-white text-left text-xs text-[#536158]">
                            <tr>
                                <th class="px-4 py-3">Nro.</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Sucursal / PV</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="factura in (report?.facturas_recientes ?? [])" :key="factura.id" class="border-t border-[#e4ebe6]">
                                <td class="px-4 py-3 font-bold">{{ factura.numero_factura }}</td>
                                <td class="px-4 py-3 text-[#536158]">{{ factura.fecha_emision }}</td>
                                <td class="px-4 py-3">{{ factura.cliente }}</td>
                                <td class="px-4 py-3">{{ factura.usuario }}</td>
                                <td class="px-4 py-3">{{ factura.sucursal }} / {{ factura.punto_venta }}</td>
                                <td class="px-4 py-3">{{ factura.estado_factura }}</td>
                                <td class="px-4 py-3 text-right font-bold">{{ money(factura.monto_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </ModulePageLayout>
</template>

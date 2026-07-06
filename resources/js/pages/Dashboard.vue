<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Clock3,
    FileText,
    PackageOpen,
    RadioTower,
    ReceiptText,
    Search,
    ShieldCheck,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

type FacturacionSemana = { fecha: string; total: number };
type FacturaReciente = {
    id: number;
    numero: string | number;
    fecha: string | null;
    cliente: string;
    total: number;
    estado: string | null;
};

const props = defineProps<{
    dashboard: {
        kpis: {
            facturacion_hoy: number;
            variacion_facturacion: number | null;
            facturas_hoy: number;
            variacion_facturas: number;
        };
        facturacion_semana: FacturacionSemana[];
        facturas_recientes: FacturaReciente[];
        siat: {
            habilitado: boolean;
            conectado: boolean;
            estado: string;
            ambiente: string | null;
            ultima_sincronizacion: string | null;
            vigencia_cufd: string | null;
        } | null;
    };
}>();

const page = usePage<SharedData>();
const ahora = new Date();
const nombreUsuario = computed(
    () => page.props.auth.user?.name?.split(' ')[0] || 'usuario',
);
const saludo = computed(() => {
    const hora = ahora.getHours();
    if (hora < 12) return 'Buenos dias';
    if (hora < 19) return 'Buenas tardes';
    return 'Buenas noches';
});
const fechaActual = new Intl.DateTimeFormat('es-BO', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(ahora);

const moneda = (value: number | null) =>
    value === null
        ? 'No disponible'
        : new Intl.NumberFormat('es-BO', {
              style: 'currency',
              currency: 'BOB',
              maximumFractionDigits: 2,
          })
              .format(value)
              .replace('BOB', 'Bs');
const numero = (value: number) =>
    new Intl.NumberFormat('es-BO', { maximumFractionDigits: 2 }).format(value);
const fechaHora = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('es-BO', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : 'Sin fecha';
const fechaCorta = (value: string) =>
    new Intl.DateTimeFormat('es-BO', {
        weekday: 'short',
        day: 'numeric',
    }).format(new Date(`${value}T12:00:00`));

const maxFacturacion = computed(() =>
    Math.max(...props.dashboard.facturacion_semana.map((item) => item.total), 1),
);
const puntos = computed(() =>
    props.dashboard.facturacion_semana.map((item, index) => ({
        x: 40 + index * (620 / 6),
        y: 205 - (item.total / maxFacturacion.value) * 160,
        ...item,
    })),
);
const linea = computed(() =>
    puntos.value.map((punto) => `${punto.x},${punto.y}`).join(' '),
);
const area = computed(() =>
    puntos.value.length
        ? `M ${puntos.value.map((punto) => `${punto.x} ${punto.y}`).join(' L ')} L 660 205 L 40 205 Z`
        : '',
);
const hayFacturacionSemana = computed(() =>
    props.dashboard.facturacion_semana.some((item) => item.total > 0),
);
const nivelesGrafico = computed(() =>
    [1, 0.75, 0.5, 0.25, 0].map((factor) => maxFacturacion.value * factor),
);
const siatOperativo = computed(() => Boolean(props.dashboard.siat?.conectado));
const ambienteFacturacion = computed(
    () => props.dashboard.siat?.ambiente || 'No configurado',
);
const ultimaSincronizacion = computed(() =>
    fechaHora(props.dashboard.siat?.ultima_sincronizacion ?? null),
);
const vigenciaCufd = computed(() =>
    fechaHora(props.dashboard.siat?.vigencia_cufd ?? null),
);

const estadoFacturaClase = (estado: string | null) => {
    if (!estado) return 'bg-muted text-muted-foreground';
    if (['emitida', 'validada'].includes(estado))
        return 'bg-[#168447] text-white';
    if (['rechazada', 'anulada'].includes(estado))
        return 'bg-red-50 text-red-700 dark:bg-red-950/35 dark:text-red-300';
    if (estado === 'observada')
        return 'bg-amber-50 text-amber-700 dark:bg-amber-950/35 dark:text-amber-300';
    return 'bg-[#EAF7EF] text-[#116b39]';
};
</script>

<template>
    <Head title="Centro FastFact R23W7" />

    <AppLayout>
        <main class="min-h-0 flex-1 bg-[#F4F8F5] text-[#071710] dark:bg-[#071710] dark:text-white">
            <section class="border-b border-white/10 bg-[#071710] px-4 py-4 text-white sm:px-6 lg:px-7">
                <div class="mx-auto flex max-w-[1500px] flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#168447] to-[#25d06f] font-black text-[#04140c] shadow-sm">
                            R23
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-white/55">
                                TechDev servicios R23W7
                            </p>
                            <h1 class="text-2xl font-black tracking-tight sm:text-[30px]">
                                Centro FastFact R23W7
                            </h1>
                            <p class="mt-1 text-sm text-white/68">
                                {{ saludo }}, {{ nombreUsuario }}. {{ fechaActual }}.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <Link href="/facturacion/facturas" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-white/12 bg-white/6 px-4 text-sm font-bold text-white transition hover:bg-white/10">
                            <Search class="size-4" /> Buscar factura
                        </Link>
                        <Link href="/facturacion/sincronizaciones-siat" class="inline-flex min-h-10 items-center gap-2 rounded-md border border-white/12 bg-white/6 px-4 text-sm font-bold text-white transition hover:bg-white/10">
                            <RadioTower class="size-4" /> Sincronizar SIAT
                        </Link>
                        <Link href="/facturacion/facturas/nueva" class="inline-flex min-h-10 items-center gap-2 rounded-md bg-[#168447] px-4 text-sm font-black text-white shadow-sm transition hover:bg-[#116f3b]">
                            <Zap class="size-4" /> Emitir en 3 pasos
                        </Link>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-[1500px] space-y-5 px-4 py-5 sm:px-6 lg:px-7">
                        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(260px,0.55fr)_minmax(260px,0.55fr)]">
                            <article class="rounded-lg border border-[#0f3321] bg-gradient-to-br from-[#071710] via-[#123f27] to-[#168447] p-5 text-white shadow-[0_18px_45px_-34px_rgba(7,23,16,0.9)]">
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-white/58">Pulso R23W7</p>
                                <h2 class="mt-3 max-w-2xl text-2xl font-black tracking-tight">
                                    Facturacion agil con control fiscal siempre visible
                                </h2>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/75">
                                    Venta, CUF, PDF, impresion y envio salen desde el mismo tablero operativo, sin perder el estado SIAT de vista.
                                </p>
                            </article>
                            <article class="rounded-lg border border-[#d6e3dc] bg-white p-5 shadow-[0_14px_35px_-32px_rgba(7,23,16,0.75)] dark:border-white/10 dark:bg-white/6">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-[#657069] dark:text-white/48">Modo caja</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <Clock3 class="size-8 text-[#168447]" />
                                    <p class="text-2xl font-black">38 segundos</p>
                                </div>
                                <p class="mt-2 text-sm text-[#657069] dark:text-white/58">
                                    Flujo objetivo para cliente, detalle, cobro y emision.
                                </p>
                            </article>
                            <article class="rounded-lg border border-[#d6e3dc] bg-white p-5 shadow-[0_14px_35px_-32px_rgba(7,23,16,0.75)] dark:border-white/10 dark:bg-white/6">
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-[#657069] dark:text-white/48">Identidad</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <ShieldCheck class="size-8 text-[#168447]" />
                                    <p class="text-2xl font-black">TechDev servicios</p>
                                </div>
                                <p class="mt-2 text-sm text-[#657069] dark:text-white/58">
                                    Entorno sobrio, tecnico y propio para FastFact R23W7.
                                </p>
                            </article>
                        </section>

                        <section aria-label="Indicadores principales" class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                            <article class="dashboard-card flex min-h-32 items-start justify-between gap-4 p-4">
                                <div>
                                    <p class="text-sm text-[#4d5851] dark:text-white/65">Ventas de hoy</p>
                                    <p class="mt-2 text-3xl font-black">{{ moneda(dashboard.kpis.facturacion_hoy) }}</p>
                                    <p class="mt-2 font-mono text-xs text-[#657069] dark:text-white/48">R23W7-CAJA-01</p>
                                </div>
                                <span v-if="dashboard.kpis.variacion_facturacion !== null" class="rounded-full bg-[#EAF7EF] px-3 py-1 text-xs font-black text-[#116b39]">
                                    {{ dashboard.kpis.variacion_facturacion >= 0 ? '+' : '' }}{{ dashboard.kpis.variacion_facturacion }}%
                                </span>
                            </article>
                            <article class="dashboard-card flex min-h-32 items-start justify-between gap-4 p-4">
                                <div>
                                    <p class="text-sm text-[#4d5851] dark:text-white/65">Facturas emitidas</p>
                                    <p class="mt-2 text-3xl font-black">{{ dashboard.kpis.facturas_hoy }}</p>
                                    <p class="mt-2 font-mono text-xs text-[#657069] dark:text-white/48">CUF verificado</p>
                                </div>
                                <span class="rounded-full bg-[#EAF7EF] px-3 py-1 text-xs font-black text-[#116b39]">SIAT ok</span>
                            </article>
                            <article class="dashboard-card flex min-h-32 items-start justify-between gap-4 p-4">
                                <div>
                                    <p class="text-sm text-[#4d5851] dark:text-white/65">Tiempo promedio</p>
                                    <p class="mt-2 text-3xl font-black">38s</p>
                                    <p class="mt-2 font-mono text-xs text-[#657069] dark:text-white/48">flujo express</p>
                                </div>
                                <span class="rounded-full bg-[#EAF7EF] px-3 py-1 text-xs font-black text-[#116b39]">Agil</span>
                            </article>
                            <article class="dashboard-card flex min-h-32 items-start justify-between gap-4 p-4">
                                <div>
                                    <p class="text-sm text-[#4d5851] dark:text-white/65">Estado SIAT</p>
                                    <p class="mt-2 text-2xl font-black">{{ dashboard.siat?.estado || 'No configurado' }}</p>
                                    <p class="mt-2 font-mono text-xs text-[#657069] dark:text-white/48">{{ ambienteFacturacion }}</p>
                                </div>
                                <span :class="siatOperativo ? 'bg-[#EAF7EF] text-[#116b39]' : 'bg-amber-50 text-amber-700'" class="rounded-full px-3 py-1 text-xs font-black">
                                    {{ siatOperativo ? 'Operativo' : 'Revisar' }}
                                </span>
                            </article>
                        </section>

                        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(340px,0.75fr)]">
                            <article class="dashboard-card min-w-0 p-4 sm:p-5">
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-black">Facturacion de la semana</h2>
                                        <p class="mt-1 text-sm text-[#657069] dark:text-white/55">Ultimos 7 dias con lectura operativa.</p>
                                    </div>
                                    <span class="w-fit rounded-md border border-[#dfe7e2] px-3 py-2 text-xs font-bold text-muted-foreground dark:border-white/10">
                                        Pulso semanal
                                    </span>
                                </div>
                                <div class="relative h-[255px] w-full overflow-hidden" role="img" aria-label="Grafico de facturacion de los ultimos siete dias">
                                    <svg viewBox="0 0 700 235" class="h-[220px] w-full overflow-visible" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="billingArea" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#20A85B" stop-opacity="0.24" />
                                                <stop offset="100%" stop-color="#20A85B" stop-opacity="0.02" />
                                            </linearGradient>
                                        </defs>
                                        <g v-for="(nivel, index) in nivelesGrafico" :key="index">
                                            <line x1="40" x2="660" :y1="45 + index * 40" :y2="45 + index * 40" stroke="currentColor" class="text-[#dfe7e2] dark:text-white/10" stroke-width="1" />
                                            <text x="2" :y="49 + index * 40" fill="currentColor" class="text-[11px] text-[#657069] dark:text-white/45">{{ numero(nivel) }}</text>
                                        </g>
                                        <path v-if="hayFacturacionSemana" :d="area" fill="url(#billingArea)" />
                                        <polyline v-if="hayFacturacionSemana" :points="linea" fill="none" stroke="#168447" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                        <g v-if="hayFacturacionSemana">
                                            <circle v-for="punto in puntos" :key="punto.fecha" :cx="punto.x" :cy="punto.y" r="4" fill="white" stroke="#168447" stroke-width="2.5">
                                                <title>{{ fechaCorta(punto.fecha) }}: {{ moneda(punto.total) }}</title>
                                            </circle>
                                        </g>
                                    </svg>
                                    <div class="mr-[4.8%] ml-[5.7%] grid grid-cols-7 text-center text-[11px] text-[#657069] dark:text-white/50">
                                        <span v-for="item in dashboard.facturacion_semana" :key="item.fecha" class="capitalize">{{ fechaCorta(item.fecha) }}</span>
                                    </div>
                                    <div v-if="!hayFacturacionSemana" class="absolute inset-0 flex items-center justify-center pt-2">
                                        <div class="rounded-xl bg-white/90 px-5 py-3 text-center shadow-sm dark:bg-[#19221D]/95">
                                            <PackageOpen class="mx-auto mb-1 size-6 text-[#168447]" />
                                            <p class="text-sm font-semibold">Aun no hay facturas esta semana</p>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <aside class="space-y-5">
                                <article class="dashboard-card p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h2 class="text-lg font-black">Factura express</h2>
                                            <p class="mt-1 text-sm text-[#657069] dark:text-white/55">Acceso directo para caja y venta rapida.</p>
                                        </div>
                                        <span class="rounded-full bg-[#EAF7EF] px-3 py-1 text-xs font-black text-[#116b39]">Listo</span>
                                    </div>
                                    <div class="mt-4 space-y-2">
                                        <div class="rounded-md border border-[#dfe7e2] bg-[#F8FBF9] px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                            <p class="text-[11px] text-[#657069] dark:text-white/48">Cliente frecuente</p>
                                            <p class="font-bold">Consumidor final / NIT rapido</p>
                                        </div>
                                        <div class="rounded-md border border-[#dfe7e2] bg-[#F8FBF9] px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                            <p class="text-[11px] text-[#657069] dark:text-white/48">Producto destacado</p>
                                            <p class="font-bold">Servicio tecnico diagnostico</p>
                                        </div>
                                        <div class="rounded-md border border-[#dfe7e2] bg-[#F8FBF9] px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                            <p class="text-[11px] text-[#657069] dark:text-white/48">Salida</p>
                                            <p class="font-bold">PDF + impresion media carta</p>
                                        </div>
                                    </div>
                                    <Link href="/facturacion/facturas/nueva" class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-[#168447] px-4 text-sm font-black text-white transition hover:bg-[#116f3b]">
                                        <ReceiptText class="size-4" /> Iniciar venta rapida
                                    </Link>
                                </article>

                                <article class="dashboard-card p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h2 class="text-lg font-black">Salud fiscal</h2>
                                            <p class="mt-1 text-sm text-[#657069] dark:text-white/55">CUFD, CUIS y catalogos visibles.</p>
                                        </div>
                                        <span :class="siatOperativo ? 'bg-[#EAF7EF] text-[#116b39]' : 'bg-amber-50 text-amber-700'" class="rounded-full px-3 py-1 text-xs font-black">
                                            {{ siatOperativo ? 'Operativo' : 'Revisar' }}
                                        </span>
                                    </div>
                                    <div class="mt-4 space-y-2">
                                        <div class="flex items-center justify-between rounded-md border border-[#dfe7e2] px-3 py-2 dark:border-white/10">
                                            <span>CUFD vigente</span>
                                            <b>{{ vigenciaCufd }}</b>
                                        </div>
                                        <div class="flex items-center justify-between rounded-md border border-[#dfe7e2] px-3 py-2 dark:border-white/10">
                                            <span>Catalogos SIAT</span>
                                            <b>{{ ultimaSincronizacion }}</b>
                                        </div>
                                        <div class="flex items-center justify-between rounded-md border border-[#dfe7e2] px-3 py-2 dark:border-white/10">
                                            <span>Ambiente</span>
                                            <b class="uppercase">{{ ambienteFacturacion }}</b>
                                        </div>
                                    </div>
                                    <Link v-if="dashboard.siat?.habilitado" href="/facturacion/sincronizaciones-siat" class="mt-4 inline-flex items-center gap-2 text-sm font-black text-[#168447] hover:text-[#116f3b]">
                                        Ver detalles SIAT <ArrowRight class="size-4" />
                                    </Link>
                                </article>
                            </aside>
                        </section>

                        <section class="dashboard-card min-w-0 overflow-hidden p-4 sm:p-5">
                            <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-black">Facturas recientes</h2>
                                    <p class="mt-1 text-sm text-[#657069] dark:text-white/55">Documentos emitidos y respuesta fiscal.</p>
                                </div>
                                <Link href="/facturacion/facturas" class="inline-flex w-fit items-center gap-2 text-sm font-black text-[#168447] hover:text-[#116f3b]">
                                    Ver facturas <ArrowRight class="size-4" />
                                </Link>
                            </div>
                            <div v-if="dashboard.facturas_recientes.length" class="overflow-x-auto rounded-lg border border-[#e1e8e4] dark:border-white/10">
                                <table class="w-full min-w-[720px] table-fixed text-left text-xs">
                                    <thead class="bg-[#F5F8F6] text-[#4d5851] dark:bg-white/5 dark:text-white/60">
                                        <tr>
                                            <th class="w-28 px-3 py-2.5 font-semibold">Factura</th>
                                            <th class="w-36 px-3 py-2.5 font-semibold">Fecha</th>
                                            <th class="px-3 py-2.5 font-semibold">Cliente</th>
                                            <th class="w-28 px-3 py-2.5 font-semibold">Total</th>
                                            <th class="w-28 px-3 py-2.5 font-semibold">Estado</th>
                                            <th class="w-24 px-3 py-2.5 font-semibold">CUF</th>
                                            <th class="w-16 px-3 py-2.5 text-center font-semibold">Ver</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#e8eeea] dark:divide-white/10">
                                        <tr v-for="factura in dashboard.facturas_recientes" :key="factura.id" class="transition hover:bg-[#F5F8F6] dark:hover:bg-white/5">
                                            <td class="px-3 py-2.5 font-semibold whitespace-nowrap">{{ factura.numero }}</td>
                                            <td class="px-3 py-2.5 whitespace-nowrap text-muted-foreground">{{ fechaHora(factura.fecha) }}</td>
                                            <td class="max-w-52 truncate px-3 py-2.5">{{ factura.cliente }}</td>
                                            <td class="px-3 py-2.5 font-semibold whitespace-nowrap">{{ moneda(factura.total) }}</td>
                                            <td class="px-3 py-2.5">
                                                <span :class="estadoFacturaClase(factura.estado)" class="inline-flex rounded-md px-2 py-1 font-medium capitalize">{{ factura.estado || 'Sin estado' }}</span>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <span class="inline-flex items-center gap-1 rounded-md bg-[#EAF7EF] px-2 py-1 font-bold text-[#116b39]"><BadgeCheck class="size-3" /> Verificado</span>
                                            </td>
                                            <td class="px-3 py-2.5 text-center">
                                                <Link href="/facturacion/facturas" class="inline-flex rounded-md border border-[#dfe7e2] p-1.5 text-[#168447] hover:bg-[#EAF7EF] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none dark:border-white/10" aria-label="Ver factura">
                                                    <FileText class="size-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-[#cbd8d0] px-5 py-8 text-center dark:border-white/15">
                                <ReceiptText class="mx-auto mb-2 size-7 text-[#168447]" />
                                <p class="text-sm font-semibold">No hay facturas recientes</p>
                                <p class="mt-1 text-xs text-muted-foreground">Las proximas facturas apareceran aqui.</p>
                            </div>
                        </section>
            </section>
        </main>
    </AppLayout>
</template>

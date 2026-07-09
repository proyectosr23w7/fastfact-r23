<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    CheckCircle2,
    CircleAlert,
    FileText,
    ReceiptText,
    ServerCog,
} from 'lucide-vue-next';

defineProps<{
    open: boolean;
    factura: Record<string, any> | null;
    showTechnicalDetails?: boolean;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const formatDate = (value?: unknown) => {
    if (!value) return 'No disponible';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime())
        ? String(value)
        : new Intl.DateTimeFormat('es-BO', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
};
const formatMoney = (value?: unknown) =>
    new Intl.NumberFormat('es-BO', {
        style: 'currency',
        currency: 'BOB',
    }).format(Number(value ?? 0));
const statusLabel = (status?: unknown) =>
    ({
        emitida: 'Validada',
        pendiente: 'Pendiente',
        pendiente_envio: 'Pendiente de envio',
        observada: 'Observada',
        rechazada: 'Rechazada',
        anulada: 'Anulada',
    })[String(status ?? '')] ??
    String(status ?? 'No disponible').replaceAll('_', ' ');
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="max-h-[92vh] max-w-5xl overflow-y-auto border-[#cad7ce] p-0"
        >
            <div
                class="border-b border-[#26382e] bg-[#101713] px-6 py-5 text-white"
            >
                <DialogHeader
                    ><DialogTitle class="text-left text-xl"
                        >Detalle de factura</DialogTitle
                    ><DialogDescription class="text-left text-[#c8d8ce]"
                        >Consulta la informacion principal de la
                        factura.</DialogDescription
                    ></DialogHeader
                >
            </div>
            <div v-if="factura" class="space-y-5 p-5 md:p-6">
                <section
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    aria-label="Resumen de factura"
                >
                    <div
                        class="rounded-xl border border-[#dce5df] bg-[#f8faf9] p-4"
                    >
                        <ReceiptText class="size-5 text-[#168447]" />
                        <p class="mt-3 text-xs text-[#66736a]">Factura</p>
                        <p class="font-bold">
                            FAC-{{
                                String(
                                    factura.numero_factura || factura.id,
                                ).padStart(7, '0')
                            }}
                        </p>
                        <p class="mt-1 text-xs text-[#66736a]">
                            {{ formatDate(factura.fecha_emision) }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-[#dce5df] bg-[#f8faf9] p-4"
                    >
                        <FileText class="size-5 text-[#168447]" />
                        <p class="mt-3 text-xs text-[#66736a]">Venta</p>
                        <p class="font-bold">
                            {{ factura.venta?.numero_venta || 'No disponible' }}
                        </p>
                        <p class="mt-1 truncate text-xs text-[#66736a]">
                            {{
                                factura.cliente?.razon_social ||
                                factura.cliente?.nombre ||
                                'Sin cliente'
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-[#dce5df] bg-[#f8faf9] p-4"
                    >
                        <component
                            :is="
                                ['observada', 'rechazada'].includes(
                                    String(factura.estado_factura),
                                )
                                    ? CircleAlert
                                    : CheckCircle2
                            "
                            :class="[
                                'size-5',
                                ['observada', 'rechazada'].includes(
                                    String(factura.estado_factura),
                                )
                                    ? 'text-red-600'
                                    : 'text-[#168447]',
                            ]"
                        />
                        <p class="mt-3 text-xs text-[#66736a]">Estado SIAT</p>
                        <p class="font-bold">
                            {{ statusLabel(factura.estado_factura) }}
                        </p>
                        <p class="mt-1 text-xs text-[#66736a]">
                            {{
                                showTechnicalDetails
                                    ? `Codigo ${factura.codigo_estado || 'no disponible'}`
                                    : 'Resultado de la emision'
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-[#dce5df] bg-[#f8faf9] p-4"
                    >
                        <ServerCog class="size-5 text-[#168447]" />
                        <p class="mt-3 text-xs text-[#66736a]">Total</p>
                        <p class="font-bold">
                            {{ formatMoney(factura.monto_total) }}
                        </p>
                        <p class="mt-1 text-xs text-[#66736a]">
                            Sujeto a IVA
                            {{ formatMoney(factura.monto_sujeto_iva) }}
                        </p>
                    </div>
                </section>

                <section
                    v-if="showTechnicalDetails && factura.descripcion_estado"
                    :class="[
                        'rounded-xl border p-4',
                        ['observada', 'rechazada'].includes(
                            String(factura.estado_factura),
                        )
                            ? 'border-red-200 bg-red-50'
                            : 'border-[#cbe2d3] bg-[#f3faf5]',
                    ]"
                >
                    <h3 class="text-sm font-bold">Respuesta registrada</h3>
                    <p
                        class="mt-2 text-sm leading-6 break-words whitespace-pre-wrap"
                    >
                        {{ factura.descripcion_estado }}
                    </p>
                </section>

                <div class="grid gap-4 lg:grid-cols-2">
                    <section
                        v-if="showTechnicalDetails"
                        class="rounded-xl border border-[#dce5df] p-4"
                    >
                        <h3 class="font-bold">Datos fiscales</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-xs text-[#66736a]">CUF</dt>
                                <dd class="mt-1 font-mono text-xs break-all">
                                    {{ factura.cuf || 'No disponible' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#66736a]">
                                    Codigo de recepcion
                                </dt>
                                <dd class="mt-1 break-all">
                                    {{
                                        factura.codigo_recepcion ||
                                        'No disponible'
                                    }}
                                </dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-xs text-[#66736a]">
                                        Ambiente
                                    </dt>
                                    <dd class="mt-1 capitalize">
                                        {{
                                            factura.ambiente_facturacion ||
                                            'No disponible'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-[#66736a]">
                                        Emision
                                    </dt>
                                    <dd class="mt-1">
                                        {{
                                            Number(factura.codigo_emision) === 2
                                                ? 'Contingencia'
                                                : 'En linea'
                                        }}
                                    </dd>
                                </div>
                            </div>
                            <div>
                                <dt class="text-xs text-[#66736a]">Hash XML</dt>
                                <dd class="mt-1 font-mono text-xs break-all">
                                    {{ factura.hash_xml || 'No disponible' }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                    <section class="rounded-xl border border-[#dce5df] p-4">
                        <h3 class="font-bold">Contexto operativo</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-[#66736a]">Sucursal</dt>
                                <dd class="mt-1">
                                    {{
                                        factura.sucursal?.nombre ||
                                        'No disponible'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#66736a]">
                                    Punto de venta
                                </dt>
                                <dd class="mt-1">
                                    {{
                                        factura.punto_venta?.nombre ||
                                        'No disponible'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#66736a]">CUIS</dt>
                                <dd class="mt-1 break-all">
                                    {{
                                        factura.cuis?.codigo || 'No disponible'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[#66736a]">CUFD</dt>
                                <dd class="mt-1 break-all">
                                    {{
                                        factura.cufd?.codigo || 'No disponible'
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="factura.evento_significativo"
                                class="sm:col-span-2"
                            >
                                <dt class="text-xs text-[#66736a]">
                                    Evento significativo
                                </dt>
                                <dd class="mt-1">
                                    {{
                                        factura.evento_significativo
                                            .codigo_evento
                                    }}
                                    -
                                    {{
                                        factura.evento_significativo.descripcion
                                    }}
                                </dd>
                            </div>
                            <div v-if="factura.cafc" class="sm:col-span-2">
                                <dt class="text-xs text-[#66736a]">CAFC</dt>
                                <dd class="mt-1">{{ factura.cafc.codigo }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <details
                    v-if="
                        showTechnicalDetails &&
                        factura.datos_respuesta_siat &&
                        Object.keys(factura.datos_respuesta_siat).length
                    "
                    class="rounded-xl border border-[#dce5df] p-4"
                >
                    <summary class="cursor-pointer font-bold text-[#168447]">
                        Ver respuesta tecnica completa
                    </summary>
                    <pre
                        class="mt-4 max-h-72 overflow-auto rounded-lg bg-[#101713] p-4 text-xs whitespace-pre-wrap text-[#eaf7ef]"
                        >{{
                            JSON.stringify(
                                factura.datos_respuesta_siat,
                                null,
                                2,
                            )
                        }}</pre
                    >
                </details>
                <section
                    v-if="factura.anulaciones?.length"
                    class="rounded-xl border border-[#dce5df] p-4"
                >
                    <h3 class="font-bold">Historial de anulacion</h3>
                    <div
                        v-for="anulacion in factura.anulaciones"
                        :key="anulacion.id"
                        class="mt-3 border-t border-[#e2e8e4] pt-3 text-sm"
                    >
                        <p class="font-semibold">
                            {{
                                anulacion.descripcion_motivo ||
                                'Motivo SIAT ' +
                                    anulacion.codigo_motivo_anulacion
                            }}
                        </p>
                        <p class="mt-1 text-[#66736a]">
                            {{ formatDate(anulacion.fecha_anulacion) }} ·
                            {{
                                anulacion.descripcion_respuesta ||
                                'Sin respuesta disponible'
                            }}
                        </p>
                    </div>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>

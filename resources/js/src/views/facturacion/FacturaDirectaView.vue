<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import VentaForm from '@/src/components/ventas/VentaForm.vue';
import { ApiError } from '@/src/services/apiClient';
import { clienteVentaService } from '@/src/services/clienteVentaService';
import { facturacionService } from '@/src/services/facturacionService';
import { useVentaStore } from '@/src/stores/ventaStore';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, BadgeHelp } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type FlowNotice = {
    tone: 'error' | 'warning' | 'success';
    title: string;
    message: string;
};
type SubmitMode = 'draft' | 'confirm' | 'confirm_print';
const store = useVentaStore();
const flowNotice = ref<FlowNotice | null>(null);
const breadcrumbs = [
    { title: 'Facturacion SIAT', href: '/facturacion/facturas' },
    { title: 'Nueva factura', href: '/facturacion/facturas/nueva' },
];
const actionLabels = {
    confirm: 'Emitir factura',
    confirmPrint: 'Emitir e imprimir',
    processing: 'Procesando...',
};
const articulos = computed(
    () =>
        (store.state.meta.articulos as Record<string, unknown>[] | undefined) ??
        [],
);
const clientes = computed(
    () =>
        (store.state.meta.clientes as Record<string, unknown>[] | undefined) ??
        [],
);
const selectedCliente = computed(
    () =>
        clientes.value.find(
            (cliente) =>
                String(cliente.id ?? '') ===
                String(store.state.form.cliente_id ?? ''),
        ) ?? null,
);
const setNotice = (notice: FlowNotice | null) => {
    flowNotice.value = notice;
};
const articuloById = (id: unknown) =>
    articulos.value.find(
        (articulo) => String(articulo.id ?? '') === String(id ?? ''),
    );
const numeroTarjeta = () =>
    store.state.form.numero_tarjeta_inicio &&
    store.state.form.numero_tarjeta_fin
        ? `${store.state.form.numero_tarjeta_inicio}${store.state.form.numero_tarjeta_fin}`
        : null;
const mapErrorsToVentaForm = (errors: Record<string, string[]>) =>
    Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => {
            const mapped = field
                .replace(
                    /^detalles\.(\d+)\.actividad_economica$/,
                    'detalle.$1.articulo_id',
                )
                .replace(
                    /^detalles\.(\d+)\.codigo_producto_sin$/,
                    'detalle.$1.articulo_id',
                )
                .replace(
                    /^detalles\.(\d+)\.codigo_producto$/,
                    'detalle.$1.articulo_id',
                )
                .replace(
                    /^detalles\.(\d+)\.unidad_medida$/,
                    'detalle.$1.articulo_id',
                )
                .replace(
                    /^detalles\.(\d+)\.precio_unitario$/,
                    'detalle.$1.precio_unitario',
                )
                .replace(
                    /^detalles\.(\d+)\.monto_descuento$/,
                    'detalle.$1.descuento',
                )
                .replace(/^detalles\.(\d+)\./, 'detalle.$1.');
            return [mapped, messages];
        }),
    );
const buildDetalles = () =>
    store.state.form.detalle.map((item, index) => {
        const articulo = articuloById(item.articulo_id);
        const actividad = String(
            articulo?.codigo_actividad_economica ?? '',
        ).trim();
        const productoSin = String(articulo?.codigo_producto_sin ?? '').trim();
        const unidadSiat = String(
            articulo?.codigo_unidad_medida_siat ?? '',
        ).trim();
        if (!articulo || !actividad || !productoSin || !unidadSiat) {
            throw new Error(
                `El item ${index + 1} no tiene homologacion SIAT completa. Revisa actividad, producto SIN y unidad SIAT en el articulo.`,
            );
        }
        return {
            articulo_id: Number(item.articulo_id || 0) || null,
            actividad_economica: actividad,
            codigo_producto_sin: Number(productoSin),
            codigo_producto: String(
                articulo.codigo_generico ||
                    articulo.codigo_barras ||
                    articulo.id ||
                    productoSin,
            ),
            descripcion: String(articulo.descripcion || articulo.nombre || ''),
            cantidad: Number(item.cantidad || 0),
            unidad_medida: Number(unidadSiat),
            precio_unitario: Number(item.precio_unitario || 0),
            monto_descuento: Number(item.descuento || 0),
            numero_serie: null,
            numero_imei: null,
        };
    });
const persistSelectedClientEmail = async () => {
    const cliente = selectedCliente.value;
    if (!cliente) return;

    const correo = String(cliente.correo ?? '').trim();

    if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        throw new Error('El correo del cliente no tiene un formato valido.');
    }

    await clienteVentaService.update(Number(cliente.id), {
        razon_social: String(cliente.razon_social ?? cliente.nombre ?? ''),
        nit_ci: String(cliente.nit_ci ?? ''),
        tipo_documento_identidad: String(
            cliente.tipo_documento_identidad ?? '',
        ),
        complemento: String(cliente.complemento ?? ''),
        telefono: String(cliente.telefono ?? ''),
        correo: correo || null,
        estado: Boolean(cliente.estado ?? true),
    });
};
const submit = async (mode: SubmitMode) => {
    store.state.saving = true;
    store.state.errors = {};
    store.state.generalError = '';
    store.state.generalSuccess = '';
    setNotice(null);
    try {
        await persistSelectedClientEmail();
        store.recalculateTotals();
        const response = await facturacionService.emitirFacturaDirecta({
            cliente_id: Number(store.state.form.cliente_id),
            sucursal_id: Number(store.state.form.sucursal_id),
            punto_venta_id: Number(store.state.form.punto_venta_id),
            codigo_metodo_pago: store.state.form.codigo_metodo_pago || null,
            numero_tarjeta: numeroTarjeta(),
            monto_gift_card:
                store.state.form.monto_gift_card !== ''
                    ? Number(store.state.form.monto_gift_card)
                    : null,
            origen: 'directa',
            observacion: store.state.form.observacion || null,
            detalles: buildDetalles(),
        });
        store.state.generalSuccess = String(
            response.message ?? 'Factura directa emitida correctamente.',
        );
        if (mode === 'confirm_print') {
            const pdfUrl = String(
                (response.data as Record<string, unknown>)?.pdf_download_url ??
                    '',
            );
            if (pdfUrl) window.open(pdfUrl, '_blank', 'noopener,noreferrer');
        }
        window.location.assign('/facturacion/facturas');
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            store.state.errors = mapErrorsToVentaForm(error.errors);
            store.state.generalError = error.message;
            setNotice({
                tone: 'error',
                title: 'No se pudo emitir la factura',
                message: error.message,
            });
            return;
        }
        const message =
            error instanceof Error
                ? error.message
                : 'No fue posible emitir la factura.';
        store.state.generalError = message;
        setNotice({
            tone: 'error',
            title: 'No se pudo emitir la factura',
            message,
        });
    } finally {
        store.state.saving = false;
    }
};
onMounted(async () => {
    await store.load();
    store.startCreate();
    store.state.form.tipo_documento_venta = 'factura';
    store.state.form.requiere_factura = true;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="min-h-full flex-1 bg-[#f5f8f6] px-3 py-4 text-[#101713] sm:px-5 lg:px-7"
        >
            <div class="mx-auto max-w-[1540px]">
                <header
                    class="mb-4 flex flex-wrap items-center gap-3 border-b border-[#dde7e0] pb-4"
                >
                    <Button as-child variant="ghost" class="px-2">
                        <Link href="/facturacion/facturas"
                            ><ArrowLeft class="size-4" /> Volver</Link
                        >
                    </Button>
                    <span class="hidden h-7 w-px bg-[#dde7e0] sm:block" />
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight sm:text-3xl"
                        >
                            Nueva factura directa
                        </h1>
                        <p class="mt-1 text-sm text-[#66736a]">
                            Carga cliente, productos y metodo de pago para emitir.
                        </p>
                    </div>
                    <span
                        class="ml-auto inline-flex items-center gap-2 rounded-lg border border-[#bfdcc9] bg-[#eaf7ef] px-3 py-2 text-xs font-semibold text-[#126b3b]"
                        ><BadgeHelp class="size-4" aria-hidden="true" /> F2
                        Buscar producto</span
                    >
                </header>
                <VentaForm
                    :form="store.state.form"
                    :meta="store.state.meta"
                    :errors="store.state.errors"
                    :general-error="store.state.generalError"
                    :general-success="store.state.generalSuccess"
                    :flow-notice="flowNotice"
                    :client-form="store.state.clientForm"
                    :client-errors="store.state.clientErrors"
                    :client-saving="store.state.clientSaving"
                    :client-editing-id="store.state.clientEditingId"
                    :save-client-handler="store.saveClient"
                    :is-editing="false"
                    :saving="store.state.saving || store.state.processing"
                    :stock-resolver="() => 999999999"
                    :puntos-venta="store.puntosVentaDisponibles"
                    :action-labels="actionLabels"
                    :show-draft-action="false"
                    @submit="submit"
                    @cancel="window.location.assign('/facturacion/facturas')"
                    @dismiss-notice="setNotice(null)"
                    @reset-client-form="store.resetClientForm()"
                    @edit-client="store.editClient($event)"
                    @add-row="store.addRow()"
                    @remove-row="store.removeRow($event)"
                    @recalc-row="store.recalculateDetail($event)"
                />
            </div>
        </main>
    </AppLayout>
</template>

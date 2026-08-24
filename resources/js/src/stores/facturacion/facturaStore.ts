import { ApiError } from '@/src/services/apiClient';
import { facturacionService } from '@/src/services/facturacionService';
import { reactive } from 'vue';

const round2 = (value: number) => Math.round(value * 100) / 100;

const initialEmitDetail = () => ({
    producto_servicio_id: '',
    actividad_economica: '',
    codigo_producto_sin: '',
    codigo_producto: '',
    descripcion: '',
    cantidad: 1,
    unidad_medida: '',
    precio_unitario: 0,
    monto_descuento: 0,
    subtotal: 0,
    numero_serie: '',
    numero_imei: '',
});

export function useFacturaStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        filters: {
            search: '',
            scope: 'facturas',
            fecha_desde: '',
            fecha_hasta: '',
            cliente_id: '',
            sucursal_id: '',
            punto_venta_id: '',
            estado_factura: '',
        },
        emitForm: {
            venta_id: '',
            cliente_id: '',
            sucursal_id: '',
            punto_venta_id: '',
            codigo_metodo_pago: '',
            numero_tarjeta_inicio: '',
            numero_tarjeta_fin: '',
            monto_gift_card: '',
            codigo_documento_identidad: '',
            referencia_externa: '',
            numero_factura_manual: '',
            fecha_emision_manual: '',
            observacion: '',
            detalles: [initialEmitDetail()],
            total: 0,
        },
        anularForm: {
            codigo_motivo_anulacion: '',
            descripcion_motivo: '',
        },
        reenviarCorreoForm: {
            correo: '',
            actualizar_cliente: true,
        },
        currentItem: null as Record<string, unknown> | null,
        loading: false,
        saving: false,
        processing: false,
        errors: {} as Record<string, string[]>,
        generalError: '',
        generalSuccess: '',
    });

    const defaultMetodoPago = () =>
        ((
            (state.meta.metodos_pago as
                | Record<string, unknown>[]
                | undefined) ?? []
        ).find((metodo) => Boolean(metodo.es_predeterminado ?? false)) ??
            ((state.meta.metodos_pago as
                | Record<string, unknown>[]
                | undefined) ?? [])[0]) as Record<string, unknown> | undefined;

    const defaultSucursal = () =>
        (((state.meta.salud_siat as Record<string, any> | undefined)?.contexto
            ?.sucursal_id
            ? {
                  id: (state.meta.salud_siat as Record<string, any>).contexto
                      .sucursal_id,
              }
            : null) ??
            ((state.meta.sucursales as Record<string, unknown>[] | undefined) ??
                [])[0]) as Record<string, unknown> | undefined;

    const defaultPuntoVenta = (sucursalId?: unknown) =>
        (
            (state.meta.puntos_venta as
                | Record<string, unknown>[]
                | undefined) ?? []
        ).find(
            (punto) =>
                String(punto.id ?? '') ===
                    String(
                        (
                            state.meta.salud_siat as
                                | Record<string, any>
                                | undefined
                        )?.contexto?.punto_venta_id ?? '',
                    ) ||
                String(punto.sucursal_id ?? '') === String(sucursalId ?? ''),
        ) as Record<string, unknown> | undefined;

    const resetEmitForm = () => {
        const metodo = defaultMetodoPago();
        const sucursal = defaultSucursal();
        const puntoVenta = defaultPuntoVenta(sucursal?.id);

        state.emitForm = {
            venta_id: '',
            cliente_id: '',
            sucursal_id: sucursal ? String(sucursal.id ?? '') : '',
            punto_venta_id: puntoVenta ? String(puntoVenta.id ?? '') : '',
            codigo_metodo_pago: metodo
                ? String(metodo.codigo_clasificador ?? '')
                : '',
            numero_tarjeta_inicio: '',
            numero_tarjeta_fin: '',
            monto_gift_card: '',
            codigo_documento_identidad: '',
            referencia_externa: '',
            numero_factura_manual: '',
            fecha_emision_manual: '',
            observacion: '',
            detalles: [initialEmitDetail()],
            total: 0,
        };
        state.errors = {};
    };

    const recalculateEmitDetail = (index: number) => {
        const item = state.emitForm.detalles[index];
        if (!item) return;

        const cantidad = Number(item.cantidad || 0);
        const precio = Number(item.precio_unitario || 0);
        const descuento = Number(item.monto_descuento || 0);
        item.subtotal = round2(Math.max(cantidad * precio - descuento, 0));
        recalculateEmitTotal();
    };

    const recalculateEmitTotal = () => {
        state.emitForm.total = round2(
            state.emitForm.detalles.reduce(
                (carry, item) => carry + Number(item.subtotal || 0),
                0,
            ),
        );
    };

    const addEmitDetail = () => {
        state.emitForm.detalles.push(initialEmitDetail());
        recalculateEmitTotal();
    };

    const removeEmitDetail = (index: number) => {
        state.emitForm.detalles.splice(index, 1);
        if (state.emitForm.detalles.length === 0)
            state.emitForm.detalles.push(initialEmitDetail());
        recalculateEmitTotal();
    };
    const resetAnularForm = () => {
        state.anularForm = {
            codigo_motivo_anulacion: '',
            descripcion_motivo: '',
        };
        state.errors = {};
    };

    const resetReenviarCorreoForm = (correo = '') => {
        state.reenviarCorreoForm = {
            correo,
            actualizar_cliente: true,
        };
        state.errors = {};
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';

        try {
            const response = await facturacionService.listFacturas({
                ...state.filters,
                meta_context: 'listado',
                per_page: 300,
            });
            state.items = response.data;
            state.meta = response.meta ?? {};
        } catch (error) {
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo cargar el centro de facturacion.';
        } finally {
            state.loading = false;
        }
    };

    const show = async (id: number) => {
        state.processing = true;
        state.generalError = '';

        try {
            const response = await facturacionService.showFactura(id);
            state.currentItem = response.data;

            return response.data;
        } catch (error) {
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo abrir el detalle de la factura.';
            return null;
        } finally {
            state.processing = false;
        }
    };

    const emitir = async () => {
        state.saving = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const numeroTarjeta =
                state.emitForm.numero_tarjeta_inicio &&
                state.emitForm.numero_tarjeta_fin
                    ? `${state.emitForm.numero_tarjeta_inicio}${state.emitForm.numero_tarjeta_fin}`
                    : null;

            const response = await facturacionService.emitirFactura({
                venta_id: Number(state.emitForm.venta_id),
                codigo_metodo_pago: state.emitForm.codigo_metodo_pago || null,
                numero_tarjeta: numeroTarjeta,
                monto_gift_card:
                    state.emitForm.monto_gift_card !== ''
                        ? Number(state.emitForm.monto_gift_card)
                        : null,
                codigo_documento_identidad:
                    state.emitForm.codigo_documento_identidad || null,
                numero_factura_manual:
                    state.emitForm.numero_factura_manual !== ''
                        ? Number(state.emitForm.numero_factura_manual)
                        : null,
                fecha_emision_manual:
                    state.emitForm.fecha_emision_manual || null,
                observacion: state.emitForm.observacion || null,
            });
            await load();
            state.generalSuccess =
                response.message ?? 'El proceso de facturacion finalizo.';
            resetEmitForm();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo procesar la emision.';
            return false;
        } finally {
            state.saving = false;
        }
    };

    const emitirDirecta = async () => {
        state.saving = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            state.emitForm.detalles.forEach((_, index) =>
                recalculateEmitDetail(index),
            );

            const numeroTarjeta =
                state.emitForm.numero_tarjeta_inicio &&
                state.emitForm.numero_tarjeta_fin
                    ? `${state.emitForm.numero_tarjeta_inicio}${state.emitForm.numero_tarjeta_fin}`
                    : null;

            const response = await facturacionService.emitirFacturaDirecta({
                cliente_id: Number(state.emitForm.cliente_id),
                sucursal_id: Number(state.emitForm.sucursal_id),
                punto_venta_id: Number(state.emitForm.punto_venta_id),
                codigo_metodo_pago: state.emitForm.codigo_metodo_pago || null,
                numero_tarjeta: numeroTarjeta,
                monto_gift_card:
                    state.emitForm.monto_gift_card !== ''
                        ? Number(state.emitForm.monto_gift_card)
                        : null,
                codigo_documento_identidad:
                    state.emitForm.codigo_documento_identidad || null,
                referencia_externa: state.emitForm.referencia_externa || null,
                origen: 'directa',
                observacion: state.emitForm.observacion || null,
                detalles: state.emitForm.detalles.map((detalle) => ({
                    actividad_economica: String(
                        detalle.actividad_economica ?? '',
                    ),
                    codigo_producto_sin: Number(detalle.codigo_producto_sin),
                    codigo_producto: String(
                        detalle.codigo_producto ||
                            detalle.codigo_producto_sin ||
                            '',
                    ),
                    descripcion: String(detalle.descripcion ?? ''),
                    cantidad: Number(detalle.cantidad || 0),
                    unidad_medida: Number(detalle.unidad_medida),
                    precio_unitario: Number(detalle.precio_unitario || 0),
                    monto_descuento: Number(detalle.monto_descuento || 0),
                    numero_serie: detalle.numero_serie || null,
                    numero_imei: detalle.numero_imei || null,
                })),
            });
            await load();
            state.generalSuccess =
                response.message ?? 'Factura directa emitida correctamente.';
            resetEmitForm();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo emitir la factura directa.';
            return false;
        } finally {
            state.saving = false;
        }
    };
    const consultar = async (item: Record<string, unknown>) => {
        state.processing = true;
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.consultarFactura(
                Number(item.id),
            );
            await load();
            state.generalSuccess =
                response.message ?? 'Estado consultado correctamente.';
            return response.data;
        } catch (error) {
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo consultar el estado en SIAT.';
            return null;
        } finally {
            state.processing = false;
        }
    };

    const reintentar = async (item: Record<string, unknown>) => {
        state.processing = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.reintentarFactura(
                Number(item.id),
                {},
            );
            await load();
            state.generalSuccess = response.message ?? 'Reintento procesado.';
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo reintentar la factura.';
            return false;
        } finally {
            state.processing = false;
        }
    };

    const anular = async (item: Record<string, unknown>) => {
        state.processing = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.anularFactura(
                Number(item.id),
                {
                    codigo_motivo_anulacion:
                        state.anularForm.codigo_motivo_anulacion,
                    descripcion_motivo:
                        state.anularForm.descripcion_motivo || null,
                },
            );
            await load();
            state.generalSuccess =
                response.message ?? 'Solicitud de anulacion procesada.';
            resetAnularForm();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo anular la factura.';
            return false;
        } finally {
            state.processing = false;
        }
    };

    const revertirAnulacion = async (item: Record<string, unknown>) => {
        state.processing = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.revertirAnulacionFactura(
                Number(item.id),
            );
            await load();
            state.generalSuccess =
                response.message ?? 'Reversion de anulacion procesada.';
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo revertir la anulacion de la factura.';
            return false;
        } finally {
            state.processing = false;
        }
    };

    const reenviarCorreo = async (item: Record<string, unknown>) => {
        state.processing = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.reenviarCorreoFactura(
                Number(item.id),
                {
                    correo: state.reenviarCorreoForm.correo,
                    actualizar_cliente:
                        state.reenviarCorreoForm.actualizar_cliente,
                },
            );
            await load();
            state.generalSuccess =
                response.message ?? 'Correo reenviado correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo reenviar el correo de la factura.';
            return false;
        } finally {
            state.processing = false;
        }
    };

    const resetFilters = () => {
        state.filters = {
            search: '',
            scope: 'facturas',
            fecha_desde: '',
            fecha_hasta: '',
            cliente_id: '',
            sucursal_id: '',
            punto_venta_id: '',
            estado_factura: '',
        };
    };

    return {
        state,
        load,
        show,
        emitir,
        emitirDirecta,
        addEmitDetail,
        removeEmitDetail,
        recalculateEmitDetail,
        recalculateEmitTotal,
        reintentar,
        consultar,
        anular,
        revertirAnulacion,
        reenviarCorreo,
        resetEmitForm,
        resetAnularForm,
        resetReenviarCorreoForm,
        resetFilters,
    };
}

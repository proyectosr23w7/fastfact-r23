import { ApiError } from '@/src/services/apiClient';
import { clienteVentaService } from '@/src/services/clienteVentaService';
import { facturacionService } from '@/src/services/facturacionService';
import { ventaService } from '@/src/services/ventaService';
import { reactive } from 'vue';

const today = () => new Date().toISOString().slice(0, 10);

const initialDetail = () => ({
    articulo_id: '',
    cantidad: 1,
    precio_unitario: 0,
    descuento: 0,
    subtotal: 0,
    total: 0,
    costo_unitario: 0,
    costo_total: 0,
    utilidad_bruta: 0,
    lote_consumido: [] as Record<string, unknown>[],
});

const initialForm = () => ({
    cliente_id: '',
    sucursal_id: '',
    punto_venta_id: '',
    user_id: '',
    fecha_venta: today(),
    tipo_documento_venta: 'nota_venta',
    descuento_global: 0,
    codigo_metodo_pago: '',
    numero_tarjeta_inicio: '',
    numero_tarjeta_fin: '',
    monto_gift_card: '',
    requiere_factura: false,
    observacion: '',
    detalle: [initialDetail()],
    subtotal: 0,
    descuento: 0,
    iva: 0,
    it: 0,
    impuesto: 0,
    total: 0,
});

const round2 = (value: number) => Math.round(value * 100) / 100;

const validationFieldLabel = (field: string) => {
    const normalized = field.replace(/\.\d+\./g, '.');
    const labels: Record<string, string> = {
        cliente_id: 'cliente',
        sucursal_id: 'sucursal',
        punto_venta_id: 'punto de venta',
        user_id: 'responsable',
        fecha_venta: 'fecha de venta',
        tipo_documento_venta: 'tipo de documento',
        descuento_global: 'descuento global',
        codigo_metodo_pago: 'método de pago',
        numero_tarjeta: 'número de tarjeta',
        monto_gift_card: 'monto de gift card',
        observacion: 'observación',
        detalle: 'detalle de venta',
        'detalle.articulo_id': 'artículo',
        'detalle.cantidad': 'cantidad',
        'detalle.precio_unitario': 'precio unitario',
        'detalle.descuento': 'descuento del artículo',
        razon_social: 'razón social',
        nit_ci: 'NIT o CI',
        tipo_documento_identidad: 'tipo de documento',
        complemento: 'complemento',
        telefono: 'teléfono',
        correo: 'correo',
    };

    return labels[normalized] ?? 'campo';
};

const translateValidationMessage = (field: string, message: string) => {
    const normalizedMessage = message
        .replace(/\bvalido\b/g, 'válido')
        .replace(/\binvalido\b/g, 'inválido')
        .replace(/\bdespues\b/g, 'después')
        .replace(/\bfacturacion\b/g, 'facturación')
        .replace(/\bnumero\b/g, 'número')
        .replace(/\bmetodo\b/g, 'método');

    if (!/^(The|This)\b/i.test(normalizedMessage.trim()))
        return normalizedMessage;

    const label = validationFieldLabel(field);
    if (/selected .* is invalid/i.test(normalizedMessage))
        return `El valor seleccionado para ${label} no es válido.`;
    if (/field is required/i.test(normalizedMessage))
        return `El campo ${label} es obligatorio.`;
    if (/must be greater than or equal to/i.test(normalizedMessage))
        return `El campo ${label} debe ser mayor o igual al mínimo permitido.`;
    if (/must be greater than/i.test(normalizedMessage))
        return `El campo ${label} debe ser mayor al mínimo permitido.`;
    if (/must be a number|must be numeric/i.test(normalizedMessage))
        return `El campo ${label} debe ser numérico.`;
    if (/must be a valid email/i.test(normalizedMessage))
        return `El campo ${label} debe contener un correo válido.`;
    if (/must be an array/i.test(normalizedMessage))
        return `El campo ${label} debe contener una lista válida.`;

    return `Revisa el campo ${label}.`;
};

const translateValidationErrors = (errors: Record<string, string[]>) =>
    Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [
            field,
            messages.map((message) =>
                translateValidationMessage(field, String(message)),
            ),
        ]),
    );

export function useVentaStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        filters: {
            search: '',
            fecha_desde: '',
            fecha_hasta: '',
            cliente_id: '',
            sucursal_id: '',
            punto_venta_id: '',
            estado: '',
            tipo_documento_venta: '',
            per_page: 10,
            page: 1,
        },
        form: initialForm(),
        currentItem: null as Record<string, unknown> | null,
        loading: false,
        saving: false,
        processing: false,
        editingId: null as number | null,
        errors: {} as Record<string, string[]>,
        generalError: '',
        generalSuccess: '',
        clientErrors: {} as Record<string, string[]>,
        clientSaving: false,
        clientEditingId: null as number | null,
        clientForm: {
            razon_social: '',
            nit_ci: '',
            tipo_documento_identidad: '',
            complemento: '',
            telefono: '',
            correo: '',
            estado: true,
        },
    });

    const resetForm = () => {
        const contextoSiat = (state.meta.salud_siat as
            | Record<string, unknown>
            | undefined)?.contexto as Record<string, unknown> | undefined;
        const firstUser = ((state.meta.usuarios as
            | Record<string, unknown>[]
            | undefined) ?? [])[0];
        const sucursales = (state.meta.sucursales as
            | Record<string, unknown>[]
            | undefined) ?? [];
        const puntosVenta = (state.meta.puntos_venta as
            | Record<string, unknown>[]
            | undefined) ?? [];
        const firstSucursal =
            sucursales.find(
                (sucursal) =>
                    String(sucursal.id ?? '') ===
                    String(contextoSiat?.sucursal_id ?? ''),
            ) ?? sucursales[0];
        const firstPuntoVenta =
            puntosVenta.find(
                (puntoVenta) =>
                    String(puntoVenta.id ?? '') ===
                    String(contextoSiat?.punto_venta_id ?? ''),
            ) ??
            puntosVenta.find(
                (puntoVenta) =>
                    String(puntoVenta.sucursal_id ?? '') ===
                    String(firstSucursal?.id ?? ''),
            );
        const facturacionObligatoria = Boolean(
            state.meta.facturacion_obligatoria_ventas ?? false,
        );
        const metodoPagoDefault =
            ((
                (state.meta.metodos_pago_siat as
                    | Record<string, unknown>[]
                    | undefined) ?? []
            ).find((metodo) => Boolean(metodo.es_predeterminado ?? false)) as
                | Record<string, unknown>
                | undefined) ??
            ((
                (state.meta.metodos_pago_siat as
                    | Record<string, unknown>[]
                    | undefined) ?? []
            ).find(
                (metodo) =>
                    String(metodo.codigo_clasificador ?? '') === '1' ||
                    String(metodo.descripcion ?? '')
                        .toLowerCase()
                        .includes('efectivo'),
            ) as Record<string, unknown> | undefined) ??
            (((state.meta.metodos_pago_siat as
                | Record<string, unknown>[]
                | undefined) ?? [])[0] as Record<string, unknown> | undefined);

        state.form = {
            ...initialForm(),
            user_id: firstUser ? String(firstUser.id ?? '') : '',
            sucursal_id: firstSucursal ? String(firstSucursal.id ?? '') : '',
            punto_venta_id: firstPuntoVenta
                ? String(firstPuntoVenta.id ?? '')
                : '',
            tipo_documento_venta: facturacionObligatoria
                ? 'factura'
                : 'nota_venta',
            codigo_metodo_pago: metodoPagoDefault
                ? String(metodoPagoDefault.codigo_clasificador ?? '')
                : '',
            numero_tarjeta_inicio: '',
            numero_tarjeta_fin: '',
            monto_gift_card: '',
            requiere_factura: facturacionObligatoria,
        };
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';
        state.clientErrors = {};
        state.editingId = null;
    };

    const resetClientForm = () => {
        state.clientForm = {
            razon_social: '',
            nit_ci: '',
            tipo_documento_identidad: '',
            complemento: '',
            telefono: '',
            correo: '',
            estado: true,
        };
        state.clientErrors = {};
        state.clientEditingId = null;
    };

    const puntosVentaDisponibles = () =>
        (
            (state.meta.puntos_venta as
                | Record<string, unknown>[]
                | undefined) ?? []
        ).filter(
            (puntoVenta) =>
                String(puntoVenta.sucursal_id ?? '') ===
                String(state.form.sucursal_id ?? ''),
        );

    const findArticulo = (articuloId: string) =>
        (
            (state.meta.articulos as Record<string, unknown>[] | undefined) ??
            []
        ).find((articulo) => String(articulo.id) === String(articuloId));

    const stockSucursalArticulo = (articuloId: string) => {
        const articulo = findArticulo(articuloId);
        const stocks =
            (articulo?.stocks as Record<string, unknown>[] | undefined) ?? [];
        const stock = stocks.find(
            (item) =>
                String(item.sucursal_id ?? '') ===
                String(state.form.sucursal_id ?? ''),
        );

        return Number(stock?.stock_actual ?? 0);
    };

    const sugerirPrecio = (articuloId: string, cantidad: number) => {
        const articulo = findArticulo(articuloId);

        if (!articulo) return 0;

        const preciosPorCantidad = Boolean(
            state.meta.precios_por_cantidad ?? false,
        );
        const multiplesPrecios = Boolean(state.meta.multiples_precios ?? false);
        const precioBase = Number(articulo.precio_base ?? 0);

        if (!multiplesPrecios) {
            return precioBase;
        }

        const precios = (
            (articulo.precios as Record<string, unknown>[] | undefined) ?? []
        )
            .filter((precio) => Number(precio.cantidad_minima ?? 0) <= cantidad)
            .sort(
                (a, b) =>
                    Number(b.cantidad_minima ?? 0) -
                    Number(a.cantidad_minima ?? 0),
            );

        if (preciosPorCantidad && precios.length > 0) {
            return Number(precios[0].precio ?? precioBase);
        }

        return precioBase;
    };

    const recalculateDetail = (index: number) => {
        const item = state.form.detalle[index];
        const cantidad = Number(item.cantidad || 0);

        if (
            String(item.articulo_id ?? '') !== '' &&
            Number(item.precio_unitario || 0) <= 0
        ) {
            item.precio_unitario = sugerirPrecio(
                String(item.articulo_id),
                cantidad,
            );
        }

        item.subtotal = round2(cantidad * Number(item.precio_unitario || 0));
        item.total = round2(
            Math.max(item.subtotal - Number(item.descuento || 0), 0),
        );
        recalculateTotals();
    };

    const recalculateTotals = () => {
        state.form.subtotal = round2(
            state.form.detalle.reduce(
                (carry, item) => carry + Number(item.subtotal || 0),
                0,
            ),
        );
        state.form.descuento = round2(
            state.form.detalle.reduce(
                (carry, item) => carry + Number(item.descuento || 0),
                0,
            ),
        );
        state.form.total = round2(
            Math.max(
                state.form.subtotal -
                    state.form.descuento -
                    Number(state.form.descuento_global || 0),
                0,
            ),
        );
        state.form.iva = round2(state.form.total * 0.13);
        state.form.it = round2(state.form.total * 0.03);
        state.form.impuesto = state.form.iva;
    };

    const stockConflicts = () =>
        state.form.detalle
            .map((item, index) => {
                const articuloId = String(item.articulo_id ?? '');
                if (!articuloId) return null;

                const stockDisponible = stockSucursalArticulo(articuloId);
                const cantidad = Number(item.cantidad || 0);

                if (cantidad <= stockDisponible) {
                    return null;
                }

                const articulo = findArticulo(articuloId);

                return {
                    index,
                    articulo: String(articulo?.nombre ?? `Fila ${index + 1}`),
                    stockDisponible,
                    cantidad,
                };
            })
            .filter(Boolean) as {
            index: number;
            articulo: string;
            stockDisponible: number;
            cantidad: number;
        }[];

    const addRow = () => {
        state.form.detalle.push(initialDetail());
    };

    const removeRow = (index: number) => {
        state.form.detalle.splice(index, 1);

        if (state.form.detalle.length === 0) {
            state.form.detalle.push(initialDetail());
        }

        recalculateTotals();
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';

        try {
            const response = await facturacionService.listFacturas({
                meta_context: 'emision',
                per_page: 1,
            });
            state.items = [];
            state.meta = response.meta ?? {};

            if (!state.form.user_id) {
                resetForm();
            }
        } catch (error) {
            state.items = [];
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo cargar los datos de facturacion. Intenta nuevamente.';
        } finally {
            state.loading = false;
        }
    };

    const upsertClienteMeta = (cliente: Record<string, unknown>) => {
        const clientes =
            (state.meta.clientes as Record<string, unknown>[] | undefined) ??
            [];
        const clienteId = String(cliente.id ?? '');

        if (!clienteId) return;

        const index = clientes.findIndex(
            (item) => String(item.id ?? '') === clienteId,
        );

        state.meta.clientes =
            index >= 0
                ? clientes.map((item, itemIndex) =>
                      itemIndex === index ? { ...item, ...cliente } : item,
                  )
                : [cliente, ...clientes];
    };

    const goToPage = async (page: number) => {
        state.filters.page = Math.max(page, 1);
        await load();
    };

    const resetFilters = async () => {
        state.filters = {
            search: '',
            fecha_desde: '',
            fecha_hasta: '',
            cliente_id: '',
            sucursal_id: '',
            punto_venta_id: '',
            estado: '',
            tipo_documento_venta: '',
            per_page: 10,
            page: 1,
        };
        await load();
    };

    const hydrateForm = (item: Record<string, unknown>) => {
        state.editingId = Number(item.id);
        state.currentItem = item;
        state.errors = {};
        const numeroTarjeta = String(item.numero_tarjeta ?? '');
        state.form = {
            cliente_id: String(item.cliente_id ?? ''),
            sucursal_id: String(item.sucursal_id ?? ''),
            punto_venta_id: String(item.punto_venta_id ?? ''),
            user_id: String(item.user_id ?? ''),
            fecha_venta: String(item.fecha_venta ?? today()),
            tipo_documento_venta: String(
                item.tipo_documento_venta ?? 'nota_venta',
            ),
            descuento_global: Number(
                item.descuento_global ?? item.descuento ?? 0,
            ),
            codigo_metodo_pago: String(item.codigo_metodo_pago ?? ''),
            numero_tarjeta_inicio: numeroTarjeta
                ? numeroTarjeta.slice(0, 4)
                : '',
            numero_tarjeta_fin: numeroTarjeta ? numeroTarjeta.slice(-4) : '',
            monto_gift_card:
                item.monto_gift_card !== null &&
                item.monto_gift_card !== undefined
                    ? String(item.monto_gift_card)
                    : '',
            requiere_factura: Boolean(item.requiere_factura ?? false),
            observacion: String(item.observacion ?? ''),
            detalle: (
                (item.detalle as Record<string, unknown>[] | undefined) ?? []
            ).map((detail) => ({
                articulo_id: String(detail.articulo_id ?? ''),
                cantidad: Number(detail.cantidad ?? 1),
                precio_unitario: Number(detail.precio_unitario ?? 0),
                descuento: Number(detail.descuento ?? 0),
                subtotal: Number(detail.subtotal ?? 0),
                total: Number(detail.total ?? 0),
                costo_unitario: Number(detail.costo_unitario ?? 0),
                costo_total: Number(detail.costo_total ?? 0),
                utilidad_bruta: Number(detail.utilidad_bruta ?? 0),
                lote_consumido: Array.isArray(detail.lote_consumido)
                    ? detail.lote_consumido
                    : [],
            })),
            subtotal: Number(item.subtotal ?? 0),
            descuento: Number(item.descuento ?? 0),
            iva: Number(item.iva ?? item.impuesto ?? 0),
            it: Number(item.it ?? 0),
            impuesto: Number(item.iva ?? item.impuesto ?? 0),
            total: Number(item.total ?? 0),
        };
    };

    const startCreate = () => {
        resetForm();
    };

    const startEdit = async (item: Record<string, unknown>) => {
        const response = await ventaService.show(Number(item.id));
        hydrateForm(response.data);
    };

    const show = async (id: number) => {
        const response = await ventaService.show(id);
        state.currentItem = response.data;

        return response.data;
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const facturacionObligatoria = Boolean(
                state.meta.facturacion_obligatoria_ventas ?? false,
            );
            const numeroTarjeta =
                state.form.numero_tarjeta_inicio &&
                state.form.numero_tarjeta_fin
                    ? `${state.form.numero_tarjeta_inicio}${state.form.numero_tarjeta_fin}`
                    : null;

            const payload = {
                cliente_id: Number(state.form.cliente_id),
                sucursal_id: Number(state.form.sucursal_id),
                punto_venta_id: Number(state.form.punto_venta_id),
                user_id: Number(state.form.user_id),
                fecha_venta: state.form.fecha_venta,
                tipo_documento_venta: facturacionObligatoria
                    ? 'factura'
                    : String(state.form.tipo_documento_venta),
                descuento_global: Number(state.form.descuento_global || 0),
                codigo_metodo_pago: state.form.codigo_metodo_pago
                    ? String(state.form.codigo_metodo_pago)
                    : null,
                numero_tarjeta: numeroTarjeta,
                monto_gift_card:
                    state.form.monto_gift_card !== ''
                        ? Number(state.form.monto_gift_card)
                        : null,
                requiere_factura:
                    facturacionObligatoria ||
                    Boolean(state.form.requiere_factura),
                observacion: state.form.observacion || null,
                detalle: state.form.detalle.map((item) => ({
                    articulo_id: Number(item.articulo_id),
                    cantidad: Number(item.cantidad),
                    precio_unitario: Number(item.precio_unitario),
                    descuento: Number(item.descuento || 0),
                })),
            };

            const response = state.editingId
                ? await ventaService.update(state.editingId, payload)
                : await ventaService.create(payload);

            await load();
            const savedItem = response.data as Record<string, unknown>;
            state.generalSuccess = String(
                response.message ?? 'Venta registrada correctamente.',
            );
            resetForm();
            return savedItem;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = translateValidationErrors(error.errors);
                state.generalError = error.message;
            } else {
                throw error;
            }
        } finally {
            state.saving = false;
        }

        return null;
    };

    const editClient = (cliente: Record<string, unknown>) => {
        state.clientEditingId = Number(cliente.id ?? 0) || null;
        state.clientForm = {
            razon_social: String(cliente.razon_social ?? ''),
            nit_ci: String(cliente.nit_ci ?? ''),
            tipo_documento_identidad: String(cliente.tipo_documento_identidad ?? ''),
            complemento: String(cliente.complemento ?? ''),
            telefono: String(cliente.telefono ?? ''),
            correo: String(cliente.correo ?? ''),
            estado: Boolean(cliente.estado ?? true),
        };
        state.clientErrors = {};
    };

    const createClient = async () => {
        state.clientSaving = true;
        state.clientErrors = {};

        try {
            const response = await clienteVentaService.create({
                ...state.clientForm,
            });
            upsertClienteMeta(response.data);
            state.form.cliente_id = String(response.data.id ?? '');
            resetClientForm();
            return response.data;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.clientErrors = translateValidationErrors(error.errors);
            } else {
                throw error;
            }
        } finally {
            state.clientSaving = false;
        }

        return null;
    };

    const saveClient = async () => {
        if (!state.clientEditingId) return createClient();

        state.clientSaving = true;
        state.clientErrors = {};

        try {
            const response = await clienteVentaService.update(state.clientEditingId, {
                ...state.clientForm,
            });
            upsertClienteMeta(response.data);
            state.form.cliente_id = String(response.data.id ?? state.clientEditingId);
            resetClientForm();
            return response.data;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.clientErrors = translateValidationErrors(error.errors);
            } else {
                throw error;
            }
        } finally {
            state.clientSaving = false;
        }

        return null;
    };

    const confirm = async (
        item: Record<string, unknown>,
        observacion?: string,
    ) => {
        state.processing = true;
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await ventaService.confirm(
                Number(item.id),
                observacion,
            );
            await load();
            state.generalSuccess = String(
                response.message ?? 'Venta confirmada correctamente.',
            );
            return response.data as Record<string, unknown>;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.generalError = error.message;
                return null;
            }

            throw error;
        } finally {
            state.processing = false;
        }
    };

    const cancel = async (
        item: Record<string, unknown>,
        observacion?: string,
    ) => {
        state.processing = true;
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await ventaService.cancel(
                Number(item.id),
                observacion,
            );
            await load();
            state.generalSuccess = String(
                response.message ?? 'Venta anulada correctamente.',
            );
            return response.data as Record<string, unknown>;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.generalError = error.message;
                return null;
            }

            throw error;
        } finally {
            state.processing = false;
        }
    };

    return {
        state,
        load,
        goToPage,
        resetFilters,
        startCreate,
        startEdit,
        show,
        save,
        confirm,
        cancel,
        addRow,
        removeRow,
        recalculateDetail,
        recalculateTotals,
        createClient,
        saveClient,
        editClient,
        resetClientForm,
        stockSucursalArticulo,
        stockConflicts,
        puntosVentaDisponibles,
        sugerirPrecio,
    };
}

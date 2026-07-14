import { apiClient } from '@/src/services/apiClient';

const buildQuery = (filters: Record<string, unknown>) => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();

    return query ? `?${query}` : '';
};

export const facturacionService = {
    listFacturas: (filters: Record<string, unknown> = {}) =>
        apiClient.get<Record<string, unknown>[]>(
            `/api/facturacion/facturas${buildQuery(filters)}`,
        ),
    showFactura: (id: number) =>
        apiClient.get<Record<string, unknown>>(
            `/api/facturacion/facturas/${id}`,
        ),
    emitirFactura: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/facturas/emitir',
            payload,
        ),
    emitirFacturaDirecta: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/facturas/emitir-directa',
            payload,
        ),
    reintentarFactura: (id: number, payload: Record<string, unknown> = {}) =>
        apiClient.post<Record<string, unknown>>(
            `/api/facturacion/facturas/${id}/reintentar`,
            payload,
        ),
    consultarFactura: (id: number) =>
        apiClient.post<Record<string, unknown>>(
            `/api/facturacion/facturas/${id}/consultar`,
            {},
        ),
    anularFactura: (id: number, payload: Record<string, unknown>) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/facturas/${id}/anular`,
            payload,
        ),
    revertirAnulacionFactura: (id: number) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/facturas/${id}/revertir-anulacion`,
            {},
        ),
    reenviarCorreoFactura: (id: number, payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            `/api/facturacion/facturas/${id}/reenviar-correo`,
            payload,
        ),

    listCuis: (filters: Record<string, unknown> = {}) =>
        apiClient.get<Record<string, unknown>[]>(
            `/api/facturacion/cuis${buildQuery(filters)}`,
        ),
    createCuis: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/cuis',
            payload,
        ),

    listCufd: (filters: Record<string, unknown> = {}) =>
        apiClient.get<Record<string, unknown>[]>(
            `/api/facturacion/cufd${buildQuery(filters)}`,
        ),
    createCufd: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/cufd',
            payload,
        ),

    listCafc: (filters: Record<string, unknown> = {}) =>
        apiClient.get<Record<string, unknown>[]>(
            `/api/facturacion/cafc${buildQuery(filters)}`,
        ),
    createCafc: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/cafc',
            payload,
        ),
    updateCafc: (id: number, payload: Record<string, unknown>) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/cafc/${id}`,
            payload,
        ),
    updateCafcEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/cafc/${id}/estado`,
            { estado },
        ),

    listSincronizaciones: () =>
        apiClient.get<Record<string, unknown>[]>(
            '/api/facturacion/sincronizaciones/catalogos',
        ),
    syncCatalogos: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>[]>(
            '/api/facturacion/sincronizaciones/catalogos',
            payload,
        ),
    updateMetodoPagoEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/catalogos/metodos-pago/${id}/estado`,
            { estado },
        ),
    updateMetodoPagoOperativo: (id: number, payload: Record<string, unknown>) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/catalogos/metodos-pago/${id}/operativo`,
            payload,
        ),
    updateUnidadMedidaEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/catalogos/unidades-medida/${id}/estado`,
            { estado },
        ),

    listEventos: (filters: Record<string, unknown> = {}) =>
        apiClient.get<Record<string, unknown>[]>(
            `/api/facturacion/eventos-significativos${buildQuery(filters)}`,
        ),
    createEvento: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/eventos-significativos',
            payload,
        ),
    closeEvento: (id: number, payload: Record<string, unknown>) =>
        apiClient.patch<Record<string, unknown>>(
            `/api/facturacion/eventos-significativos/${id}/cerrar`,
            payload,
        ),
    processRecoveryEvento: (id: number) =>
        apiClient.post<Record<string, unknown>>(
            `/api/facturacion/eventos-significativos/${id}/procesar-recuperacion`,
            {},
        ),
    reportEvento: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(
            '/api/facturacion/eventos-significativos/reportes',
            payload,
        ),
};

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

export const reporteService = {
    facturacion: (filters: Record<string, unknown> = {}) =>
        apiClient.get<Record<string, unknown>>(
            `/api/reportes/facturacion${buildQuery(filters)}`,
        ),
};

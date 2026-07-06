import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/ventas/ventas';

const buildQuery = (filters: Record<string, string | number | null | undefined>) => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.append(key, String(value));
        }
    });

    const query = params.toString();

    return query ? `?${query}` : '';
};

export const ventaService = {
    list: (filters: Record<string, string | number | null | undefined>) =>
        apiClient.get<Record<string, unknown>[]>(`${baseUrl}${buildQuery(filters)}`),
    show: (id: number) => apiClient.get<Record<string, unknown>>(`${baseUrl}/${id}`),
    create: (payload: Record<string, unknown>) => apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) => apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    confirm: (id: number, observacion?: string) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/confirmar`, { observacion }),
    cancel: (id: number, observacion?: string) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/anular`, { observacion }),
};

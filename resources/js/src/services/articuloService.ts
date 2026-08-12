import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/inventario/articulos';

type ImportResult = Record<string, unknown>;

export const articuloService = {
    list: (filters: Record<string, string> = {}) => {
        const params = new URLSearchParams();

        Object.entries(filters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });

        const query = params.size > 0 ? `?${params.toString()}` : '';

        return apiClient.get<Record<string, unknown>[]>(`${baseUrl}${query}`);
    },
    show: (id: number) =>
        apiClient.get<Record<string, unknown>>(`${baseUrl}/${id}`),
    create: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(baseUrl, payload),
    import: (
        rows: Record<string, unknown>[],
        defaults: Record<string, unknown> = {},
    ) => apiClient.post<ImportResult>(`${baseUrl}/importar`, { rows, defaults }),
    update: (id: number, payload: Record<string, unknown>) =>
        apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    updateEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/estado`, {
            estado,
        }),
    remove: (id: number) => apiClient.delete(`${baseUrl}/${id}`),
};

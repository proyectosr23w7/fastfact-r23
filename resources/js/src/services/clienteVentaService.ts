import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/ventas/clientes';

export const clienteVentaService = {
    list: (filters: Record<string, string | number | null | undefined> = {}) => {
        const params = new URLSearchParams();

        Object.entries(filters).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                params.append(key, String(value));
            }
        });

        const query = params.toString();

        return apiClient.get<Record<string, unknown>[]>(
            query ? `${baseUrl}?${query}` : baseUrl,
        );
    },
    create: (payload: Record<string, unknown>) => apiClient.post<Record<string, unknown>>(baseUrl, payload),
    show: (id: number | string) => apiClient.get<Record<string, unknown>>(`${baseUrl}/${id}`),
    update: (id: number | string, payload: Record<string, unknown>) => apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    updateEstado: (id: number | string, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/estado`, {
            estado,
        }),
};

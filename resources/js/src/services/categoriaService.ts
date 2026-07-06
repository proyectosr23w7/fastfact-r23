import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/inventario/categorias';

export const categoriaService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    create: (payload: Record<string, unknown>) => apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) => apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    updateEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/estado`, { estado }),
    remove: (id: number) => apiClient.delete(`${baseUrl}/${id}`),
};

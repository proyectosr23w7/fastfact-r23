import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/seguridad/permisos';

export const permisoService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    show: (id: number) => apiClient.get<Record<string, unknown>>(`${baseUrl}/${id}`),
    create: (payload: Record<string, unknown>) => apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) => apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    updateEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/estado`, { estado }),
};

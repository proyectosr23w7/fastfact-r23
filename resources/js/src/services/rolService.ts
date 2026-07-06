import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/seguridad/roles';

export const rolService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    show: (id: number) => apiClient.get<Record<string, unknown>>(`${baseUrl}/${id}`),
    create: (payload: Record<string, unknown>) => apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) => apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    updateEstado: (id: number, estado: boolean) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/estado`, { estado }),
    assignPermissions: (id: number, permissionIds: number[]) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/permisos`, { permission_ids: permissionIds }),
};

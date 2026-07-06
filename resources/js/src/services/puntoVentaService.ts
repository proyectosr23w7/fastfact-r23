import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/configuracion/puntos-venta';

export const puntoVentaService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    create: (payload: Record<string, unknown>) => apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) => apiClient.put<Record<string, unknown>>(`${baseUrl}/${id}`, payload),
    remove: (id: number) => apiClient.delete(`${baseUrl}/${id}`),
};

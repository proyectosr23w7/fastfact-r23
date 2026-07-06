import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/configuracion/configuracion';

const hasFirmaDigitalFile = (payload: Record<string, unknown>) =>
    payload.firma_digital_archivo instanceof File;

export const configuracionService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    create: (payload: Record<string, unknown>) =>
        hasFirmaDigitalFile(payload)
            ? apiClient.postForm<Record<string, unknown>>(baseUrl, payload)
            : apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) =>
        hasFirmaDigitalFile(payload)
            ? apiClient.putForm<Record<string, unknown>>(
                  `${baseUrl}/${id}`,
                  payload,
              )
            : apiClient.put<Record<string, unknown>>(
                  `${baseUrl}/${id}`,
                  payload,
              ),
    remove: (id: number) => apiClient.delete(`${baseUrl}/${id}`),
};

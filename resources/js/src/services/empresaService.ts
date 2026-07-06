import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/configuracion/empresa';

const hasLogoFile = (payload: Record<string, unknown>) =>
    payload.logo instanceof File;

export const empresaService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    create: (payload: Record<string, unknown>) =>
        hasLogoFile(payload)
            ? apiClient.postForm<Record<string, unknown>>(baseUrl, payload)
            : apiClient.post<Record<string, unknown>>(baseUrl, payload),
    update: (id: number, payload: Record<string, unknown>) =>
        hasLogoFile(payload)
            ? apiClient.putForm<Record<string, unknown>>(
                  `${baseUrl}/${id}`,
                  payload,
              )
            : apiClient.put<Record<string, unknown>>(
                  `${baseUrl}/${id}`,
                  payload,
              ),
};

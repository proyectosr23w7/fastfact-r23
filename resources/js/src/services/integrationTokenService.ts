import { apiClient } from '@/src/services/apiClient';

const baseUrl = '/api/seguridad/tokens-integracion';

export const integrationTokenService = {
    list: () => apiClient.get<Record<string, unknown>[]>(baseUrl),
    create: (payload: Record<string, unknown>) =>
        apiClient.post<Record<string, unknown>>(baseUrl, payload),
    revoke: (id: number) =>
        apiClient.patch<Record<string, unknown>>(`${baseUrl}/${id}/revocar`, {}),
};

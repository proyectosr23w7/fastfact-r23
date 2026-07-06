import { http } from '@/services/http';
import type { Sucursal } from '@/types';

interface SucursalListResponse {
    data: Sucursal[];
}

export const sucursalService = {
    list() {
        return http.get<SucursalListResponse>('/api/configuracion/sucursales');
    },
};

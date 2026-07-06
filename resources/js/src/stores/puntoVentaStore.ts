import { createCrudStore } from '@/src/stores/createCrudStore';
import { puntoVentaService } from '@/src/services/puntoVentaService';

export const usePuntoVentaStore = () =>
    createCrudStore(puntoVentaService, () => ({
        sucursal_id: '',
        codigo: 0,
        nombre: '',
        descripcion: '',
        tipo_impresion: 'ticket',
        estado: true,
    }));

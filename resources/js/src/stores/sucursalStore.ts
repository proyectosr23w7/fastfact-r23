import { createCrudStore } from '@/src/stores/createCrudStore';
import { sucursalService } from '@/src/services/sucursalService';

export const useSucursalStore = () =>
    createCrudStore(sucursalService, () => ({
        codigo: 0,
        nombre: '',
        direccion: '',
        telefono: '',
        estado: true,
    }));

import { createCrudStore } from '@/src/stores/createCrudStore';
import { marcaService } from '@/src/services/marcaService';

export function useMarcaStore() {
    const base = createCrudStore(marcaService, () => ({
        nombre: '',
        descripcion: '',
        estado: true,
    }));

    const toggleEstado = async (item: Record<string, unknown>) => {
        await marcaService.updateEstado(Number(item.id), !Boolean(item.estado));
        await base.load();
    };

    return {
        ...base,
        toggleEstado,
    };
}

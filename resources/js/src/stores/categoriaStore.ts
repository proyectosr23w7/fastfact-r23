import { createCrudStore } from '@/src/stores/createCrudStore';
import { categoriaService } from '@/src/services/categoriaService';

export function useCategoriaStore() {
    const base = createCrudStore(categoriaService, () => ({
        nombre: '',
        descripcion: '',
        estado: true,
    }));

    const toggleEstado = async (item: Record<string, unknown>) => {
        await categoriaService.updateEstado(Number(item.id), !Boolean(item.estado));
        await base.load();
    };

    return {
        ...base,
        toggleEstado,
    };
}

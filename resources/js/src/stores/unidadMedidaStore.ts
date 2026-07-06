import { createCrudStore } from '@/src/stores/createCrudStore';
import { unidadMedidaService } from '@/src/services/unidadMedidaService';

export function useUnidadMedidaStore() {
    const base = createCrudStore(unidadMedidaService, () => ({
        nombre: '',
        abreviatura: '',
        estado: true,
    }));

    const toggleEstado = async (item: Record<string, unknown>) => {
        await unidadMedidaService.updateEstado(Number(item.id), !Boolean(item.estado));
        await base.load();
    };

    return {
        ...base,
        toggleEstado,
    };
}

import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';
import { permisoService } from '@/src/services/permisoService';

const initialForm = () => ({
    nombre: '',
    slug: '',
    modulo: '',
    descripcion: '',
    estado: true,
});

export function usePermisoStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: initialForm(),
        loading: false,
        saving: false,
        errors: {} as Record<string, string[]>,
        editingId: null as number | null,
    });

    const load = async () => {
        state.loading = true;

        try {
            const response = await permisoService.list();
            state.items = response.data;
            state.meta = response.meta ?? {};
        } finally {
            state.loading = false;
        }
    };

    const resetForm = () => {
        state.form = initialForm();
        state.errors = {};
        state.editingId = null;
    };

    const startCreate = () => resetForm();

    const startEdit = (item: Record<string, unknown>) => {
        state.editingId = Number(item.id);
        state.errors = {};
        state.form = {
            nombre: String(item.nombre ?? ''),
            slug: String(item.slug ?? ''),
            modulo: String(item.modulo ?? ''),
            descripcion: String(item.descripcion ?? ''),
            estado: Boolean(item.estado ?? true),
        };
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};

        try {
            if (state.editingId) {
                await permisoService.update(state.editingId, { ...state.form });
            } else {
                await permisoService.create({ ...state.form });
            }

            await load();
            resetForm();
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
            } else {
                throw error;
            }
        } finally {
            state.saving = false;
        }
    };

    const toggleEstado = async (item: Record<string, unknown>) => {
        await permisoService.updateEstado(Number(item.id), !Boolean(item.estado));
        await load();
    };

    return {
        state,
        load,
        save,
        resetForm,
        startCreate,
        startEdit,
        toggleEstado,
    };
}

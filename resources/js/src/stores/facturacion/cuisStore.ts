import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';
import { facturacionService } from '@/src/services/facturacionService';

export function useCuisStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: {
            sucursal_id: '',
            punto_venta_id: '',
        },
        loading: false,
        saving: false,
        errors: {} as Record<string, string[]>,
    });

    const resetForm = () => {
        state.form = { sucursal_id: '', punto_venta_id: '' };
        state.errors = {};
    };

    const load = async () => {
        state.loading = true;
        try {
            const response = await facturacionService.listCuis();
            state.items = response.data;
            state.meta = response.meta ?? {};
        } finally {
            state.loading = false;
        }
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};

        try {
            await facturacionService.createCuis({
                sucursal_id: Number(state.form.sucursal_id),
                punto_venta_id: Number(state.form.punto_venta_id),
            });
            await load();
            resetForm();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                return false;
            }

            throw error;
        } finally {
            state.saving = false;
        }
    };

    return { state, load, save, resetForm };
}

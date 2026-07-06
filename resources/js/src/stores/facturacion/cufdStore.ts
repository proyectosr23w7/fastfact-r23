import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';
import { facturacionService } from '@/src/services/facturacionService';

export function useCufdStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        filters: {
            sucursal_id: '',
            punto_venta_id: '',
            ambiente_facturacion: '',
            estado: '',
        },
        form: {
            sucursal_id: '',
            punto_venta_id: '',
            forzar_nuevo: true,
        },
        loading: false,
        saving: false,
        errors: {} as Record<string, string[]>,
        generalError: '',
        successMessage: '',
    });

    const resetForm = () => {
        state.form = {
            sucursal_id: '',
            punto_venta_id: '',
            forzar_nuevo: true,
        };
        state.errors = {};
        state.generalError = '';
        state.successMessage = '';
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';
        try {
            const response = await facturacionService.listCufd(state.filters);
            state.items = response.data;
            state.meta = response.meta ?? {};
            if (!state.filters.ambiente_facturacion) {
                state.filters.ambiente_facturacion = String((state.meta.siat as Record<string, any> | undefined)?.environment?.key ?? '');
            }
        } catch (error) {
            state.generalError = error instanceof Error ? error.message : 'No se pudo cargar el historial de CUFD.';
        } finally {
            state.loading = false;
        }
    };

    const clearFilters = async () => {
        state.filters = {
            sucursal_id: '',
            punto_venta_id: '',
            ambiente_facturacion: String((state.meta.siat as Record<string, any> | undefined)?.environment?.key ?? ''),
            estado: '',
        };
        await load();
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};
        state.generalError = '';
        state.successMessage = '';

        try {
            await facturacionService.createCufd({
                sucursal_id: state.form.sucursal_id ? Number(state.form.sucursal_id) : null,
                punto_venta_id: state.form.punto_venta_id ? Number(state.form.punto_venta_id) : null,
                forzar_nuevo: true,
            });
            await load();
            resetForm();
            state.successMessage = 'Solicitud de CUFD procesada correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }

            throw error;
        } finally {
            state.saving = false;
        }
    };

    return { state, load, save, resetForm, clearFilters };
}

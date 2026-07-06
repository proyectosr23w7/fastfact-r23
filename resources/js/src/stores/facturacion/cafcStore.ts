import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';
import { facturacionService } from '@/src/services/facturacionService';

export function useCafcStore() {
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
            id: null as number | null,
            codigo: '',
            pin: '',
            descripcion: '',
            sucursal_id: '',
            punto_venta_id: '',
            ambiente_facturacion: '',
            fecha_inicio_vigencia: '',
            fecha_fin_vigencia: '',
            numero_inicial: '',
            numero_final: '',
            observacion: '',
        },
        loading: false,
        saving: false,
        updatingEstadoId: null as number | null,
        errors: {} as Record<string, string[]>,
        generalError: '',
        successMessage: '',
    });

    const resetForm = () => {
        state.form = {
            id: null,
            codigo: '',
            pin: '',
            descripcion: '',
            sucursal_id: '',
            punto_venta_id: '',
            ambiente_facturacion: '',
            fecha_inicio_vigencia: '',
            fecha_fin_vigencia: '',
            numero_inicial: '',
            numero_final: '',
            observacion: '',
        };
        state.errors = {};
        state.generalError = '';
        state.successMessage = '';
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';
        try {
            const response = await facturacionService.listCafc(state.filters);
            state.items = response.data;
            state.meta = response.meta ?? {};
            if (!state.filters.ambiente_facturacion) {
                state.filters.ambiente_facturacion = String((state.meta.siat as Record<string, any> | undefined)?.environment?.key ?? 'piloto');
            }
        } catch (error) {
            state.generalError = error instanceof Error ? error.message : 'No se pudieron cargar los CAFC.';
        } finally {
            state.loading = false;
        }
    };

    const clearFilters = async () => {
        state.filters = {
            sucursal_id: '',
            punto_venta_id: '',
            ambiente_facturacion: String((state.meta.siat as Record<string, any> | undefined)?.environment?.key ?? 'piloto'),
            estado: '',
        };
        await load();
    };

    const startEdit = (item: Record<string, unknown>) => {
        state.form = {
            id: Number(item.id),
            codigo: String(item.codigo ?? ''),
            pin: String(item.pin ?? ''),
            descripcion: String(item.descripcion ?? ''),
            sucursal_id: String(item.sucursal_id ?? ''),
            punto_venta_id: String(item.punto_venta_id ?? ''),
            ambiente_facturacion: String(item.ambiente_facturacion ?? ''),
            fecha_inicio_vigencia: String(item.fecha_inicio_vigencia ?? '').slice(0, 16),
            fecha_fin_vigencia: String(item.fecha_fin_vigencia ?? '').slice(0, 16),
            numero_inicial: item.numero_inicial !== null && item.numero_inicial !== undefined ? String(item.numero_inicial) : '',
            numero_final: item.numero_final !== null && item.numero_final !== undefined ? String(item.numero_final) : '',
            observacion: String(item.observacion ?? ''),
        };
        state.errors = {};
        state.generalError = '';
        state.successMessage = '';
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};
        state.generalError = '';

        try {
            const payload = {
                codigo: state.form.codigo,
                pin: state.form.pin || null,
                descripcion: state.form.descripcion || null,
                sucursal_id: state.form.sucursal_id ? Number(state.form.sucursal_id) : null,
                punto_venta_id: state.form.punto_venta_id ? Number(state.form.punto_venta_id) : null,
                ambiente_facturacion: state.form.ambiente_facturacion || null,
                fecha_inicio_vigencia: state.form.fecha_inicio_vigencia || null,
                fecha_fin_vigencia: state.form.fecha_fin_vigencia || null,
                numero_inicial: state.form.numero_inicial !== '' ? Number(state.form.numero_inicial) : null,
                numero_final: state.form.numero_final !== '' ? Number(state.form.numero_final) : null,
                observacion: state.form.observacion || null,
            };

            if (state.form.id) {
                await facturacionService.updateCafc(Number(state.form.id), payload);
            } else {
                await facturacionService.createCafc(payload);
            }
            await load();
            const message = state.form.id ? 'CAFC actualizado correctamente.' : 'CAFC registrado correctamente.';
            resetForm();
            state.successMessage = message;
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

    const updateEstado = async (item: Record<string, unknown>, estado: boolean) => {
        state.updatingEstadoId = Number(item.id);
        state.generalError = '';
        state.successMessage = '';

        try {
            await facturacionService.updateCafcEstado(Number(item.id), estado);
            await load();
            state.successMessage = estado ? 'CAFC activado correctamente.' : 'CAFC desactivado correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.generalError = error.message;
                return false;
            }

            throw error;
        } finally {
            state.updatingEstadoId = null;
        }
    };

    return { state, load, save, updateEstado, resetForm, startEdit, clearFilters };
}

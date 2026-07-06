import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';
import { facturacionService } from '@/src/services/facturacionService';

export function useSiatSyncStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: {
            sucursal_id: '',
            punto_venta_id: '',
        },
        lastSyncResponse: null as Record<string, unknown> | null,
        loading: false,
        syncing: false,
        updating: [] as string[],
        errors: {} as Record<string, string[]>,
        generalError: '',
        generalSuccess: '',
    });

    const load = async (clearMessage = true) => {
        state.loading = true;
        if (clearMessage) {
            state.generalError = '';
        }
        try {
            const response = await facturacionService.listSincronizaciones();
            state.items = response.data;
            state.meta = response.meta ?? {};

            const sucursales = (state.meta.sucursales as Record<string, unknown>[] | undefined) ?? [];
            const puntosVenta = (state.meta.puntos_venta as Record<string, unknown>[] | undefined) ?? [];

            if (!state.form.sucursal_id && sucursales.length === 1) {
                state.form.sucursal_id = String(sucursales[0].id ?? '');
            }

            if (state.form.sucursal_id && !state.form.punto_venta_id) {
                const punto = puntosVenta.find(
                    (item) => String(item.sucursal_id ?? '') === String(state.form.sucursal_id),
                );

                if (punto) {
                    state.form.punto_venta_id = String(punto.id ?? '');
                }
            }
        } catch (error) {
            state.generalError = error instanceof ApiError
                ? error.message
                : 'No se pudo cargar el centro de sincronizacion SIAT.';
        } finally {
            state.loading = false;
        }
    };

    const sync = async () => {
        state.syncing = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.syncCatalogos({
                sucursal_id: Number(state.form.sucursal_id),
                punto_venta_id: Number(state.form.punto_venta_id),
            });
            state.lastSyncResponse = (response.meta?.response as Record<string, unknown> | undefined) ?? null;
            if (response.success === false) {
                state.generalError = String(response.message ?? 'La sincronizacion termino con observaciones.');
            } else {
                state.generalSuccess = String(response.message ?? 'Sincronizacion SIAT finalizada.');
            }
            await load(false);
            return response.success !== false;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }

            state.generalError = error instanceof ApiError
                ? error.message
                : 'No se pudo completar la sincronizacion SIAT.';
            return false;
        } finally {
            state.syncing = false;
        }
    };

    const toggleMetodoPago = async (item: Record<string, unknown>) => {
        state.generalError = '';
        state.generalSuccess = '';
        const key = `metodo-${item.id}`;
        state.updating.push(key);
        try {
            await facturacionService.updateMetodoPagoEstado(Number(item.id), !Boolean(item.habilitado_venta));
            state.generalSuccess = 'Disponibilidad operativa del metodo de pago actualizada.';
            await load(false);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.generalError = error.message;
                return false;
            }

            state.generalError = error instanceof ApiError ? error.message : 'No se pudo actualizar el metodo de pago.';
            return false;
        } finally {
            state.updating = state.updating.filter((itemKey) => itemKey !== key);
        }

        return true;
    };

    const updateMetodoPagoOperativo = async (item: Record<string, unknown>, changes: Record<string, unknown>) => {
        state.generalError = '';
        state.generalSuccess = '';
        const key = `metodo-${item.id}`;
        state.updating.push(key);
        try {
            await facturacionService.updateMetodoPagoOperativo(Number(item.id), changes);
            state.generalSuccess = 'Preferencias operativas del metodo de pago actualizadas.';
            await load(false);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.generalError = error.message;
                return false;
            }

            state.generalError = error instanceof ApiError ? error.message : 'No se pudieron actualizar las preferencias del metodo de pago.';
            return false;
        } finally {
            state.updating = state.updating.filter((itemKey) => itemKey !== key);
        }

        return true;
    };

    const toggleUnidadMedida = async (item: Record<string, unknown>) => {
        state.generalError = '';
        state.generalSuccess = '';
        const key = `unidad-${item.id}`;
        state.updating.push(key);
        try {
            await facturacionService.updateUnidadMedidaEstado(Number(item.id), !Boolean(item.habilitado_uso));
            state.generalSuccess = 'Disponibilidad operativa de la unidad de medida actualizada.';
            await load(false);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.generalError = error.message;
                return false;
            }

            state.generalError = error instanceof ApiError ? error.message : 'No se pudo actualizar la unidad de medida.';
            return false;
        } finally {
            state.updating = state.updating.filter((itemKey) => itemKey !== key);
        }

        return true;
    };

    const isUpdating = (key: string) => state.updating.includes(key);

    return { state, load, sync, toggleMetodoPago, updateMetodoPagoOperativo, toggleUnidadMedida, isUpdating };
}

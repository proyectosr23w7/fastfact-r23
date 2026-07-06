import { ApiError } from '@/src/services/apiClient';
import { facturacionService } from '@/src/services/facturacionService';
import { reactive } from 'vue';

export function useEventoSignificativoStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: {
            sucursal_id: '',
            punto_venta_id: '',
            cufd_evento_id: '',
            cafc_id: '',
            codigo_evento: '',
            descripcion: '',
            fecha_inicio: '',
            observacion_interna: '',
        },
        closeForm: {
            fecha_fin: '',
            observacion_interna: '',
        },
        reportForm: {
            sucursal_id: '',
            punto_venta_id: '',
            tipo_falla: 'cufd',
            descripcion: '',
        },
        loading: false,
        saving: false,
        closing: false,
        reporting: false,
        processingRecoveryId: null as number | null,
        errors: {} as Record<string, string[]>,
        closeErrors: {} as Record<string, string[]>,
        reportErrors: {} as Record<string, string[]>,
        generalError: '',
        generalSuccess: '',
    });

    const resetForm = () => {
        state.form = {
            sucursal_id: '',
            punto_venta_id: '',
            cufd_evento_id: '',
            cafc_id: '',
            codigo_evento: '',
            descripcion: '',
            fecha_inicio: '',
            observacion_interna: '',
        };
        state.errors = {};
    };

    const resetCloseForm = () => {
        state.closeForm = {
            fecha_fin: '',
            observacion_interna: '',
        };
        state.closeErrors = {};
    };

    const resetReportForm = () => {
        state.reportForm = {
            sucursal_id: '',
            punto_venta_id: '',
            tipo_falla: 'cufd',
            descripcion: '',
        };
        state.reportErrors = {};
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';
        try {
            const response = await facturacionService.listEventos();
            state.items = response.data;
            state.meta = response.meta ?? {};
            hydrateDefaultsFromMeta();
            return true;
        } catch (error) {
            state.generalError =
                error instanceof ApiError
                    ? error.message
                    : 'No se pudo cargar la información de contingencias SIAT.';
            return false;
        } finally {
            state.loading = false;
        }
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.createEvento({
                sucursal_id: Number(state.form.sucursal_id),
                punto_venta_id: Number(state.form.punto_venta_id),
                cufd_evento_id: state.form.cufd_evento_id
                    ? Number(state.form.cufd_evento_id)
                    : null,
                cafc_id: state.form.cafc_id ? Number(state.form.cafc_id) : null,
                codigo_evento: state.form.codigo_evento,
                descripcion: state.form.descripcion,
                fecha_inicio: state.form.fecha_inicio,
                observacion_interna: state.form.observacion_interna || null,
            });
            await load();
            resetForm();
            state.generalSuccess =
                response.message ?? 'Contingencia activada correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError) {
                if (error.status === 422) state.errors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError = 'No se pudo activar la contingencia.';
            return false;
        } finally {
            state.saving = false;
        }
    };

    const close = async (id: number) => {
        state.closing = true;
        state.closeErrors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.closeEvento(id, {
                fecha_fin: state.closeForm.fecha_fin,
                observacion_interna:
                    state.closeForm.observacion_interna || null,
            });
            await load();
            resetCloseForm();
            state.generalSuccess =
                response.message ?? 'Contingencia cerrada correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError) {
                if (error.status === 422) state.closeErrors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError = 'No se pudo cerrar la contingencia.';
            return false;
        } finally {
            state.closing = false;
        }
    };

    const report = async () => {
        state.reporting = true;
        state.reportErrors = {};
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.reportEvento({
                sucursal_id: state.reportForm.sucursal_id
                    ? Number(state.reportForm.sucursal_id)
                    : null,
                punto_venta_id: state.reportForm.punto_venta_id
                    ? Number(state.reportForm.punto_venta_id)
                    : null,
                tipo_falla: state.reportForm.tipo_falla,
                descripcion: state.reportForm.descripcion || null,
            });
            await load();
            resetReportForm();
            state.generalSuccess =
                response.message ?? 'Incidencia SIAT reportada correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError) {
                if (error.status === 422) state.reportErrors = error.errors;
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                'No se pudo registrar el reporte de incidencia.';
            return false;
        } finally {
            state.reporting = false;
        }
    };

    const processRecovery = async (id: number) => {
        state.processingRecoveryId = id;
        state.generalError = '';
        state.generalSuccess = '';

        try {
            const response = await facturacionService.processRecoveryEvento(id);
            await load();
            state.generalSuccess =
                response.message ??
                'Recuperacion de contingencia procesada correctamente.';
            return true;
        } catch (error) {
            if (error instanceof ApiError) {
                state.generalError = error.message;
                return false;
            }
            state.generalError =
                'No se pudo procesar la recuperación del evento.';
            return false;
        } finally {
            state.processingRecoveryId = null;
        }
    };

    const hydrateDefaultsFromMeta = () => {
        const sucursales =
            (state.meta.sucursales as Record<string, unknown>[] | undefined) ??
            [];
        const puntos =
            (state.meta.puntos_venta as
                | Record<string, unknown>[]
                | undefined) ?? [];
        const firstSucursal = sucursales[0];

        if (!state.form.sucursal_id && firstSucursal) {
            state.form.sucursal_id = String(firstSucursal.id ?? '');
        }

        const filteredPuntos = puntos.filter(
            (punto) =>
                String(punto.sucursal_id ?? '') ===
                String(state.form.sucursal_id ?? ''),
        );

        if (!state.form.punto_venta_id && filteredPuntos[0]) {
            state.form.punto_venta_id = String(filteredPuntos[0].id ?? '');
        }

        if (!state.reportForm.sucursal_id && state.form.sucursal_id) {
            state.reportForm.sucursal_id = state.form.sucursal_id;
        }

        if (!state.reportForm.punto_venta_id && state.form.punto_venta_id) {
            state.reportForm.punto_venta_id = state.form.punto_venta_id;
        }
    };

    return {
        state,
        load,
        save,
        close,
        report,
        processRecovery,
        resetForm,
        resetCloseForm,
        resetReportForm,
    };
}

import { ApiError } from '@/src/services/apiClient';
import { empresaService } from '@/src/services/empresaService';
import { reactive } from 'vue';

export interface EmpresaForm {
    [key: string]: unknown;
    nombre_empresa: string;
    razon_social: string;
    nit: string;
    propietario: string;
    telefono: string;
    correo: string;
    direccion: string;
    logo: string | File | null;
    estado: boolean;
}

const initialForm = (): EmpresaForm => ({
    nombre_empresa: '',
    razon_social: '',
    nit: '',
    propietario: '',
    telefono: '',
    correo: '',
    direccion: '',
    logo: null,
    estado: true,
});

export const useEmpresaStore = () => {
    const state = reactive({
        item: null as Record<string, unknown> | null,
        form: initialForm(),
        loading: false,
        saving: false,
        editingId: null as number | null,
        errors: {} as Record<string, string[]>,
        successMessage: '',
        generalError: '',
    });

    const startCreate = () => {
        state.editingId = null;
        state.errors = {};
        state.form = initialForm();
    };

    const startEdit = (item: Record<string, unknown>) => {
        state.editingId = Number(item.id);
        state.errors = {};
        state.form = {
            ...initialForm(),
            nombre_empresa: String(item.nombre_empresa ?? ''),
            razon_social: String(item.razon_social ?? ''),
            nit: String(item.nit ?? ''),
            propietario: String(item.propietario ?? ''),
            telefono: String(item.telefono ?? ''),
            correo: String(item.correo ?? ''),
            direccion: String(item.direccion ?? ''),
            logo: item.logo ? String(item.logo) : null,
            estado: Boolean(item.estado),
        };
    };

    const load = async () => {
        state.loading = true;
        state.generalError = '';

        try {
            const response = await empresaService.list();
            state.item = response.data[0] ?? null;

            if (state.item) startEdit(state.item);
            else startCreate();
        } catch (error) {
            state.generalError =
                error instanceof Error
                    ? error.message
                    : 'No se pudo cargar el perfil de empresa.';
        } finally {
            state.loading = false;
        }
    };

    const save = async (payload: Record<string, unknown>): Promise<boolean> => {
        state.saving = true;
        state.errors = {};
        state.successMessage = '';
        state.generalError = '';

        try {
            const response = state.editingId
                ? await empresaService.update(state.editingId, payload)
                : await empresaService.create(payload);

            state.item = response.data;
            startEdit(response.data);
            state.successMessage =
                response.message ?? 'Perfil de empresa guardado correctamente.';

            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                state.errors = error.errors;
                state.generalError = error.message;
            } else {
                state.generalError =
                    error instanceof Error
                        ? error.message
                        : 'No se pudo guardar el perfil de empresa.';
            }

            return false;
        } finally {
            state.saving = false;
        }
    };

    const clearMessages = () => {
        state.successMessage = '';
        state.generalError = '';
    };

    const clearFieldError = (field: string) => {
        delete state.errors[field];
        clearMessages();
    };

    return {
        state,
        load,
        save,
        startCreate,
        startEdit,
        clearMessages,
        clearFieldError,
    };
};

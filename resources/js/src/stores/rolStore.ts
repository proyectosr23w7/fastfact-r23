import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';
import { rolService } from '@/src/services/rolService';

const initialForm = () => ({
    nombre: '',
    slug: '',
    descripcion: '',
    estado: true,
});

const initialPermissionsForm = () => ({
    permission_ids: [] as number[],
});

export function useRolStore() {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: initialForm(),
        permissionsForm: initialPermissionsForm(),
        loading: false,
        saving: false,
        errors: {} as Record<string, string[]>,
        editingId: null as number | null,
        selectedItem: null as Record<string, unknown> | null,
    });

    const load = async () => {
        state.loading = true;

        try {
            const response = await rolService.list();
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
            descripcion: String(item.descripcion ?? ''),
            estado: Boolean(item.estado ?? true),
        };
    };

    const save = async () => {
        state.saving = true;
        state.errors = {};

        try {
            if (state.editingId) {
                await rolService.update(state.editingId, { ...state.form });
            } else {
                await rolService.create({ ...state.form });
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
        await rolService.updateEstado(Number(item.id), !Boolean(item.estado));
        await load();
    };

    const preparePermissions = (item: Record<string, unknown>) => {
        state.selectedItem = item;
        state.permissionsForm = {
            permission_ids: ((item.permissions as Record<string, unknown>[] | undefined) ?? []).map((permission) => Number(permission.id)),
        };
        state.errors = {};
    };

    const assignPermissions = async () => {
        if (!state.selectedItem) return;

        state.saving = true;
        state.errors = {};

        try {
            await rolService.assignPermissions(Number(state.selectedItem.id), state.permissionsForm.permission_ids);
            await load();
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

    return {
        state,
        load,
        save,
        resetForm,
        startCreate,
        startEdit,
        toggleEstado,
        preparePermissions,
        assignPermissions,
    };
}

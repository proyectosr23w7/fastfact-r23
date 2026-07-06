import { ApiError } from '@/src/services/apiClient';
import { usuarioService } from '@/src/services/usuarioService';
import { reactive } from 'vue';

type Item = Record<string, unknown>;

const initialForm = () => ({
    name: '',
    email: '',
    password: '',
    estado: true,
    role_ids: [] as number[],
});

const initialRolesForm = () => ({ role_ids: [] as number[] });

const errorMessage = (error: unknown) =>
    error instanceof ApiError
        ? error.message
        : 'No fue posible completar la operación. Intenta nuevamente.';

export function useUsuarioStore() {
    const state = reactive({
        items: [] as Item[],
        meta: {} as Record<string, unknown>,
        form: initialForm(),
        rolesForm: initialRolesForm(),
        loading: false,
        saving: false,
        errors: {} as Record<string, string[]>,
        error: '',
        success: '',
        editingId: null as number | null,
        selectedItem: null as Item | null,
    });

    const clearFeedback = () => {
        state.errors = {};
        state.error = '';
        state.success = '';
    };

    const load = async () => {
        state.loading = true;
        state.error = '';
        const selectedId = state.selectedItem
            ? Number(state.selectedItem.id)
            : null;

        try {
            const response = await usuarioService.list();
            state.items = response.data;
            state.meta = response.meta ?? {};
            state.selectedItem = selectedId
                ? (state.items.find((item) => Number(item.id) === selectedId) ?? null)
                : state.selectedItem;
        } catch (error) {
            state.error = errorMessage(error);
        } finally {
            state.loading = false;
        }
    };

    const resetForm = () => {
        state.form = initialForm();
        state.errors = {};
        state.editingId = null;
    };

    const startCreate = () => {
        clearFeedback();
        resetForm();
    };

    const startEdit = (item: Item) => {
        clearFeedback();
        state.editingId = Number(item.id);
        state.form = {
            name: String(item.name ?? ''),
            email: String(item.email ?? ''),
            password: '',
            estado: Boolean(item.estado ?? true),
            role_ids: ((item.roles as Item[] | undefined) ?? []).map((role) =>
                Number(role.id),
            ),
        };
    };

    const save = async (): Promise<boolean> => {
        state.saving = true;
        clearFeedback();

        try {
            const payload: Record<string, unknown> = {
                name: state.form.name,
                email: state.form.email,
                estado: state.form.estado,
                role_ids: state.form.role_ids,
            };

            if (state.form.password) payload.password = state.form.password;

            if (state.editingId) {
                await usuarioService.update(state.editingId, payload);
                state.success = 'Usuario actualizado correctamente.';
            } else {
                await usuarioService.create(payload);
                state.success = 'Usuario creado correctamente.';
            }

            await load();
            resetForm();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422)
                state.errors = error.errors;
            state.error = errorMessage(error);
            return false;
        } finally {
            state.saving = false;
        }
    };

    const toggleEstado = async (item: Item): Promise<boolean> => {
        state.saving = true;
        clearFeedback();

        try {
            const nextState = !Boolean(item.estado);
            await usuarioService.updateEstado(Number(item.id), nextState);
            state.success = nextState
                ? 'Usuario activado correctamente.'
                : 'Usuario desactivado correctamente.';
            await load();
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422)
                state.errors = error.errors;
            state.error = errorMessage(error);
            return false;
        } finally {
            state.saving = false;
        }
    };

    const prepareAccess = (item: Item) => {
        state.selectedItem = item;
        state.rolesForm = {
            role_ids: ((item.roles as Item[] | undefined) ?? []).map((role) =>
                Number(role.id),
            ),
        };
        state.errors = {};
    };

    const assignAccess = async (): Promise<boolean> => {
        if (!state.selectedItem) return false;

        state.saving = true;
        clearFeedback();

        try {
            const payload: Record<string, unknown> = {
                role_ids: state.rolesForm.role_ids,
            };
            await usuarioService.assignAccess(
                Number(state.selectedItem.id),
                payload,
            );
            state.success = 'Acceso del usuario guardado correctamente.';
            await load();
            if (state.selectedItem) prepareAccess(state.selectedItem);
            return true;
        } catch (error) {
            if (error instanceof ApiError && error.status === 422)
                state.errors = error.errors;
            state.error = errorMessage(error);
            return false;
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
        prepareAccess,
        assignAccess,
        clearFeedback,
    };
}

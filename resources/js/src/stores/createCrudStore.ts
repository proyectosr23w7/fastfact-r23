import { reactive } from 'vue';
import { ApiError } from '@/src/services/apiClient';

type ServiceContract = {
    list: () => Promise<{ data: Record<string, unknown>[]; meta?: Record<string, unknown> }>;
    create: (payload: Record<string, unknown>) => Promise<{ data: Record<string, unknown> }>;
    update: (id: number, payload: Record<string, unknown>) => Promise<{ data: Record<string, unknown> }>;
    remove: (id: number) => Promise<unknown>;
};

export function createCrudStore(service: ServiceContract, initialForm: () => Record<string, unknown>) {
    const state = reactive({
        items: [] as Record<string, unknown>[],
        meta: {} as Record<string, unknown>,
        form: initialForm(),
        loading: false,
        saving: false,
        editingId: null as number | null,
        errors: {} as Record<string, string[]>,
    });

    const resetForm = () => {
        state.form = initialForm();
        state.errors = {};
        state.editingId = null;
    };

    const load = async () => {
        state.loading = true;

        try {
            const response = await service.list();
            state.items = response.data;
            state.meta = response.meta ?? {};
        } finally {
            state.loading = false;
        }
    };

    const startCreate = () => {
        resetForm();
    };

    const startEdit = (item: Record<string, unknown>) => {
        state.editingId = Number(item.id);
        state.errors = {};
        state.form = {
            ...initialForm(),
            ...item,
        };
    };

    const save = async (payload: Record<string, unknown>) => {
        state.saving = true;
        state.errors = {};

        try {
            if (state.editingId) {
                await service.update(state.editingId, payload);
            } else {
                await service.create(payload);
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

    const destroy = async (id: number) => {
        await service.remove(id);
        await load();
    };

    return {
        state,
        load,
        save,
        destroy,
        startCreate,
        startEdit,
        resetForm,
    };
}

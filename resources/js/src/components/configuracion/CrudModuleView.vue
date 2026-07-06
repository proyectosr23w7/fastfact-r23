<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { computed, onMounted, ref, watch } from 'vue';

type Option = { label: string; value: string | number };
type Field = {
    key: string;
    label: string;
    type?: 'text' | 'email' | 'number' | 'datetime-local' | 'textarea' | 'checkbox' | 'select' | 'password' | 'file';
    placeholder?: string;
    span?: 'full';
    readonly?: boolean;
    accept?: string;
    options?: Option[] | ((meta: Record<string, unknown>) => Option[]);
    showWhen?: (form: Record<string, unknown>, meta: Record<string, unknown>) => boolean;
};

type Column = {
    key: string;
    label: string;
};

const props = defineProps<{
    title: string;
    description: string;
    breadcrumbs: { title: string; href: string }[];
    store: {
        state: {
            items: Record<string, unknown>[];
            meta: Record<string, unknown>;
            form: Record<string, unknown>;
            loading: boolean;
            saving: boolean;
            editingId: number | null;
            errors: Record<string, string[]>;
        };
        load: () => Promise<void>;
        save: (payload: Record<string, unknown>) => Promise<void>;
        destroy: (id: number) => Promise<void>;
        startCreate: () => void;
        startEdit: (item: Record<string, unknown>) => void;
        resetForm: () => void;
    };
    fields: Field[];
    columns: Column[];
    singleton?: boolean;
    allowDelete?: boolean;
    toggleState?: (item: Record<string, unknown>) => Promise<void>;
}>();

const isEditing = computed(() => props.store.state.editingId !== null);
const isDialogOpen = ref(false);
const canCreate = computed(() => !props.singleton || props.store.state.items.length === 0);
const canDelete = computed(() => props.allowDelete ?? true);
const canToggleState = computed(() => typeof props.toggleState === 'function');
const dialogTitle = computed(() => (isEditing.value ? 'Editar registro' : 'Nuevo registro'));
const dialogDescription = computed(() =>
    props.singleton
        ? 'Completa la informacion principal del registro unico de este modulo.'
        : 'Completa la informacion principal del modulo.',
);

const resolveOptions = (field: Field) => {
    if (!field.options) return [];

    return typeof field.options === 'function'
        ? field.options(props.store.state.meta)
        : field.options;
};

const shouldShowField = (field: Field) => {
    if (!field.showWhen) return true;

    return field.showWhen(props.store.state.form, props.store.state.meta);
};

const getValue = (item: Record<string, unknown>, path: string) => {
    return path.split('.').reduce<unknown>((carry, key) => {
        if (carry && typeof carry === 'object' && key in carry) {
            return (carry as Record<string, unknown>)[key];
        }

        return null;
    }, item) ?? '-';
};

const submit = async () => {
    await props.store.save({ ...props.store.state.form });
    isDialogOpen.value = false;
};

const remove = async (item: Record<string, unknown>) => {
    if (!window.confirm('Estas seguro de eliminar este registro?')) return;

    await props.store.destroy(Number(item.id));
};

const toggleState = async (item: Record<string, unknown>) => {
    if (!props.toggleState) return;

    await props.toggleState(item);
};

const openCreate = () => {
    props.store.startCreate();
    isDialogOpen.value = true;
};

const openEdit = (item: Record<string, unknown>) => {
    props.store.startEdit(item);
    isDialogOpen.value = true;
};

const closeDialog = () => {
    isDialogOpen.value = false;
    props.store.resetForm();
};

onMounted(async () => {
    await props.store.load();
});

watch(
    () => [props.store.state.loading, props.store.state.items.length],
    ([loading, itemCount]) => {
        if (!loading && props.singleton && itemCount === 0) {
            openCreate();
        }
    },
);
</script>

<template>
    <ModulePageLayout :title="title" :breadcrumbs="breadcrumbs" :description="description">
        <div class="company-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-border/70 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold">Listado</h2>
                    <p class="text-sm text-muted-foreground">Revision y administracion basica del modulo.</p>
                    <p v-if="singleton" class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-emerald-700">
                        Registro unico por instalacion
                    </p>
                </div>
                <Button v-if="canCreate" type="button" class="company-action-primary" @click="openCreate">
                    Nuevo registro
                </Button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/55 text-left text-foreground">
                        <tr>
                            <th v-for="column in columns" :key="column.key" class="px-4 py-3 font-semibold">
                                {{ column.label }}
                            </th>
                            <th class="px-4 py-3 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="store.state.loading">
                            <td :colspan="columns.length + 1" class="px-4 py-6 text-center text-muted-foreground">
                                Cargando registros...
                            </td>
                        </tr>
                        <tr
                            v-for="item in store.state.items"
                            :key="String(item.id)"
                            class="border-t border-border/60 hover:bg-muted/30"
                        >
                            <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                                {{ getValue(item, column.key) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" class="company-action-secondary" @click="openEdit(item)">
                                        Editar
                                    </Button>
                                    <Button v-if="canToggleState" type="button" variant="outline" @click="toggleState(item)">
                                        {{ item.estado ? 'Desactivar' : 'Activar' }}
                                    </Button>
                                    <Button v-if="canDelete" type="button" variant="destructive" @click="remove(item)">
                                        Eliminar
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!store.state.loading && store.state.items.length === 0">
                            <td :colspan="columns.length + 1" class="px-4 py-6 text-center text-muted-foreground">
                                No hay registros todavia.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-3xl overflow-hidden border-border/80 p-0">
                <div class="company-panel flex max-h-[90vh] flex-col overflow-hidden shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader>
                            <DialogTitle class="text-left text-lg font-semibold text-white">
                                {{ dialogTitle }}
                            </DialogTitle>
                            <DialogDescription class="mt-1 text-left text-sm text-emerald-50/80">
                                {{ dialogDescription }}
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <form class="flex-1 space-y-5 overflow-y-auto p-5 md:p-6" @submit.prevent="submit">
                        <div class="company-form-grid">
                            <div
                                v-for="field in fields.filter(shouldShowField)"
                                :key="field.key"
                                :class="['company-field', field.span === 'full' ? 'md:col-span-2' : '']"
                            >
                                <template v-if="field.type !== 'checkbox'">
                                    <Label :for="field.key" class="company-label">{{ field.label }}</Label>
                                </template>

                                <Input
                                    v-if="!field.type || ['text', 'email', 'number', 'datetime-local', 'password'].includes(field.type)"
                                    :id="field.key"
                                    v-model="store.state.form[field.key]"
                                    :type="field.type === 'number' ? 'number' : field.type || 'text'"
                                    :placeholder="field.placeholder"
                                    :readonly="field.readonly"
                                    :disabled="field.readonly"
                                    class="company-input"
                                />

                                <input
                                    v-else-if="field.type === 'file'"
                                    :id="field.key"
                                    :accept="field.accept"
                                    type="file"
                                    class="company-input file:mr-3 file:rounded-full file:border-0 file:bg-[hsl(150_54%_19%)] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white"
                                    @change="store.state.form[field.key] = ($event.target as HTMLInputElement)?.files?.[0] ?? null"
                                />

                                <textarea
                                    v-else-if="field.type === 'textarea'"
                                    :id="field.key"
                                    v-model="store.state.form[field.key]"
                                    :placeholder="field.placeholder"
                                    :readonly="field.readonly"
                                    :disabled="field.readonly"
                                    class="company-textarea"
                                />

                                <select
                                    v-else-if="field.type === 'select'"
                                    :id="field.key"
                                    v-model="store.state.form[field.key]"
                                    :disabled="field.readonly"
                                    class="company-select"
                                >
                                    <option value="">Seleccione una opcion</option>
                                    <option
                                        v-for="option in resolveOptions(field)"
                                        :key="String(option.value)"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>

                                <label v-else class="company-switch">
                                    <input
                                        v-model="store.state.form[field.key]"
                                        type="checkbox"
                                        :disabled="field.readonly"
                                        class="h-4 w-4 accent-[hsl(150_54%_19%)]"
                                    />
                                    <span class="space-y-1">
                                        <span class="block text-sm font-semibold text-foreground">{{ field.label }}</span>
                                        <span class="block text-sm text-muted-foreground">{{ field.placeholder }}</span>
                                    </span>
                                </label>

                                <p v-if="field.readonly" class="text-xs text-muted-foreground">
                                    Este valor es administrado automaticamente por el sistema.
                                </p>
                                <InputError :message="store.state.errors[field.key]?.[0]" />
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-3">
                            <Button type="button" variant="outline" class="company-action-secondary" @click="closeDialog">
                                Cancelar
                            </Button>
                            <Button type="submit" class="company-action-primary" :disabled="store.state.saving">
                                {{ store.state.saving ? 'Guardando...' : isEditing ? 'Actualizar' : 'Guardar' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { parseExcelRows } from '@/src/utils/excelImport';
import { AlertCircle, FileSpreadsheet, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type ImportResult = {
    total?: number;
    creados?: number;
    actualizados?: number;
    omitidos?: number;
    errores?: { fila: number; mensaje: string }[];
};

const props = defineProps<{
    open: boolean;
    title: string;
    description: string;
    columns: string[];
    importer: (
        rows: Record<string, unknown>[],
    ) => Promise<{ data: Record<string, unknown>; message?: string }>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    imported: [];
}>();

const file = ref<File | null>(null);
const loading = ref(false);
const error = ref('');
const result = ref<ImportResult | null>(null);

const canImport = computed(() => Boolean(file.value) && !loading.value);

const close = () => emit('update:open', false);

const reset = () => {
    file.value = null;
    error.value = '';
    result.value = null;
};

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.[0] ?? null;
    error.value = '';
    result.value = null;
};

const importFile = async () => {
    if (!file.value) return;

    loading.value = true;
    error.value = '';
    result.value = null;

    try {
        const rows = await parseExcelRows(file.value);

        if (rows.length === 0) {
            error.value = 'El archivo no contiene filas para importar.';
            return;
        }

        const response = await props.importer(rows);
        result.value = response.data as ImportResult;
        emit('imported');
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'No se pudo importar el archivo.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileSpreadsheet class="size-5 text-[#168447]" />
                    {{ title }}
                </DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    class="rounded-lg border border-[#dfe7e2] bg-[#f8fbf9] p-3 text-sm text-[#536158]"
                >
                    <p class="font-semibold text-[#27352d]">
                        Columnas reconocidas
                    </p>
                    <p class="mt-1">{{ columns.join(', ') }}</p>
                </div>

                <Input
                    type="file"
                    accept=".xlsx,.xls,.csv"
                    @change="onFileChange"
                />

                <slot name="options" />

                <div
                    v-if="error"
                    class="flex gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{{ error }}</span>
                </div>

                <div
                    v-if="result"
                    class="rounded-lg border border-[#A8D2B7] bg-[#F0FAF4] p-3 text-sm text-[#126B3B]"
                >
                    <p class="font-semibold">Importacion procesada</p>
                    <p>
                        Total {{ result.total ?? 0 }}. Creados
                        {{ result.creados ?? 0 }}. Actualizados
                        {{ result.actualizados ?? 0 }}. Omitidos
                        {{ result.omitidos ?? 0 }}.
                    </p>
                    <ul
                        v-if="result.errores?.length"
                        class="mt-2 max-h-32 overflow-y-auto text-red-700"
                    >
                        <li
                            v-for="item in result.errores"
                            :key="`${item.fila}-${item.mensaje}`"
                        >
                            Fila {{ item.fila }}: {{ item.mensaje }}
                        </li>
                    </ul>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="close"
                        >Cerrar</Button
                    >
                    <Button type="button" variant="outline" @click="reset"
                        >Limpiar</Button
                    >
                    <Button
                        type="button"
                        class="bg-[#168447] text-white hover:bg-[#126B3B]"
                        :disabled="!canImport"
                        @click="importFile"
                    >
                        <Upload class="size-4" />
                        {{ loading ? 'Importando...' : 'Importar' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

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
import { Label } from '@/components/ui/label';
import { Ban, LoaderCircle, TriangleAlert } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    factura: Record<string, unknown> | null;
    form: {
        codigo_motivo_anulacion: string | number;
        descripcion_motivo: string;
    };
    motivos: Record<string, unknown>[];
    errors: Record<string, string[]>;
    processing: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [];
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-2xl border-border/80 p-0">
            <div class="overflow-hidden rounded-xl bg-white shadow-none">
                <div
                    class="border-b border-red-900/20 bg-[#101713] px-5 py-5 text-white"
                >
                    <DialogHeader>
                        <DialogTitle
                            class="text-left text-lg font-semibold text-white"
                            >Anular factura</DialogTitle
                        >
                        <DialogDescription class="text-left text-emerald-50/80">
                            La solicitud se enviara al SIAT y solo se marcara
                            anulada si existe confirmacion.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form
                    class="space-y-5 p-5 md:p-6"
                    @submit.prevent="emit('submit')"
                >
                    <div
                        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        <TriangleAlert class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p class="font-bold">
                                Factura
                                {{
                                    factura?.numero_factura ||
                                    factura?.id ||
                                    '-'
                                }}
                            </p>
                            <p class="mt-1">
                                Esta accion fiscal no se puede deshacer desde el
                                sistema. Verifica el motivo antes de continuar.
                            </p>
                        </div>
                    </div>
                    <div class="company-field">
                        <Label
                            for="codigo_motivo_anulacion"
                            class="company-label"
                            >Motivo de anulacion</Label
                        >
                        <select
                            id="codigo_motivo_anulacion"
                            v-model="form.codigo_motivo_anulacion"
                            class="company-select"
                        >
                            <option value="">Seleccione un motivo</option>
                            <option
                                v-for="motivo in motivos"
                                :key="String(motivo.codigo_clasificador)"
                                :value="String(motivo.codigo_clasificador)"
                            >
                                {{ motivo.descripcion }}
                            </option>
                        </select>
                        <InputError
                            :message="errors.codigo_motivo_anulacion?.[0]"
                        />
                    </div>

                    <div class="company-field">
                        <Label for="descripcion_motivo" class="company-label"
                            >Descripcion complementaria</Label
                        >
                        <textarea
                            id="descripcion_motivo"
                            v-model="form.descripcion_motivo"
                            class="company-textarea"
                        />
                        <InputError :message="errors.descripcion_motivo?.[0]" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            class="company-action-secondary"
                            @click="emit('update:open', false)"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            class="bg-red-600 text-white hover:bg-red-700"
                            :disabled="processing"
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            /><Ban v-else class="size-4" />
                            {{
                                processing
                                    ? 'Enviando al SIAT...'
                                    : 'Solicitar anulacion'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Check, Search, UserRound, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    clientes: Record<string, unknown>[];
    disabled?: boolean;
    inputId?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [];
    notFound: [documento: string];
}>();

const search = ref('');
const isOpen = ref(false);
const activeIndex = ref(-1);
const inputRef = ref<HTMLInputElement | null>(null);
let blurTimeout: ReturnType<typeof setTimeout> | null = null;

const listboxId = computed(
    () => `${props.inputId ?? 'cliente-search'}-listbox`,
);
const findCliente = (value: string) =>
    props.clientes.find((cliente) => String(cliente.id) === String(value));
const normalizeDocument = (value: unknown) =>
    String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[\s-]+/g, '');

const clienteDocument = (cliente: Record<string, unknown>) => {
    const documento = String(cliente.nit_ci ?? '').trim() || 'SIN DOC';
    const complemento = String(cliente.complemento ?? '').trim();
    return complemento ? `${documento}-${complemento}` : documento;
};

const clienteLabel = (cliente: Record<string, unknown>) =>
    `${clienteDocument(cliente)} - ${String(cliente.razon_social ?? '').trim() || 'SIN RAZÓN SOCIAL'}`;

const filteredClientes = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.clientes.slice(0, 25);

    return props.clientes
        .filter((cliente) =>
            [
                cliente.nit_ci,
                cliente.complemento,
                cliente.razon_social,
                cliente.codigo,
            ]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(term)),
        )
        .sort((a, b) => {
            const aStarts = String(a.nit_ci ?? '')
                .toLowerCase()
                .startsWith(term)
                ? 0
                : 1;
            const bStarts = String(b.nit_ci ?? '')
                .toLowerCase()
                .startsWith(term)
                ? 0
                : 1;
            return aStarts - bStarts;
        })
        .slice(0, 25);
});

const selectCliente = (cliente: Record<string, unknown>) => {
    if (blurTimeout) clearTimeout(blurTimeout);
    emit('update:modelValue', String(cliente.id));
    search.value = clienteLabel(cliente);
    isOpen.value = false;
    activeIndex.value = -1;
    emit('select');
};

const clearCliente = async () => {
    emit('update:modelValue', '');
    search.value = '';
    isOpen.value = true;
    await nextTick();
    inputRef.value?.focus();
};

const handleBlur = () => {
    blurTimeout = setTimeout(() => {
        isOpen.value = false;
        activeIndex.value = -1;
        const term = search.value.trim();

        if (!term) {
            emit('update:modelValue', '');
            return;
        }

        const selectedCliente = findCliente(props.modelValue);
        if (selectedCliente && term === clienteLabel(selectedCliente)) return;

        const exactDocumentMatch = props.clientes.find(
            (cliente) =>
                normalizeDocument(cliente.nit_ci) === normalizeDocument(term),
        );

        if (exactDocumentMatch) {
            selectCliente(exactDocumentMatch);
            return;
        }

        emit('update:modelValue', '');
        emit('notFound', term);
    }, 180);
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        isOpen.value = true;
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            filteredClientes.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    } else if (
        event.key === 'Enter' &&
        isOpen.value &&
        activeIndex.value >= 0
    ) {
        event.preventDefault();
        const cliente = filteredClientes.value[activeIndex.value];
        if (cliente) selectCliente(cliente);
    } else if (event.key === 'Escape') {
        isOpen.value = false;
        activeIndex.value = -1;
    }
};

watch(search, () => {
    activeIndex.value = -1;
});

watch(
    () => [props.modelValue, props.clientes] as const,
    ([value]) => {
        const cliente = findCliente(value);
        search.value = cliente ? clienteLabel(cliente) : '';
    },
    { immediate: true },
);
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-3.5 left-3 z-10 size-4 text-[#68766D]"
            aria-hidden="true"
        />
        <input
            :id="inputId"
            ref="inputRef"
            v-model="search"
            type="text"
            role="combobox"
            autocomplete="off"
            :disabled="disabled"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :aria-activedescendant="
                activeIndex >= 0
                    ? `${listboxId}-option-${activeIndex}`
                    : undefined
            "
            class="h-11 w-full rounded-lg border border-[#CDD9D1] bg-white pr-10 pl-10 text-sm transition outline-none placeholder:text-[#8A978F] focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20"
            placeholder="Buscar por NIT, CI o razón social"
            @focus="isOpen = true"
            @blur="handleBlur"
            @keydown="handleKeydown"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute top-2 right-2 flex size-7 items-center justify-center rounded-md text-[#68766D] hover:bg-[#F1F8F4] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none"
            aria-label="Quitar cliente seleccionado"
            @mousedown.prevent
            @click="clearCliente"
        >
            <X class="size-4" aria-hidden="true" />
        </button>

        <div
            v-if="isOpen && !disabled"
            :id="listboxId"
            role="listbox"
            class="absolute z-40 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-[#DDE7E0] bg-white p-1 shadow-xl"
        >
            <button
                v-for="(cliente, index) in filteredClientes"
                :id="`${listboxId}-option-${index}`"
                :key="String(cliente.id)"
                type="button"
                role="option"
                :aria-selected="String(cliente.id) === String(modelValue)"
                :class="[
                    'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition',
                    activeIndex === index
                        ? 'bg-[#EAF7EF]'
                        : 'hover:bg-[#F5F8F6]',
                ]"
                @mousedown.prevent
                @mouseenter="activeIndex = index"
                @click="selectCliente(cliente)"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#EAF7EF] text-[#168447]"
                >
                    <UserRound class="size-4" aria-hidden="true" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium text-[#101713]">{{
                        String(cliente.razon_social ?? '') || 'Sin razón social'
                    }}</span>
                    <span class="block text-xs text-[#68766D]"
                        >NIT / CI {{ clienteDocument(cliente) }}</span
                    >
                </span>
                <Check
                    v-if="String(cliente.id) === String(modelValue)"
                    class="size-4 text-[#168447]"
                    aria-hidden="true"
                />
            </button>
            <div
                v-if="filteredClientes.length === 0"
                class="px-3 py-4 text-center text-sm text-[#68766D]"
            >
                No se encontraron clientes. Al salir podrás registrarlo.
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Barcode, Check, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    articulos: Record<string, unknown>[];
    disabled?: boolean;
    inputId?: string;
    placeholder?: string;
    inputClass?: string;
    iconClass?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [articulo: Record<string, unknown>];
}>();

const search = ref('');
const isOpen = ref(false);
const activeIndex = ref(-1);
let blurTimeout: ReturnType<typeof setTimeout> | null = null;

const listboxId = computed(
    () => `${props.inputId ?? 'venta-articulo'}-listbox`,
);
const findArticulo = (value: string) =>
    props.articulos.find((articulo) => String(articulo.id) === String(value));
const articuloLabel = (articulo: Record<string, unknown>) => {
    const codigo = String(articulo.codigo_generico ?? '').trim();
    const barras = String(articulo.codigo_barras ?? '').trim();
    const nombre = String(articulo.nombre ?? '').trim();
    return barras
        ? `${codigo} / ${barras} - ${nombre}`
        : `${codigo} - ${nombre}`;
};

const normalize = (value: unknown) =>
    String(value ?? '')
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

const terms = (value: unknown) =>
    Array.isArray(value) ? value.map((item) => String(item ?? '')) : [];

const attributeTerms = (value: unknown) =>
    Array.isArray(value)
        ? value.flatMap((item) => [
              (item as Record<string, unknown>).atributo,
              (item as Record<string, unknown>).valor,
          ])
        : [];

const relationName = (
    articulo: Record<string, unknown>,
    key: 'categoria' | 'marca',
) => normalize((articulo[key] as Record<string, unknown> | null)?.nombre);

const includesTerm = (values: unknown[], term: string) =>
    values.some((value) => normalize(value).includes(term));

const exactTerm = (values: unknown[], term: string) =>
    values.some((value) => normalize(value) === term);

const stockValue = (articulo: Record<string, unknown>) =>
    Number(articulo.stock_actual ?? 0);

const usageValue = (articulo: Record<string, unknown>) =>
    Number(articulo.ventas_count ?? articulo.venta_detalles_count ?? 0);

const searchScore = (articulo: Record<string, unknown>, term: string) => {
    if (
        exactTerm([articulo.codigo_generico, articulo.codigo_barras], term)
    ) {
        return 1;
    }

    if (normalize(articulo.nombre) === term) return 2;

    if (
        includesTerm(
            [
                ...terms(articulo.alias),
                ...terms(articulo.tags),
                ...attributeTerms(articulo.atributos),
            ],
            term,
        )
    )
        return 3;

    if (
        [relationName(articulo, 'categoria'), relationName(articulo, 'marca')]
            .filter(Boolean)
            .some((value) => value.includes(term))
    ) {
        return 4;
    }

    if (normalize(articulo.descripcion).includes(term)) return 5;

    if (
        includesTerm(
            [
                articulo.nombre,
                articulo.codigo_generico,
                articulo.codigo_barras,
            ],
            term,
        )
    ) {
        return 6;
    }

    return 99;
};

const filteredArticulos = computed(() => {
    const term = normalize(search.value);
    if (!term) return props.articulos.slice(0, 25);

    return props.articulos
        .map((articulo) => ({ articulo, score: searchScore(articulo, term) }))
        .filter((item) => item.score < 99)
        .sort((a, b) => {
            if (a.score !== b.score) return a.score - b.score;

            const stockDiff =
                Number(stockValue(b.articulo) > 0) -
                Number(stockValue(a.articulo) > 0);
            if (stockDiff !== 0) return stockDiff;

            const usageDiff =
                usageValue(b.articulo) - usageValue(a.articulo);
            if (usageDiff !== 0) return usageDiff;

            return normalize(a.articulo.nombre).localeCompare(
                normalize(b.articulo.nombre),
                'es',
            );
        })
        .map((item) => item.articulo)
        .slice(0, 25);
});

const selectArticulo = (articulo: Record<string, unknown>) => {
    if (blurTimeout) clearTimeout(blurTimeout);
    emit('update:modelValue', String(articulo.id));
    search.value = articuloLabel(articulo);
    isOpen.value = false;
    activeIndex.value = -1;
    emit('select', articulo);
};

const handleBlur = () => {
    blurTimeout = setTimeout(() => {
        isOpen.value = false;
        activeIndex.value = -1;
        const selected = findArticulo(props.modelValue);
        search.value = selected ? articuloLabel(selected) : '';
    }, 160);
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        isOpen.value = true;
        activeIndex.value = Math.min(
            activeIndex.value + 1,
            filteredArticulos.value.length - 1,
        );
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        return;
    }

    if (event.key === 'Escape') {
        isOpen.value = false;
        activeIndex.value = -1;
        return;
    }

    if (event.key !== 'Enter') return;
    const term = normalize(search.value);
    const exact = props.articulos.find((articulo) =>
        [articulo.codigo_barras, articulo.codigo_generico].some(
            (value) => normalize(value) === term,
        ),
    );
    const articulo =
        exact ??
        filteredArticulos.value[activeIndex.value] ??
        (filteredArticulos.value.length === 1
            ? filteredArticulos.value[0]
            : null);

    if (articulo) {
        event.preventDefault();
        selectArticulo(articulo);
    }
};

watch(search, () => {
    activeIndex.value = -1;
});

watch(
    () => props.modelValue,
    (value) => {
        const articulo = findArticulo(value);
        search.value = articulo ? articuloLabel(articulo) : '';
    },
    { immediate: true },
);
</script>

<template>
    <div class="relative min-w-0">
        <Search
            :class="
                iconClass ??
                'pointer-events-none absolute top-3 left-3 size-4 text-[#68766D]'
            "
            aria-hidden="true"
        />
        <input
            :id="inputId"
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
            :class="
                inputClass ??
                'h-10 w-full min-w-0 rounded-lg border border-[#CDD9D1] bg-white pr-3 pl-9 text-sm transition outline-none placeholder:text-[#8A978F] focus:border-[#168447] focus:ring-2 focus:ring-[#168447]/20'
            "
            :placeholder="
                placeholder ?? 'Producto, servicio, codigo o barras'
            "
            @focus="isOpen = true"
            @blur="handleBlur"
            @keydown="handleKeydown"
        />

        <div
            v-if="isOpen && !disabled"
            :id="listboxId"
            role="listbox"
            class="absolute z-50 mt-2 max-h-72 w-full min-w-[290px] overflow-y-auto rounded-xl border border-[#DDE7E0] bg-white p-1 shadow-xl"
        >
            <button
                v-for="(articulo, index) in filteredArticulos"
                :id="`${listboxId}-option-${index}`"
                :key="String(articulo.id)"
                type="button"
                role="option"
                :aria-selected="String(articulo.id) === String(modelValue)"
                :class="[
                    'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm',
                    activeIndex === index
                        ? 'bg-[#EAF7EF]'
                        : 'hover:bg-[#F5F8F6]',
                ]"
                @mousedown.prevent
                @mouseenter="activeIndex = index"
                @click="selectArticulo(articulo)"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#EAF7EF] text-[#168447]"
                    ><Barcode class="size-4" aria-hidden="true"
                /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium">{{
                        String(articulo.nombre ?? '')
                    }}</span>
                    <span class="block truncate text-xs text-[#68766D]"
                        >{{ String(articulo.codigo_generico ?? '')
                        }}<span v-if="articulo.codigo_barras">
                            ? {{ String(articulo.codigo_barras) }}</span
                        ></span
                    >
                </span>
                <Check
                    v-if="String(articulo.id) === String(modelValue)"
                    class="size-4 text-[#168447]"
                    aria-hidden="true"
                />
            </button>
            <div
                v-if="filteredArticulos.length === 0"
                class="px-3 py-4 text-center text-sm text-[#68766D]"
            >
                No se encontraron productos o servicios.
            </div>
        </div>
    </div>
</template>


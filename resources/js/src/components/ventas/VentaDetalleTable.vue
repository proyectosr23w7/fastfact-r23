<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import VentaArticuloSearchSelect from '@/src/components/ventas/VentaArticuloSearchSelect.vue';
import { AlertTriangle, Minus, Plus, Trash2 } from 'lucide-vue-next';
import { nextTick } from 'vue';

const props = defineProps<{
    detail: Record<string, any>[];
    articulos: Record<string, unknown>[];
    errors: Record<string, string[]>;
    readOnly?: boolean;
    mostrarDescuentoDetalle?: boolean;
    stockResolver?: (articuloId: string) => number;
}>();

const emit = defineEmits<{
    add: [];
    remove: [index: number];
    recalc: [index: number];
}>();

const findArticulo = (item: Record<string, unknown>) =>
    props.articulos.find(
        (articulo) =>
            String(articulo.id ?? '') === String(item.articulo_id ?? ''),
    ) ?? (item.articulo as Record<string, unknown> | undefined);

const articuloNombre = (item: Record<string, unknown>) =>
    String(findArticulo(item)?.nombre ?? '-');

const articuloCodigo = (item: Record<string, unknown>) =>
    String(
        findArticulo(item)?.codigo_generico ??
            findArticulo(item)?.codigo_barras ?? '',
    );

const stockDisponible = (item: Record<string, unknown>) => {
    if (!props.stockResolver || !String(item.articulo_id ?? '')) return 0;
    return props.stockResolver(String(item.articulo_id ?? ''));
};

const cantidadExcedeStock = (item: Record<string, unknown>) =>
    String(item.articulo_id ?? '') !== '' &&
    Number(item.cantidad ?? 0) > stockDisponible(item);

const stockBajo = (item: Record<string, unknown>) => {
    if (!String(item.articulo_id ?? '') || cantidadExcedeStock(item))
        return false;
    return stockDisponible(item) - Number(item.cantidad ?? 0) <= 3;
};

const handleArticuloSelect = async (
    item: Record<string, any>,
    index: number,
) => {
    item.precio_unitario = 0;
    emit('recalc', index);

    await nextTick();
    window.requestAnimationFrame(() =>
        document.getElementById(`venta-cantidad-${index}`)?.focus(),
    );
};

const clearArticulo = async (item: Record<string, any>, index: number) => {
    item.articulo_id = '';
    item.precio_unitario = 0;
    item.total = 0;
    emit('recalc', index);

    await nextTick();
    window.requestAnimationFrame(() =>
        document.getElementById(`venta-articulo-${index}`)?.focus(),
    );
};

const changeQuantity = (
    item: Record<string, any>,
    index: number,
    delta: number,
) => {
    item.cantidad = Math.max(Number(item.cantidad ?? 0) + delta, 0.01);
    emit('recalc', index);
};

const focusNextField = (id: string) => {
    window.requestAnimationFrame(() => document.getElementById(id)?.focus());
};

const subtotalBruto = (item: Record<string, unknown>) =>
    Number(item.cantidad ?? 0) * Number(item.precio_unitario ?? 0);

const mostrarDescuentoDetalle = () => props.mostrarDescuentoDetalle !== false;
</script>

<template>
    <div class="bg-white">
        <div
            class="hidden border-b border-[#E4ECE7] bg-white px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-[#68766D] lg:grid lg:gap-3"
            :class="
                mostrarDescuentoDetalle()
                    ? 'lg:grid-cols-[minmax(240px,1fr)_104px_96px_96px_112px_32px]'
                    : 'lg:grid-cols-[minmax(240px,1fr)_104px_96px_112px_32px]'
            "
        >
            <span>Producto / servicio</span>
            <span class="text-center">Cant.</span>
            <span class="text-right">Precio</span>
            <span v-if="mostrarDescuentoDetalle()" class="text-right">Desc.</span>
            <span class="text-right">Subtotal</span>
            <span />
        </div>

        <div>
            <div
                v-for="(item, index) in detail"
                :key="index"
                class="border-b border-[#EDF2EE] px-4 py-3 transition hover:bg-[#FAFCFB]"
            >
                <div
                    class="grid gap-3 lg:items-center"
                    :class="
                        mostrarDescuentoDetalle()
                            ? 'lg:grid-cols-[minmax(240px,1fr)_104px_96px_96px_112px_32px]'
                            : 'lg:grid-cols-[minmax(240px,1fr)_104px_96px_112px_32px]'
                    "
                >
                    <div class="min-w-0">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF7EF] text-sm font-bold text-[#168447]"
                            >
                                {{ index + 1 }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <template
                                    v-if="
                                        readOnly ||
                                        String(item.articulo_id ?? '')
                                    "
                                >
                                    <div
                                        class="rounded-lg border border-[#E4ECE7] bg-[#FAFCFB] px-3 py-2"
                                    >
                                        <div
                                            class="flex min-w-0 items-start justify-between gap-3"
                                        >
                                            <div class="min-w-0">
                                                <h3
                                                    class="truncate text-sm font-semibold text-[#101713]"
                                                >
                                                    {{ articuloNombre(item) }}
                                                </h3>
                                                <p
                                                    class="mt-0.5 truncate text-xs text-[#68766D]"
                                                >
                                                    {{
                                                        articuloCodigo(item) ||
                                                        'Servicio o producto'
                                                    }}
                                                </p>
                                            </div>
                                            <button
                                                v-if="!readOnly"
                                                type="button"
                                                class="shrink-0 rounded-md border border-[#CFE2D5] bg-white px-2.5 py-1 text-xs font-semibold text-[#126B3B] transition hover:bg-[#EAF7EF] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none"
                                                @click="
                                                    clearArticulo(item, index)
                                                "
                                            >
                                                Cambiar
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <VentaArticuloSearchSelect
                                    v-else
                                    v-model="item.articulo_id"
                                    :articulos="articulos"
                                    :input-id="`venta-articulo-${index}`"
                                    @select="
                                        handleArticuloSelect(item, index)
                                    "
                                />
                                <InputError
                                    :message="
                                        errors[
                                            `detalle.${index}.articulo_id`
                                        ]?.[0]
                                    "
                                />

                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    <span
                                        v-if="cantidadExcedeStock(item) || stockBajo(item)"
                                        :class="[
                                            'rounded-full px-2.5 py-1 font-semibold',
                                            cantidadExcedeStock(item)
                                                ? 'bg-rose-50 text-rose-700'
                                                : 'bg-amber-50 text-amber-700',
                                        ]"
                                    >
                                        Stock:
                                        {{
                                            String(item.articulo_id ?? '')
                                                ? stockDisponible(
                                                      item,
                                                  ).toFixed(2)
                                                : '-'
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="stockBajo(item)"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700"
                                >
                                    <AlertTriangle
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Stock bajo: quedan
                                    {{
                                        Math.max(
                                            stockDisponible(item) -
                                                Number(item.cantidad ?? 0),
                                            0,
                                        ).toFixed(2)
                                    }}
                                    unidades
                                </div>
                                <p
                                    v-if="cantidadExcedeStock(item)"
                                    class="mt-2 text-xs font-medium text-rose-700"
                                >
                                    Supera el stock disponible.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span
                            class="mb-1 block text-xs font-semibold text-[#68766D] lg:hidden"
                        >
                            Cantidad
                        </span>
                        <template v-if="readOnly">
                            <div
                                class="flex h-10 items-center rounded-lg bg-[#F5F8F6] px-3 font-medium"
                            >
                                {{ Number(item.cantidad ?? 0).toFixed(2) }}
                            </div>
                        </template>
                        <div
                            v-else
                            class="flex h-10 overflow-hidden rounded-lg border border-[#CDD9D1] bg-white focus-within:border-[#168447] focus-within:ring-2 focus-within:ring-[#168447]/20"
                        >
                            <button
                                type="button"
                                class="flex w-8 items-center justify-center text-[#435248] hover:bg-[#F1F8F4] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none focus-visible:ring-inset"
                                :aria-label="`Disminuir cantidad del articulo ${index + 1}`"
                                @click="changeQuantity(item, index, -1)"
                            >
                                <Minus class="size-3.5" aria-hidden="true" />
                            </button>
                            <input
                                :id="`venta-cantidad-${index}`"
                                v-model="item.cantidad"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :aria-invalid="cantidadExcedeStock(item)"
                                class="min-w-0 flex-1 border-x border-[#E4ECE7] bg-white px-1 text-center text-sm font-semibold outline-none"
                                @input="emit('recalc', index)"
                                @keydown.enter.prevent="
                                    focusNextField(`venta-precio-${index}`)
                                "
                            />
                            <button
                                type="button"
                                class="flex w-8 items-center justify-center text-[#435248] hover:bg-[#F1F8F4] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none focus-visible:ring-inset"
                                :aria-label="`Aumentar cantidad del articulo ${index + 1}`"
                                @click="changeQuantity(item, index, 1)"
                            >
                                <Plus class="size-3.5" aria-hidden="true" />
                            </button>
                        </div>
                        <InputError
                            :message="errors[`detalle.${index}.cantidad`]?.[0]"
                        />
                    </div>

                    <div>
                        <span
                            class="mb-1 block text-xs font-semibold text-[#68766D] lg:hidden"
                        >
                            Precio
                        </span>
                        <div
                            v-if="readOnly"
                            class="flex h-10 items-center rounded-lg bg-[#F5F8F6] px-3 font-medium"
                        >
                            {{ Number(item.precio_unitario ?? 0).toFixed(2) }}
                        </div>
                        <Input
                            v-else
                            :id="`venta-precio-${index}`"
                            v-model="item.precio_unitario"
                            type="number"
                            step="0.01"
                            min="0"
                            class="h-10 text-right"
                            @update:model-value="emit('recalc', index)"
                            @keydown.enter.prevent="
                                focusNextField(
                                    mostrarDescuentoDetalle()
                                        ? `venta-descuento-${index}`
                                        : `venta-cantidad-${index + 1}`,
                                )
                            "
                        />
                        <InputError
                            :message="
                                errors[
                                    `detalle.${index}.precio_unitario`
                                ]?.[0]
                            "
                        />
                    </div>

                    <div v-if="mostrarDescuentoDetalle()">
                        <span
                            class="mb-1 block text-xs font-semibold text-[#68766D] lg:hidden"
                        >
                            Desc.
                        </span>
                        <div
                            v-if="readOnly"
                            class="flex h-10 items-center rounded-lg bg-[#F5F8F6] px-3 font-medium"
                        >
                            {{ Number(item.descuento ?? 0).toFixed(2) }}
                        </div>
                        <Input
                            v-else
                            :id="`venta-descuento-${index}`"
                            v-model="item.descuento"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="h-10 text-right"
                            @update:model-value="emit('recalc', index)"
                        />
                        <InputError
                            :message="errors[`detalle.${index}.descuento`]?.[0]"
                        />
                    </div>

                    <div class="text-right">
                        <span
                            class="mb-1 block text-xs font-semibold text-[#68766D] lg:hidden"
                        >
                            Subtotal
                        </span>
                        <div class="text-sm font-bold text-[#126B3B]">
                            Bs {{ Number(item.total ?? 0).toFixed(2) }}
                        </div>
                        <div class="text-[10px] text-[#68766D]">
                            <template v-if="mostrarDescuentoDetalle()">
                                {{ Number(subtotalBruto(item)).toFixed(2) }} -
                                {{ Number(item.descuento ?? 0).toFixed(2) }}
                            </template>
                            <template v-else>
                                {{ Number(subtotalBruto(item)).toFixed(2) }}
                            </template>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            v-if="!readOnly"
                            type="button"
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg text-rose-600 transition hover:bg-rose-50 focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:outline-none"
                            :aria-label="`Quitar articulo ${index + 1}`"
                            @click="emit('remove', index)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-[#E4ECE7] bg-[#FAFCFB] px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-md text-xs leading-5 text-[#68766D]">
                    Enter agrega el resultado seleccionado. Si no existe, usa
                    una linea manual facturable.
                </p>
                <button
                    v-if="!readOnly"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-[#A8D2B7] bg-white px-3 py-2 text-sm font-semibold text-[#126B3B] hover:bg-[#EAF7EF] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none"
                    @click="emit('add')"
                >
                    <Plus class="size-4" aria-hidden="true" /> Agregar linea
                </button>
            </div>
            <InputError :message="errors.detalle?.[0]" />
        </div>
    </div>
</template>

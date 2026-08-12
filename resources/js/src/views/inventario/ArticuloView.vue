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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import ImportExcelDialog from '@/src/components/importacion/ImportExcelDialog.vue';
import { useArticuloStore } from '@/src/stores/articuloStore';
import { useAuthStore } from '@/src/stores/authStore';
import {
    BadgeCheck,
    Boxes,
    ChevronLeft,
    ChevronRight,
    EllipsisVertical,
    Filter,
    Package,
    PackageOpen,
    Pencil,
    Plus,
    RefreshCcw,
    Search,
    ShieldCheck,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Item = Record<string, unknown>;

const store = useArticuloStore();
const authStore = useAuthStore();
const isDialogOpen = ref(false);
const isDeleteOpen = ref(false);
const importOpen = ref(false);
const importDefaults = reactive({
    codigo_actividad_economica: '',
    codigo_producto_sin: '',
    codigo_unidad_medida_siat: '',
});
const deleteTarget = ref<Item | null>(null);
const selectedItem = ref<Item | null>(null);
const currentPage = ref(1);
const pageSize = ref(10);

const isEditing = computed(() => store.state.editingId !== null);
const isSuperadmin = computed(() =>
    authStore.roles.value.some((role) => String(role.slug) === 'superadmin'),
);
const categorias = computed(
    () => (store.state.meta.categorias as Item[] | undefined) ?? [],
);
const marcas = computed(
    () => (store.state.meta.marcas as Item[] | undefined) ?? [],
);
const siatActividades = computed(
    () => (store.state.meta.siat_actividades as Item[] | undefined) ?? [],
);
const siatProductos = computed(
    () =>
        (store.state.meta.siat_productos_servicios as Item[] | undefined) ?? [],
);
const siatUnidades = computed(
    () => (store.state.meta.siat_unidades_medida as Item[] | undefined) ?? [],
);
const preciosConfig = computed(
    () =>
        (store.state.meta.configuracion_precios as
            | { habilitado?: boolean }
            | undefined) ?? { habilitado: false },
);
const productConfig = computed(
    () =>
        (store.state.meta.configuracion_productos as
            | {
                  categorias_habilitadas?: boolean;
                  marcas_habilitadas?: boolean;
                  busqueda_avanzada_habilitada?: boolean;
                  codigo_barras_habilitado?: boolean;
              }
            | undefined) ?? {
            categorias_habilitadas: true,
            marcas_habilitadas: false,
            busqueda_avanzada_habilitada: false,
            codigo_barras_habilitado: true,
        },
);
const categoriasHabilitadas = computed(
    () => productConfig.value.categorias_habilitadas !== false,
);
const marcasHabilitadas = computed(
    () => productConfig.value.marcas_habilitadas === true,
);
const busquedaAvanzadaHabilitada = computed(
    () => productConfig.value.busqueda_avanzada_habilitada === true,
);
const codigoBarrasHabilitado = computed(
    () => productConfig.value.codigo_barras_habilitado !== false,
);
const visibleTableColumns = computed(
    () =>
        6 +
        (categoriasHabilitadas.value ? 1 : 0) +
        (marcasHabilitadas.value ? 1 : 0),
);
const searchPlaceholder = computed(() => {
    const base = codigoBarrasHabilitado.value
        ? 'Buscar por nombre, codigo o codigo de barras'
        : 'Buscar por nombre o codigo';

    return busquedaAvanzadaHabilitada.value
        ? `${base}, alias o atributos`
        : base;
});
const productosSiatFiltrados = computed(() => {
    const codigoActividad = String(
        store.state.form.codigo_actividad_economica ?? '',
    );

    if (!codigoActividad) return [];

    return siatProductos.value.filter(
        (producto) =>
            String(producto.codigo_actividad ?? '') === codigoActividad,
    );
});
const productosSiatImportFiltrados = computed(() => {
    const codigoActividad = String(
        importDefaults.codigo_actividad_economica ?? '',
    );

    if (!codigoActividad) return [];

    return siatProductos.value.filter(
        (producto) =>
            String(producto.codigo_actividad ?? '') === codigoActividad,
    );
});
const pageCount = computed(() =>
    Math.max(1, Math.ceil(store.state.items.length / pageSize.value)),
);
const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;
    return store.state.items.slice(start, start + pageSize.value);
});
const visibleFrom = computed(() =>
    store.state.items.length === 0
        ? 0
        : (currentPage.value - 1) * pageSize.value + 1,
);
const visibleTo = computed(() =>
    Math.min(currentPage.value * pageSize.value, store.state.items.length),
);

const openCreate = () => {
    store.startCreate();
    isDialogOpen.value = true;
};

const openEdit = (item: Item) => {
    store.startEdit(item);
    isDialogOpen.value = true;
};

const submit = async () => {
    if (await store.save()) {
        isDialogOpen.value = false;
        syncSelectedItem();
    }
};

const requestDelete = (item: Item) => {
    deleteTarget.value = item;
    isDeleteOpen.value = true;
};

const confirmDelete = async () => {
    if (!deleteTarget.value) return;

    const deletedId = Number(deleteTarget.value.id);
    if (await store.destroy(deletedId)) {
        if (Number(selectedItem.value?.id) === deletedId)
            selectedItem.value = store.state.items[0] ?? null;
        isDeleteOpen.value = false;
        deleteTarget.value = null;
    }
};

const load = async () => {
    currentPage.value = 1;
    await store.load();
    syncSelectedItem();
};

const clearFilters = async () => {
    store.clearFilters();
    await load();
};

const importArticulos = (rows: Record<string, unknown>[]) =>
    store.importRows(rows, {
        codigo_actividad_economica:
            importDefaults.codigo_actividad_economica || null,
        codigo_producto_sin: importDefaults.codigo_producto_sin || null,
        codigo_unidad_medida_siat:
            importDefaults.codigo_unidad_medida_siat || null,
    });

const afterImport = async () => {
    await load();
};

const syncSelectedItem = () => {
    const selectedId = Number(selectedItem.value?.id ?? 0);
    selectedItem.value =
        store.state.items.find((item) => Number(item.id) === selectedId) ??
        store.state.items[0] ??
        null;
};

const categoryName = (item: Item) =>
    String((item.categoria as Item | undefined)?.nombre ?? 'Sin categoría');
const brandName = (item: Item) =>
    String((item.marca as Item | undefined)?.nombre ?? 'Sin marca');
const unitName = (item: Item) =>
    String((item.unidad_medida as Item | undefined)?.abreviatura ?? '-');
const prices = (item: Item) => (item.precios as Item[] | undefined) ?? [];
const siatHomologado = (item: Item) =>
    Boolean(item.homologacion_siat_completa ?? false);
const estadoStatus = (item: Item) =>
    Boolean(item.estado ?? true)
        ? {
              label: 'Activo',
              class: 'bg-green-50 text-green-700 ring-green-200',
          }
        : {
              label: 'Inactivo',
              class: 'bg-slate-50 text-slate-600 ring-slate-200',
          };
const numberValue = (value: unknown) => Number(value ?? 0);
const formatCurrency = (value: unknown) =>
    `Bs ${numberValue(value).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const margin = (item: Item) => {
    const cost = numberValue(item.costo);
    return cost > 0
        ? ((numberValue(item.precio_base) - cost) / cost) * 100
        : null;
};
watch(pageCount, (count) => {
    if (currentPage.value > count) currentPage.value = count;
});

watch(
    () => store.state.form.codigo_actividad_economica,
    (codigoActividad) => {
        const productoActual = String(
            store.state.form.codigo_producto_sin ?? '',
        );
        if (!productoActual) return;

        const productoPertenece = siatProductos.value.some(
            (producto) =>
                String(producto.codigo_producto ?? '') === productoActual &&
                String(producto.codigo_actividad ?? '') ===
                    String(codigoActividad ?? ''),
        );
        if (!productoPertenece) store.state.form.codigo_producto_sin = '';
    },
);

watch(
    () => importDefaults.codigo_actividad_economica,
    (codigoActividad) => {
        const productoActual = String(importDefaults.codigo_producto_sin ?? '');
        if (!productoActual) return;

        const productoPertenece = siatProductos.value.some(
            (producto) =>
                String(producto.codigo_producto ?? '') === productoActual &&
                String(producto.codigo_actividad ?? '') ===
                    String(codigoActividad ?? ''),
        );

        if (!productoPertenece) importDefaults.codigo_producto_sin = '';
    },
);

onMounted(load);
</script>

<template>
    <ModulePageLayout
        compact
        title="Productos"
        description="Catalogo de productos y servicios para facturacion."
        :breadcrumbs="[
            { title: 'Facturacion', href: '/facturacion/facturas' },
            { title: 'Productos', href: '/facturacion/productos' },
        ]"
    >
        <template #actions>
            <Button
                v-if="isSuperadmin"
                variant="outline"
                class="min-h-11 gap-2 border-[#A8D2B7] px-5 text-[#126B3B]"
                @click="importOpen = true"
            >
                <Upload class="size-4" aria-hidden="true" /> Importar
            </Button>
            <Button
                class="company-action-primary min-h-11 gap-2 px-5"
                @click="openCreate"
            >
                <Plus class="size-4" aria-hidden="true" /> Nuevo producto
            </Button>
        </template>

        <div class="space-y-4">
            <section
                class="company-panel p-3 md:p-4"
                aria-label="Filtros de productos"
            >
                <form
                    class="grid gap-3 lg:grid-cols-[minmax(15rem,1.8fr)_minmax(9rem,1fr)_minmax(9rem,1fr)_minmax(10rem,1fr)_auto]"
                    @submit.prevent="load"
                >
                    <label class="relative block">
                        <span class="sr-only">Buscar producto</span>
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#68756d]"
                        />
                        <Input
                            v-model="store.state.search"
                            class="company-input h-11 pl-10"
                            :placeholder="searchPlaceholder"
                        />
                    </label>
                    <label
                        v-if="categoriasHabilitadas"
                        class="inventory-filter-field"
                        ><span>Categoría</span
                        ><select
                            v-model="store.state.filters.categoria_id"
                            class="company-select h-11"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="categoria in categorias"
                                :key="String(categoria.id)"
                                :value="String(categoria.id)"
                            >
                                {{ categoria.nombre }}
                            </option>
                        </select></label
                    >
                    <label
                        v-if="marcasHabilitadas"
                        class="inventory-filter-field"
                        ><span>Marca</span
                        ><select
                            v-model="store.state.filters.marca_id"
                            class="company-select h-11"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="marca in marcas"
                                :key="String(marca.id)"
                                :value="String(marca.id)"
                            >
                                {{ marca.nombre }}
                            </option>
                        </select></label
                    >
                    <div class="flex items-end gap-2">
                        <Button
                            type="submit"
                            class="company-action-primary h-11 flex-1 gap-2 px-5"
                            :disabled="store.state.loading"
                            ><Filter class="size-4" /> Filtrar</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 gap-2 px-4"
                            title="Limpiar filtros"
                            @click="clearFilters"
                            ><RefreshCcw class="size-4" /><span
                                class="sr-only xl:not-sr-only"
                                >Limpiar</span
                            ></Button
                        >
                    </div>
                </form>
            </section>

            <div
                v-if="store.state.loadError || store.state.actionError"
                role="alert"
                class="flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
                <span>{{
                    store.state.loadError || store.state.actionError
                }}</span>
                <button
                    type="button"
                    class="rounded p-1 hover:bg-red-100"
                    aria-label="Cerrar mensaje"
                    @click="
                        store.state.loadError = '';
                        store.state.actionError = '';
                    "
                >
                    <X class="size-4" />
                </button>
            </div>

            <section
                class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_19rem]"
            >
                <div class="company-panel min-w-0 overflow-hidden">
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full min-w-[760px] text-sm">
                            <thead
                                class="border-b border-[#dce4df] bg-[#F5F8F6] text-left text-xs font-semibold text-[#34423a]"
                            >
                                <tr>
                                    <th class="px-4 py-3">Producto</th>
                                    <th class="px-3 py-3">Código</th>
                                    <th
                                        v-if="categoriasHabilitadas"
                                        class="px-3 py-3"
                                    >
                                        Categoría
                                    </th>
                                    <th
                                        v-if="marcasHabilitadas"
                                        class="px-3 py-3"
                                    >
                                        Marca
                                    </th>
                                    <th class="px-3 py-3">Unidad</th>
                                    <th class="px-3 py-3 text-right">
                                        Precio venta
                                    </th>
                                    <th class="px-3 py-3">Estado</th>
                                    <th class="px-4 py-3 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-if="store.state.loading"
                                    v-for="index in 6"
                                    :key="`skeleton-${index}`"
                                    class="border-b border-[#e3e9e5]"
                                >
                                    <td
                                        v-for="cell in visibleTableColumns"
                                        :key="cell"
                                        class="px-3 py-4"
                                    >
                                        <span
                                            class="block h-3 animate-pulse rounded bg-[#e7ece9]"
                                        />
                                    </td>
                                </tr>
                                <tr v-else-if="paginatedItems.length === 0">
                                    <td
                                        :colspan="visibleTableColumns"
                                        class="px-6 py-14 text-center"
                                    >
                                        <PackageOpen
                                            class="mx-auto mb-3 size-9 text-[#8b9890]"
                                        />
                                        <p class="font-semibold text-[#29362f]">
                                            No se encontraron productos
                                        </p>
                                        <p class="mt-1 text-sm text-[#68756d]">
                                            Prueba con otros filtros o registra
                                            un nuevo producto.
                                        </p>
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in paginatedItems"
                                    v-else
                                    :key="String(item.id)"
                                    :class="
                                        Number(selectedItem?.id) ===
                                        Number(item.id)
                                            ? 'bg-[#F0FAF4]'
                                            : 'hover:bg-[#F8FAF9]'
                                    "
                                    class="cursor-pointer border-b border-[#e3e9e5] transition last:border-0"
                                    tabindex="0"
                                    @click="selectedItem = item"
                                    @keydown.enter="selectedItem = item"
                                >
                                    <td class="px-4 py-3">
                                        <div
                                            class="flex min-w-44 items-center gap-3"
                                        >
                                            <span
                                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#EAF7EF] text-[#168447]"
                                                ><Boxes class="size-5"
                                            /></span>
                                            <div>
                                                <p
                                                    class="font-semibold text-[#17221c]"
                                                >
                                                    {{ item.nombre }}
                                                </p>
                                                <p
                                                    class="max-w-48 truncate text-xs text-[#68756d]"
                                                >
                                                    {{
                                                        item.descripcion ||
                                                        'Sin descripción'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-[#3e4c44]">
                                        {{ item.codigo_generico }}
                                    </td>
                                    <td
                                        v-if="categoriasHabilitadas"
                                        class="px-3 py-3"
                                    >
                                        {{ categoryName(item) }}
                                    </td>
                                    <td
                                        v-if="marcasHabilitadas"
                                        class="px-3 py-3"
                                    >
                                        {{ brandName(item) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{ unitName(item) }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-medium whitespace-nowrap"
                                    >
                                        {{ formatCurrency(item.precio_base) }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            :class="estadoStatus(item).class"
                                            class="inline-flex rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                            >{{
                                                estadoStatus(item).label
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-8 gap-1 border-[#cbd8d0] text-[#11733d]"
                                                @click.stop="openEdit(item)"
                                                ><Pencil class="size-3.5" />
                                                Editar</Button
                                            ><DropdownMenu
                                                ><DropdownMenuTrigger as-child
                                                    ><Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8"
                                                        aria-label="Más acciones"
                                                        @click.stop
                                                        ><EllipsisVertical
                                                            class="size-4" /></Button></DropdownMenuTrigger
                                                ><DropdownMenuContent
                                                    align="end"
                                                    ><DropdownMenuItem
                                                        @select="
                                                            store.toggleEstado(
                                                                item,
                                                            )
                                                        "
                                                        >{{
                                                            item.estado
                                                                ? 'Desactivar'
                                                                : 'Activar'
                                                        }}</DropdownMenuItem
                                                    ><DropdownMenuSeparator /><DropdownMenuItem
                                                        class="text-red-600 focus:text-red-700"
                                                        @select="
                                                            requestDelete(item)
                                                        "
                                                        ><Trash2
                                                            class="mr-2 size-4"
                                                        />
                                                        Eliminar</DropdownMenuItem
                                                    ></DropdownMenuContent
                                                ></DropdownMenu
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-[#e3e9e5] lg:hidden">
                        <div
                            v-if="store.state.loading"
                            v-for="index in 4"
                            :key="`mobile-skeleton-${index}`"
                            class="space-y-3 p-4"
                        >
                            <span
                                class="block h-4 w-2/3 animate-pulse rounded bg-[#e7ece9]"
                            /><span
                                class="block h-3 w-full animate-pulse rounded bg-[#edf1ef]"
                            />
                        </div>
                        <div
                            v-else-if="paginatedItems.length === 0"
                            class="px-5 py-12 text-center text-sm text-[#68756d]"
                        >
                            No se encontraron productos.
                        </div>
                        <article
                            v-for="item in paginatedItems"
                            v-else
                            :key="`mobile-${item.id}`"
                            class="p-4"
                            @click="selectedItem = item"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 gap-3">
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF7EF] text-[#168447]"
                                        ><Boxes class="size-5"
                                    /></span>
                                    <div class="min-w-0">
                                        <h3 class="truncate font-semibold">
                                            {{ item.nombre }}
                                        </h3>
                                        <p class="text-xs text-[#68756d]">
                                            {{ item.codigo_generico }} ·
                                            {{ categoryName(item) }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    :class="estadoStatus(item).class"
                                    class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                    >{{ estadoStatus(item).label }}</span
                                >
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div v-if="marcasHabilitadas">
                                    <dt class="text-xs text-[#68756d]">
                                        Marca
                                    </dt>
                                    <dd class="truncate font-medium">
                                        {{ brandName(item) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-[#68756d]">
                                        Precio
                                    </dt>
                                    <dd class="font-semibold whitespace-nowrap">
                                        {{ formatCurrency(item.precio_base) }}
                                    </dd>
                                </div>
                            </dl>
                            <div class="mt-4 flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="flex-1 gap-2 border-[#cbd8d0] text-[#11733d]"
                                    @click.stop="openEdit(item)"
                                    ><Pencil class="size-3.5" /> Editar</Button
                                ><Button
                                    variant="outline"
                                    size="icon"
                                    class="size-9"
                                    aria-label="Eliminar producto"
                                    @click.stop="requestDelete(item)"
                                    ><Trash2 class="size-4 text-red-600"
                                /></Button>
                            </div>
                        </article>
                    </div>

                    <footer
                        v-if="
                            !store.state.loading && store.state.items.length > 0
                        "
                        class="flex flex-col gap-3 border-t border-[#dce4df] px-4 py-3 text-sm text-[#5d6a62] sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p>
                            Mostrando {{ visibleFrom }}–{{ visibleTo }} de
                            {{ store.state.items.length }} productos
                        </p>
                        <div
                            class="flex items-center justify-between gap-3 sm:justify-end"
                        >
                            <label class="flex items-center gap-2"
                                ><span class="hidden sm:inline">Filas</span
                                ><select
                                    v-model="pageSize"
                                    class="h-9 rounded-md border border-[#cbd8d0] bg-white px-2"
                                >
                                    <option :value="10">10</option>
                                    <option :value="20">20</option>
                                    <option :value="50">50</option>
                                </select></label
                            >
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-9"
                                    :disabled="currentPage === 1"
                                    aria-label="Página anterior"
                                    @click="currentPage--"
                                    ><ChevronLeft class="size-4" /></Button
                                ><span class="min-w-20 text-center"
                                    >{{ currentPage }} de {{ pageCount }}</span
                                ><Button
                                    variant="outline"
                                    size="icon"
                                    class="size-9"
                                    :disabled="currentPage === pageCount"
                                    aria-label="Página siguiente"
                                    @click="currentPage++"
                                    ><ChevronRight class="size-4"
                                /></Button>
                            </div>
                        </div>
                    </footer>
                </div>

                <aside
                    class="company-panel self-start overflow-hidden xl:sticky xl:top-4"
                    aria-label="Detalle rapido del producto"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#dce4df] px-4 py-3"
                    >
                        <h2 class="font-bold text-[#17221c]">
                            Detalle del producto
                        </h2>
                        <button
                            v-if="selectedItem"
                            type="button"
                            class="rounded-md p-1 text-[#5d6a62] hover:bg-[#EAF7EF]"
                            aria-label="Cerrar detalle"
                            @click="selectedItem = null"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <div v-if="selectedItem" class="divide-y divide-[#e3e9e5]">
                        <div class="flex gap-3 p-4">
                            <span
                                class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-[#EAF7EF] text-[#168447]"
                                ><Boxes class="size-7"
                            /></span>
                            <div class="min-w-0">
                                <h3 class="font-bold text-[#17221c]">
                                    {{ selectedItem.nombre }}
                                </h3>
                                <p class="text-sm text-[#68756d]">
                                    {{ selectedItem.codigo_generico }}
                                </p>
                                <span
                                    :class="
                                        selectedItem.estado
                                            ? 'bg-[#EAF7EF] text-[#11733d]'
                                            : 'bg-[#eef1ef] text-[#536158]'
                                    "
                                    class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-semibold"
                                    >{{
                                        selectedItem.estado
                                            ? 'Activo'
                                            : 'Inactivo'
                                    }}</span
                                >
                            </div>
                        </div>
                        <dl class="p-4 text-sm">
                            <div class="inventory-detail-row">
                                <dt>Costo promedio</dt>
                                <dd>
                                    {{ formatCurrency(selectedItem.costo) }}
                                </dd>
                            </div>
                            <div class="inventory-detail-row">
                                <dt>Precio de venta</dt>
                                <dd>
                                    {{
                                        formatCurrency(selectedItem.precio_base)
                                    }}
                                </dd>
                            </div>
                            <div class="inventory-detail-row">
                                <dt>Margen de ganancia</dt>
                                <dd
                                    :class="
                                        margin(selectedItem) !== null &&
                                        margin(selectedItem)! >= 0
                                            ? 'text-[#11733d]'
                                            : 'text-red-600'
                                    "
                                    class="font-bold"
                                >
                                    {{
                                        margin(selectedItem) === null
                                            ? 'No disponible'
                                            : `${margin(selectedItem)!.toFixed(2)}%`
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <dl class="p-4 text-sm">
                            <div
                                v-if="categoriasHabilitadas"
                                class="inventory-detail-row"
                            >
                                <dt>Categoría</dt>
                                <dd>{{ categoryName(selectedItem) }}</dd>
                            </div>
                            <div
                                v-if="marcasHabilitadas"
                                class="inventory-detail-row"
                            >
                                <dt>Marca</dt>
                                <dd>{{ brandName(selectedItem) }}</dd>
                            </div>
                            <div class="inventory-detail-row">
                                <dt>Unidad</dt>
                                <dd>{{ unitName(selectedItem) }}</dd>
                            </div>
                            <div
                                v-if="prices(selectedItem).length"
                                class="inventory-detail-row"
                            >
                                <dt>Precios adicionales</dt>
                                <dd>{{ prices(selectedItem).length }}</dd>
                            </div>
                        </dl>
                        <div class="p-4">
                            <h4 class="mb-3 text-sm font-bold">
                                Homologación SIAT
                            </h4>
                            <div
                                class="flex items-center gap-2"
                                :class="
                                    siatHomologado(selectedItem)
                                        ? 'text-[#11733d]'
                                        : 'text-amber-700'
                                "
                            >
                                <ShieldCheck class="size-5" /><span
                                    class="font-semibold"
                                    >{{
                                        siatHomologado(selectedItem)
                                            ? 'Homologación completa'
                                            : 'Homologación pendiente'
                                    }}</span
                                >
                            </div>
                            <dl
                                v-if="siatHomologado(selectedItem)"
                                class="mt-3 space-y-2 rounded-lg bg-[#F5F8F6] p-3 text-xs"
                            >
                                <div>
                                    <dt class="text-[#68756d]">Actividad</dt>
                                    <dd class="font-medium">
                                        {{
                                            selectedItem.codigo_actividad_economica
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[#68756d]">Producto SIN</dt>
                                    <dd class="font-medium">
                                        {{ selectedItem.codigo_producto_sin }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[#68756d]">
                                        Unidad de medida
                                    </dt>
                                    <dd class="font-medium">
                                        {{
                                            selectedItem.codigo_unidad_medida_siat
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <div v-else class="px-5 py-12 text-center">
                        <Package class="mx-auto mb-3 size-9 text-[#9aa69f]" />
                        <p class="font-semibold">Selecciona un producto</p>
                        <p class="mt-1 text-sm text-[#68756d]">
                            Aqui veras sus datos comerciales y homologacion.
                        </p>
                    </div>
                </aside>
            </section>
        </div>

        <Dialog v-model:open="isDeleteOpen">
            <DialogContent class="max-w-md">
                <DialogHeader
                    ><DialogTitle>Eliminar producto</DialogTitle
                    ><DialogDescription
                        >Esta acción eliminará “{{ deleteTarget?.nombre }}”. No
                        puede deshacerse y podría ser rechazada si el producto
                        tiene operaciones relacionadas.</DialogDescription
                    ></DialogHeader
                >
                <div
                    v-if="store.state.actionError"
                    role="alert"
                    class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    {{ store.state.actionError }}
                </div>
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="isDeleteOpen = false"
                        >Cancelar</Button
                    ><Button
                        variant="destructive"
                        :disabled="store.state.deleting"
                        @click="confirmDelete"
                        ><Trash2 class="mr-2 size-4" />{{
                            store.state.deleting ? 'Eliminando...' : 'Eliminar'
                        }}</Button
                    >
                </div>
            </DialogContent>
        </Dialog>

        <ImportExcelDialog
            v-if="isSuperadmin"
            v-model:open="importOpen"
            title="Importar productos"
            description="Carga productos desde Excel. Si ya existe el mismo codigo, se actualiza."
            :columns="[
                'codigo',
                'nombre',
                'precio',
                'unidad_siat',
                'actividad_siat',
                'producto_sin',
                'codigo_barras',
                'categoria',
                'marca',
                'estado',
            ]"
            :importer="importArticulos"
            @imported="afterImport"
        >
            <template #options>
                <section class="space-y-3 rounded-lg border border-[#dfe7e2] bg-white p-3">
                    <div>
                        <p class="font-semibold text-[#27352d]">Homologacion masiva SIAT</p>
                        <p class="mt-1 text-sm text-[#536158]">Estos valores se aplican a las filas que no traigan homologacion en el Excel.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="company-field">
                            <span class="company-label">Actividad economica</span>
                            <select v-model="importDefaults.codigo_actividad_economica" class="company-select">
                                <option value="">Sin asignar</option>
                                <option v-for="actividad in siatActividades" :key="String(actividad.codigo_clasificador)" :value="String(actividad.codigo_clasificador)">
                                    {{ actividad.codigo_clasificador }} - {{ actividad.descripcion }}
                                </option>
                            </select>
                        </label>
                        <label class="company-field">
                            <span class="company-label">Categoria SIAT</span>
                            <select v-model="importDefaults.codigo_producto_sin" class="company-select" :disabled="!importDefaults.codigo_actividad_economica">
                                <option value="">
                                    {{ importDefaults.codigo_actividad_economica ? 'Seleccione una categoria' : 'Seleccione primero actividad' }}
                                </option>
                                <option v-for="producto in productosSiatImportFiltrados" :key="String(producto.codigo_producto)" :value="String(producto.codigo_producto)">
                                    {{ producto.codigo_producto }} - {{ producto.descripcion }}
                                </option>
                            </select>
                        </label>
                        <label class="company-field">
                            <span class="company-label">Tipo de unidad</span>
                            <select v-model="importDefaults.codigo_unidad_medida_siat" class="company-select">
                                <option value="">Sin asignar</option>
                                <option v-for="unidadSiat in siatUnidades" :key="String(unidadSiat.codigo_clasificador)" :value="String(unidadSiat.codigo_clasificador)">
                                    {{ unidadSiat.codigo_clasificador }} - {{ unidadSiat.descripcion }}
                                </option>
                            </select>
                        </label>
                    </div>
                </section>
            </template>
        </ImportExcelDialog>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent
                class="max-h-[94vh] max-w-6xl overflow-y-auto border-border/80 p-0"
            >
                <div class="company-panel overflow-hidden shadow-none">
                    <div class="company-hero px-5 py-3 text-white">
                        <DialogHeader
                            ><DialogTitle
                                class="text-left text-lg font-semibold text-white"
                                >{{
                                    isEditing
                                        ? 'Editar producto'
                                        : 'Nuevo producto'
                                }}</DialogTitle
                            ><DialogDescription
                                class="text-left text-emerald-50/80"
                                >Registra el producto y su estructura comercial
                                base.</DialogDescription
                            ></DialogHeader
                        >
                    </div>
                    <form class="space-y-4 p-4 md:p-5" @submit.prevent="submit">
                        <div
                            v-if="store.state.actionError"
                            role="alert"
                            class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                        >
                            {{ store.state.actionError }}
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div class="company-field">
                                <Label
                                    for="codigo_generico"
                                    class="company-label"
                                    >Código genérico</Label
                                ><Input
                                    id="codigo_generico"
                                    v-model="store.state.form.codigo_generico"
                                    class="company-input"
                                /><InputError
                                    :message="
                                        store.state.errors.codigo_generico?.[0]
                                    "
                                />
                            </div>
                            <div
                                v-if="codigoBarrasHabilitado"
                                class="company-field"
                            >
                                <Label for="codigo_barras" class="company-label"
                                    >Código de barras</Label
                                ><Input
                                    id="codigo_barras"
                                    v-model="store.state.form.codigo_barras"
                                    class="company-input"
                                /><InputError
                                    :message="
                                        store.state.errors.codigo_barras?.[0]
                                    "
                                />
                            </div>
                            <div class="company-field md:col-span-2">
                                <Label for="nombre" class="company-label"
                                    >Nombre</Label
                                ><Input
                                    id="nombre"
                                    v-model="store.state.form.nombre"
                                    class="company-input"
                                /><InputError
                                    :message="store.state.errors.nombre?.[0]"
                                />
                            </div>
                            <div class="company-field md:col-span-2">
                                <Label for="descripcion" class="company-label"
                                    >Descripción</Label
                                ><textarea
                                    id="descripcion"
                                    v-model="store.state.form.descripcion"
                                    class="company-textarea min-h-16"
                                /><InputError
                                    :message="
                                        store.state.errors.descripcion?.[0]
                                    "
                                />
                            </div>
                            <div
                                v-if="busquedaAvanzadaHabilitada"
                                class="company-field"
                            >
                                <Label for="tags" class="company-label"
                                    >Palabras clave</Label
                                ><textarea
                                    id="tags"
                                    v-model="store.state.form.tags"
                                    class="company-textarea min-h-16"
                                    placeholder="Ej: dolor, frio, escolar"
                                /><InputError
                                    :message="store.state.errors.tags?.[0]"
                                />
                            </div>
                            <div
                                v-if="busquedaAvanzadaHabilitada"
                                class="company-field"
                            >
                                <Label for="alias" class="company-label"
                                    >Alias / sinonimos</Label
                                ><textarea
                                    id="alias"
                                    v-model="store.state.form.alias"
                                    class="company-textarea min-h-16"
                                    placeholder="Ej: paracetamol, acetaminofen"
                                /><InputError
                                    :message="store.state.errors.alias?.[0]"
                                />
                            </div>
                            <div
                                v-if="categoriasHabilitadas"
                                class="company-field"
                            >
                                <Label for="categoria_id" class="company-label"
                                    >Categoría</Label
                                ><select
                                    id="categoria_id"
                                    v-model="store.state.form.categoria_id"
                                    class="company-select"
                                >
                                    <option value="">
                                        Seleccione una categoría
                                    </option>
                                    <option
                                        v-for="categoria in categorias"
                                        :key="String(categoria.id)"
                                        :value="String(categoria.id)"
                                    >
                                        {{ categoria.nombre }}
                                    </option></select
                                ><InputError
                                    :message="
                                        store.state.errors.categoria_id?.[0]
                                    "
                                />
                            </div>
                            <div v-if="marcasHabilitadas" class="company-field">
                                <Label for="marca_id" class="company-label"
                                    >Marca</Label
                                ><select
                                    id="marca_id"
                                    v-model="store.state.form.marca_id"
                                    class="company-select"
                                >
                                    <option value="">Sin marca</option>
                                    <option
                                        v-for="marca in marcas"
                                        :key="String(marca.id)"
                                        :value="String(marca.id)"
                                    >
                                        {{ marca.nombre }}
                                    </option></select
                                ><InputError
                                    :message="store.state.errors.marca_id?.[0]"
                                />
                            </div>
                            <div class="company-field">
                                <Label for="precio_base" class="company-label"
                                    >Precio base</Label
                                ><Input
                                    id="precio_base"
                                    v-model="store.state.form.precio_base"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="company-input"
                                /><InputError
                                    :message="
                                        store.state.errors.precio_base?.[0]
                                    "
                                />
                            </div>
                            <label class="company-switch md:col-span-2"
                                ><input
                                    v-model="store.state.form.estado"
                                    type="checkbox"
                                    class="size-4 accent-[#168447]"
                                /><span
                                    ><span class="block text-sm font-semibold"
                                        >Producto activo</span
                                    ><span
                                        class="block text-sm text-muted-foreground"
                                        >Disponible para operaciones
                                        futuras.</span
                                    ></span
                                ></label
                            >
                        </div>

                        <section
                            v-if="busquedaAvanzadaHabilitada"
                            class="space-y-3 rounded-xl border border-border/70 bg-muted/20 p-3"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3 class="font-semibold">
                                        Atributos dinamicos
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Registra pares atributo y valor para
                                        mejorar la busqueda del articulo.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="company-action-secondary gap-2"
                                    @click="store.addAttribute()"
                                    ><Plus class="size-4" /> Agregar
                                    atributo</Button
                                >
                            </div>
                            <p
                                v-if="store.state.form.atributos.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No hay atributos registrados.
                            </p>
                            <div
                                v-for="(atributo, index) in store.state.form
                                    .atributos"
                                :key="index"
                                class="grid gap-3 rounded-xl border border-border/60 bg-background p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                            >
                                <div class="company-field">
                                    <Label
                                        :for="`atributo_${index}`"
                                        class="company-label"
                                        >Atributo</Label
                                    ><Input
                                        :id="`atributo_${index}`"
                                        v-model="atributo.atributo"
                                        class="company-input"
                                        placeholder="Ej: principio activo"
                                    />
                                </div>
                                <div class="company-field">
                                    <Label
                                        :for="`atributo_valor_${index}`"
                                        class="company-label"
                                        >Valor</Label
                                    ><Input
                                        :id="`atributo_valor_${index}`"
                                        v-model="atributo.valor"
                                        class="company-input"
                                        placeholder="Ej: paracetamol"
                                    />
                                </div>
                                <div class="flex items-end justify-end">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="Quitar atributo"
                                        @click="store.removeAttribute(index)"
                                        ><Trash2 class="size-4 text-red-600"
                                    /></Button>
                                </div>
                                <div class="md:col-span-3">
                                    <InputError
                                        :message="
                                            store.state.errors[
                                                `atributos.${index}.atributo`
                                            ]?.[0] ||
                                            store.state.errors[
                                                `atributos.${index}.valor`
                                            ]?.[0]
                                        "
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            class="space-y-3 rounded-xl border border-border/70 bg-muted/20 p-3"
                        >
                            <div>
                                <h3
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <BadgeCheck class="size-5 text-[#168447]" />
                                    Homologación SIAT
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Define los códigos fiscales usados en la
                                    emisión de facturas.
                                </p>
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="company-field">
                                    <Label
                                        for="codigo_actividad_economica"
                                        class="company-label"
                                        >Actividad económica SIAT</Label
                                    ><select
                                        id="codigo_actividad_economica"
                                        v-model="
                                            store.state.form
                                                .codigo_actividad_economica
                                        "
                                        class="company-select"
                                    >
                                        <option value="">
                                            Seleccione una actividad
                                        </option>
                                        <option
                                            v-for="actividad in siatActividades"
                                            :key="
                                                String(
                                                    actividad.codigo_clasificador,
                                                )
                                            "
                                            :value="
                                                String(
                                                    actividad.codigo_clasificador,
                                                )
                                            "
                                        >
                                            {{ actividad.codigo_clasificador }}
                                            - {{ actividad.descripcion }}
                                        </option></select
                                    ><InputError
                                        :message="
                                            store.state.errors
                                                .codigo_actividad_economica?.[0]
                                        "
                                    />
                                </div>
                                <div class="company-field">
                                    <Label
                                        for="codigo_unidad_medida_siat"
                                        class="company-label"
                                        >Unidad de medida</Label
                                    ><select
                                        id="codigo_unidad_medida_siat"
                                        v-model="
                                            store.state.form
                                                .codigo_unidad_medida_siat
                                        "
                                        class="company-select"
                                    >
                                        <option value="">
                                            Seleccione una unidad
                                        </option>
                                        <option
                                            v-for="unidadSiat in siatUnidades"
                                            :key="
                                                String(
                                                    unidadSiat.codigo_clasificador,
                                                )
                                            "
                                            :value="
                                                String(
                                                    unidadSiat.codigo_clasificador,
                                                )
                                            "
                                        >
                                            {{ unidadSiat.codigo_clasificador }}
                                            - {{ unidadSiat.descripcion }}
                                        </option></select
                                    ><InputError
                                        :message="
                                            store.state.errors
                                                .codigo_unidad_medida_siat?.[0]
                                        "
                                    />
                                </div>
                                <div class="company-field md:col-span-2">
                                    <Label
                                        for="codigo_producto_sin"
                                        class="company-label"
                                        >Producto SIN</Label
                                    ><select
                                        id="codigo_producto_sin"
                                        v-model="
                                            store.state.form.codigo_producto_sin
                                        "
                                        class="company-select"
                                        :disabled="
                                            !store.state.form
                                                .codigo_actividad_economica
                                        "
                                    >
                                        <option value="">
                                            {{
                                                store.state.form
                                                    .codigo_actividad_economica
                                                    ? 'Seleccione un producto SIN'
                                                    : 'Seleccione primero una actividad económica'
                                            }}
                                        </option>
                                        <option
                                            v-for="producto in productosSiatFiltrados"
                                            :key="
                                                String(producto.codigo_producto)
                                            "
                                            :value="
                                                String(producto.codigo_producto)
                                            "
                                        >
                                            {{ producto.codigo_producto }} -
                                            {{ producto.descripcion }}
                                        </option></select
                                    ><InputError
                                        :message="
                                            store.state.errors
                                                .codigo_producto_sin?.[0]
                                        "
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="preciosConfig.habilitado"
                            class="space-y-3 rounded-xl border border-border/70 bg-muted/20 p-3"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <h3 class="font-semibold">
                                        Precios múltiples
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Define escalas por cantidad o tipos de
                                        precio.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="company-action-secondary gap-2"
                                    @click="store.addPrice()"
                                    ><Plus class="size-4" /> Agregar
                                    precio</Button
                                >
                            </div>
                            <p
                                v-if="store.state.form.precios.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No hay precios adicionales registrados.
                            </p>
                            <div
                                v-for="(precio, index) in store.state.form
                                    .precios"
                                :key="index"
                                class="grid gap-4 rounded-xl border border-border/60 bg-background p-4 md:grid-cols-4"
                            >
                                <div class="company-field">
                                    <Label
                                        :for="`cantidad_minima_${index}`"
                                        class="company-label"
                                        >Cantidad mínima</Label
                                    ><Input
                                        :id="`cantidad_minima_${index}`"
                                        v-model="precio.cantidad_minima"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="company-input"
                                    />
                                </div>
                                <div class="company-field">
                                    <Label
                                        :for="`precio_${index}`"
                                        class="company-label"
                                        >Precio</Label
                                    ><Input
                                        :id="`precio_${index}`"
                                        v-model="precio.precio"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="company-input"
                                    />
                                </div>
                                <div class="company-field">
                                    <Label
                                        :for="`tipo_precio_${index}`"
                                        class="company-label"
                                        >Tipo de precio</Label
                                    ><Input
                                        :id="`tipo_precio_${index}`"
                                        v-model="precio.tipo_precio"
                                        class="company-input"
                                    />
                                </div>
                                <div
                                    class="flex items-end justify-between gap-3"
                                >
                                    <label class="company-switch"
                                        ><input
                                            v-model="precio.estado"
                                            type="checkbox"
                                            class="size-4 accent-[#168447]"
                                        /><span class="text-sm font-semibold"
                                            >Activo</span
                                        ></label
                                    ><Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="Quitar precio"
                                        @click="store.removePrice(index)"
                                        ><Trash2 class="size-4 text-red-600"
                                    /></Button>
                                </div>
                                <div class="md:col-span-4">
                                    <InputError
                                        :message="
                                            store.state.errors[
                                                `precios.${index}.precio`
                                            ]?.[0] ||
                                            store.state.errors[
                                                `precios.${index}.cantidad_minima`
                                            ]?.[0]
                                        "
                                    />
                                </div>
                            </div>
                        </section>

                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="isDialogOpen = false"
                                >Cancelar</Button
                            ><Button
                                type="submit"
                                class="company-action-primary min-w-28"
                                :disabled="store.state.saving"
                                >{{
                                    store.state.saving
                                        ? 'Guardando...'
                                        : isEditing
                                          ? 'Actualizar'
                                          : 'Guardar'
                                }}</Button
                            >
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

<style scoped>
@reference '../../../../css/app.css';

.inventory-stat-card {
    @apply flex min-h-[7rem] items-center gap-4 rounded-xl border border-[#dce4df] bg-white p-4 shadow-[0_10px_28px_-24px_rgba(16,23,19,0.75)];
}
.inventory-stat-icon {
    @apply flex size-14 shrink-0 items-center justify-center rounded-xl;
}
.inventory-stat-label {
    @apply text-sm font-medium text-[#34423a];
}
.inventory-stat-value {
    @apply mt-0.5 text-2xl font-bold tracking-tight text-[#101713];
}
.inventory-stat-help {
    @apply mt-0.5 text-xs text-[#68756d];
}
.inventory-filter-field {
    @apply flex flex-col gap-1 text-xs font-medium text-[#34423a];
}
.inventory-detail-row {
    @apply flex items-center justify-between gap-3 border-b border-[#e3e9e5] py-2.5 last:border-0;
}
.inventory-detail-row dd {
    @apply text-right font-medium text-[#26342c];
}
</style>

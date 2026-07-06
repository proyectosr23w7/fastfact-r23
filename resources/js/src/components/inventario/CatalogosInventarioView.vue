<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { useCategoriaStore } from '@/src/stores/categoriaStore';
import { useMarcaStore } from '@/src/stores/marcaStore';
import { useUnidadMedidaStore } from '@/src/stores/unidadMedidaStore';
import type { Component } from 'vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Boxes,
    CheckCircle2,
    ChevronDown,
    Filter,
    Hexagon,
    MoreVertical,
    Pencil,
    Plus,
    RotateCcw,
    Ruler,
    Search,
    Tags,
    Trash2,
    XCircle,
} from 'lucide-vue-next';

type CatalogKey = 'categoria' | 'marca' | 'unidad';
type CatalogItem = Record<string, unknown>;
type CatalogStore = ReturnType<typeof useCategoriaStore>;
type CatalogField = {
    key: string;
    label: string;
    placeholder: string;
    type?: 'text' | 'textarea' | 'checkbox';
    required?: boolean;
    span?: 'full';
};
type CatalogConfig = {
    key: CatalogKey;
    tabLabel: string;
    singular: string;
    plural: string;
    path: string;
    activeLabel: string;
    searchPlaceholder: string;
    detailTitle: string;
    icon: Component;
    fields: CatalogField[];
};

const props = defineProps<{
    initialTab: CatalogKey;
}>();

const categoriaStore = useCategoriaStore();
const marcaStore = useMarcaStore();
const unidadStore = useUnidadMedidaStore();

const stores: Record<CatalogKey, CatalogStore> = {
    categoria: categoriaStore,
    marca: marcaStore,
    unidad: unidadStore as CatalogStore,
};

const catalogos: CatalogConfig[] = [
    {
        key: 'categoria',
        tabLabel: 'Categorías',
        singular: 'categoría',
        plural: 'categorías',
        path: '/inventario/categorias',
        activeLabel: 'Categoría activa',
        searchPlaceholder: 'Buscar categoría',
        detailTitle: 'Detalle de categoría',
        icon: Tags,
        fields: [
            {
                key: 'nombre',
                label: 'Nombre',
                placeholder: 'Ej: Abarrotes',
                required: true,
            },
            {
                key: 'descripcion',
                label: 'Descripción',
                placeholder: 'Descripción de la categoría',
                type: 'textarea',
                span: 'full',
            },
            {
                key: 'estado',
                label: 'Categoría activa',
                placeholder: 'Disponible para clasificar artículos.',
                type: 'checkbox',
            },
        ],
    },
    {
        key: 'marca',
        tabLabel: 'Marcas',
        singular: 'marca',
        plural: 'marcas',
        path: '/inventario/marcas',
        activeLabel: 'Marca activa',
        searchPlaceholder: 'Buscar marca',
        detailTitle: 'Detalle de marca',
        icon: Hexagon,
        fields: [
            {
                key: 'nombre',
                label: 'Nombre',
                placeholder: 'Ej: Andina',
                required: true,
            },
            {
                key: 'descripcion',
                label: 'Descripción',
                placeholder: 'Descripción de la marca',
                type: 'textarea',
                span: 'full',
            },
            {
                key: 'estado',
                label: 'Marca activa',
                placeholder: 'Disponible para asociar artículos.',
                type: 'checkbox',
            },
        ],
    },
    {
        key: 'unidad',
        tabLabel: 'Unidades de medida',
        singular: 'unidad',
        plural: 'unidades',
        path: '/inventario/unidades-medida',
        activeLabel: 'Unidad activa',
        searchPlaceholder: 'Buscar unidad',
        detailTitle: 'Detalle de unidad',
        icon: Ruler,
        fields: [
            {
                key: 'nombre',
                label: 'Nombre',
                placeholder: 'Ej: Kilogramo',
                required: true,
            },
            {
                key: 'abreviatura',
                label: 'Abreviatura',
                placeholder: 'Ej: KG',
                required: true,
            },
            {
                key: 'estado',
                label: 'Unidad activa',
                placeholder: 'Disponible para ventas y stock.',
                type: 'checkbox',
            },
        ],
    },
];

const pathToKey = (path: string): CatalogKey => {
    if (path.includes('/inventario/marcas')) return 'marca';
    if (path.includes('/inventario/unidades-medida')) return 'unidad';

    return 'categoria';
};

const activeKey = ref<CatalogKey>(props.initialTab);
const searchDraft = ref('');
const statusDraft = ref<'todos' | 'activos' | 'inactivos'>('todos');
const appliedSearch = ref('');
const appliedStatus = ref<'todos' | 'activos' | 'inactivos'>('todos');
const selectedId = ref<number | null>(null);
const formPanelRef = ref<HTMLElement | null>(null);
const isFormOpen = ref(true);
const loadError = ref('');
const actionError = ref('');
const deleteDialogOpen = ref(false);
const pendingDelete = ref<CatalogItem | null>(null);

const activeCatalog = computed(
    () => catalogos.find((catalogo) => catalogo.key === activeKey.value) ?? catalogos[0],
);
const activeStore = computed(() => stores[activeKey.value]);
const activeRows = computed(() => activeStore.value.state.items);
const activeIcon = computed(() => activeCatalog.value.icon);
const selectedItem = computed(() => {
    const preferred = activeRows.value.find((item) => Number(item.id) === selectedId.value);

    return preferred ?? activeRows.value[0] ?? null;
});

const selectedCount = computed(() => countArticles(selectedItem.value));
const formTitle = computed(() =>
    activeStore.value.state.editingId
        ? `Editar ${activeCatalog.value.singular}`
        : `Nueva ${activeCatalog.value.singular}`,
);
const formActionLabel = computed(() =>
    activeStore.value.state.saving
        ? 'Guardando...'
        : activeStore.value.state.editingId
          ? 'Actualizar'
          : 'Guardar',
);

const kpis = computed(() => [
    {
        label: 'Categorías',
        value: countActive(categoriaStore.state.items),
        helper: 'Activas',
        icon: Tags,
        tone: 'green',
    },
    {
        label: 'Marcas',
        value: countActive(marcaStore.state.items),
        helper: 'Activas',
        icon: Hexagon,
        tone: 'green',
    },
    {
        label: 'Unidades',
        value: countActive(unidadStore.state.items),
        helper: 'Activas',
        icon: Ruler,
        tone: 'green',
    },
    {
        label: 'Registros inactivos',
        value:
            countInactive(categoriaStore.state.items) +
            countInactive(marcaStore.state.items) +
            countInactive(unidadStore.state.items),
        helper: 'En catálogos',
        icon: XCircle,
        tone: 'danger',
    },
]);

const filteredRows = computed(() => {
    const term = normalize(appliedSearch.value);

    return activeRows.value.filter((item) => {
        const matchesText = [
            item.nombre,
            item.descripcion,
            item.abreviatura,
        ].some((value) => normalize(String(value ?? '')).includes(term));
        const active = Boolean(item.estado);
        const matchesStatus =
            appliedStatus.value === 'todos' ||
            (appliedStatus.value === 'activos' && active) ||
            (appliedStatus.value === 'inactivos' && !active);

        return matchesText && matchesStatus;
    });
});

function normalize(value: string) {
    return value
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function countArticles(item: CatalogItem | null) {
    return Number(item?.articulos_count ?? 0);
}

function countActive(items: CatalogItem[]) {
    return items.filter((item) => Boolean(item.estado)).length;
}

function countInactive(items: CatalogItem[]) {
    return items.filter((item) => !Boolean(item.estado)).length;
}

function totalFor(key: CatalogKey) {
    return stores[key].state.items.length;
}

function rowDescription(item: CatalogItem) {
    return String(item.descripcion || 'Sin descripción');
}

function articleLink(item: CatalogItem | null = selectedItem.value) {
    const id = item?.id ? String(item.id) : '';
    const param =
        activeKey.value === 'categoria'
            ? 'categoria_id'
            : activeKey.value === 'marca'
              ? 'marca_id'
              : 'unidad_medida_id';

    return id ? `/inventario/articulos?${param}=${encodeURIComponent(id)}` : '/inventario/articulos';
}

function selectItem(item: CatalogItem) {
    selectedId.value = Number(item.id);
}

function applyFilters() {
    appliedSearch.value = searchDraft.value;
    appliedStatus.value = statusDraft.value;
}

function clearFilters() {
    searchDraft.value = '';
    statusDraft.value = 'todos';
    appliedSearch.value = '';
    appliedStatus.value = 'todos';
}

function changeTab(key: CatalogKey) {
    if (activeKey.value === key) return;

    const catalog = catalogos.find((item) => item.key === key);
    if (!catalog) return;

    activeKey.value = key;
    clearFilters();
    resetForm();
    window.history.pushState({}, '', catalog.path);
}

async function loadCatalogos() {
    loadError.value = '';

    try {
        await Promise.all([categoriaStore.load(), marcaStore.load(), unidadStore.load()]);
    } catch (error) {
        loadError.value =
            error instanceof Error
                ? error.message
                : 'No se pudieron cargar los catálogos de inventario.';
    }
}

async function reloadActiveCatalog() {
    loadError.value = '';

    try {
        await activeStore.value.load();
    } catch (error) {
        loadError.value =
            error instanceof Error
                ? error.message
                : `No se pudo cargar el catálogo de ${activeCatalog.value.plural}.`;
    }
}

function openCreate() {
    actionError.value = '';
    activeStore.value.startCreate();
    isFormOpen.value = true;
    scrollToForm();
}

function openEdit(item: CatalogItem) {
    actionError.value = '';
    selectItem(item);
    activeStore.value.startEdit(item);
    isFormOpen.value = true;
    scrollToForm();
}

function resetForm() {
    actionError.value = '';
    activeStore.value.resetForm();
}

function cancelForm() {
    resetForm();
    isFormOpen.value = false;
}

async function saveForm() {
    actionError.value = '';
    const savedName = String(activeStore.value.state.form.nombre ?? '');

    try {
        await activeStore.value.save({ ...activeStore.value.state.form });

        if (Object.keys(activeStore.value.state.errors).length === 0) {
            const createdOrUpdated = activeRows.value.find(
                (item) =>
                    normalize(String(item.nombre ?? '')) ===
                    normalize(savedName),
            );

            selectedId.value = createdOrUpdated ? Number(createdOrUpdated.id) : selectedId.value;
        }
    } catch (error) {
        actionError.value =
            error instanceof Error
                ? error.message
                : `No se pudo guardar la ${activeCatalog.value.singular}.`;
    }
}

async function toggleState(item: CatalogItem) {
    actionError.value = '';

    try {
        const toggle = stores[activeKey.value].toggleEstado;
        await toggle(item);
    } catch (error) {
        actionError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo actualizar el estado del registro.';
    }
}

function askDelete(item: CatalogItem) {
    pendingDelete.value = item;
    deleteDialogOpen.value = true;
}

async function confirmDelete() {
    if (!pendingDelete.value) return;

    actionError.value = '';

    try {
        await activeStore.value.destroy(Number(pendingDelete.value.id));
        deleteDialogOpen.value = false;
        pendingDelete.value = null;
        resetForm();
    } catch (error) {
        actionError.value =
            error instanceof Error
                ? error.message
                : `No se pudo eliminar la ${activeCatalog.value.singular}.`;
    }
}

function scrollToForm() {
    nextTick(() => {
        formPanelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

function handlePopState() {
    activeKey.value = pathToKey(window.location.pathname);
    clearFilters();
    resetForm();
}

onMounted(async () => {
    activeKey.value = pathToKey(window.location.pathname);
    await loadCatalogos();
    window.addEventListener('popstate', handlePopState);
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', handlePopState);
});

watch(
    [activeKey, filteredRows],
    () => {
        if (!filteredRows.value.length) {
            selectedId.value = null;
            return;
        }

        const exists = filteredRows.value.some((item) => Number(item.id) === selectedId.value);
        if (!exists) selectedId.value = Number(filteredRows.value[0].id);
    },
    { immediate: true },
);
</script>

<template>
    <ModulePageLayout
        title="Catálogos de inventario"
        description="Organiza las clasificaciones utilizadas por tus artículos"
        :breadcrumbs="[
            { title: 'Inventario', href: '/inventario/articulos' },
            { title: 'Catálogos', href: activeCatalog.path },
        ]"
        compact
    >
        <template #actions>
            <div class="flex flex-col gap-2 sm:flex-row">
                <Button
                    type="button"
                    class="company-action-primary min-h-10 gap-2 px-5"
                    @click="openCreate"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Nueva {{ activeCatalog.singular }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-10 border-[#cad7ce] text-[#101713]"
                    aria-label="Recargar catálogos"
                    @click="reloadActiveCatalog"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                </Button>
            </div>
        </template>

        <div class="space-y-4">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores de catálogos">
                <article
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="company-panel flex min-h-24 items-center gap-4 p-4"
                >
                    <div
                        :class="[
                            'flex size-14 shrink-0 items-center justify-center rounded-xl',
                            kpi.tone === 'danger'
                                ? 'bg-red-50 text-red-600 ring-1 ring-red-100'
                                : 'bg-[#EAF7EF] text-[#168447]',
                        ]"
                    >
                        <component :is="kpi.icon" class="size-7" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-[#101713]">{{ kpi.label }}</p>
                        <p class="text-2xl font-semibold text-[#101713]">{{ kpi.value }}</p>
                        <p class="text-sm text-[#536158]">{{ kpi.helper }}</p>
                    </div>
                </article>
            </section>

            <p
                v-if="loadError"
                class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                role="alert"
            >
                {{ loadError }}
            </p>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <section class="company-panel min-w-0 overflow-hidden">
                    <div class="border-b border-[#dfe7e2]">
                        <div
                            class="flex overflow-x-auto px-3"
                            role="tablist"
                            aria-label="Catálogos de inventario"
                        >
                            <button
                                v-for="catalogo in catalogos"
                                :key="catalogo.key"
                                type="button"
                                role="tab"
                                :aria-selected="activeKey === catalogo.key"
                                :class="[
                                    'min-h-14 shrink-0 border-b-2 px-4 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:ring-offset-2 focus-visible:outline-none',
                                    activeKey === catalogo.key
                                        ? 'border-[#168447] text-[#168447]'
                                        : 'border-transparent text-[#405047] hover:text-[#101713]',
                                ]"
                                @click="changeTab(catalogo.key)"
                            >
                                {{ catalogo.tabLabel }}
                                <span class="ml-1 text-[#536158]">({{ totalFor(catalogo.key) }})</span>
                            </button>
                        </div>
                    </div>

                    <form
                        class="grid gap-3 border-b border-[#dfe7e2] p-4 md:grid-cols-[minmax(14rem,1fr)_13rem_auto_auto]"
                        @submit.prevent="applyFilters"
                    >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#536158]"
                                aria-hidden="true"
                            />
                            <Input
                                v-model="searchDraft"
                                type="search"
                                class="company-input h-11 pl-9"
                                :placeholder="activeCatalog.searchPlaceholder"
                                :aria-label="activeCatalog.searchPlaceholder"
                            />
                        </div>

                        <label class="grid gap-1 text-xs font-semibold text-[#101713]">
                            Estado
                            <select v-model="statusDraft" class="company-select h-11" aria-label="Estado">
                                <option value="todos">Todos</option>
                                <option value="activos">Activos</option>
                                <option value="inactivos">Inactivos</option>
                            </select>
                        </label>

                        <Button type="submit" class="company-action-primary h-11 gap-2">
                            <Filter class="size-4" aria-hidden="true" />
                            Filtrar
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 gap-2 border-[#cad7ce]"
                            @click="clearFilters"
                        >
                            <RotateCcw class="size-4" aria-hidden="true" />
                            Limpiar
                        </Button>
                    </form>

                    <p
                        v-if="actionError"
                        class="mx-4 mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                        role="alert"
                    >
                        {{ actionError }}
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-[#F5F8F6] text-left text-[#101713]">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ activeKey === 'unidad' ? 'Unidad' : activeCatalog.singular[0].toUpperCase() + activeCatalog.singular.slice(1) }}
                                    </th>
                                    <th v-if="activeKey === 'unidad'" class="px-4 py-3 font-semibold">Abreviatura</th>
                                    <th v-else class="px-4 py-3 font-semibold">Descripción</th>
                                    <th class="px-4 py-3 font-semibold">Artículos</th>
                                    <th class="px-4 py-3 font-semibold">Estado</th>
                                    <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="activeStore.state.loading">
                                    <td
                                        :colspan="activeKey === 'unidad' ? 5 : 5"
                                        class="px-4 py-10 text-center text-[#536158]"
                                    >
                                        Cargando registros...
                                    </td>
                                </tr>
                                <tr v-else-if="filteredRows.length === 0">
                                    <td colspan="5" class="px-4 py-12 text-center">
                                        <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                                            <div class="flex size-12 items-center justify-center rounded-xl bg-[#EAF7EF] text-[#168447]">
                                                <Boxes class="size-6" aria-hidden="true" />
                                            </div>
                                            <div>
                                                <p class="font-semibold text-[#101713]">Sin registros para mostrar</p>
                                                <p class="mt-1 text-sm text-[#536158]">
                                                    Ajusta los filtros o crea una nueva {{ activeCatalog.singular }}.
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr
                                        v-for="item in filteredRows"
                                        :key="String(item.id)"
                                        :class="[
                                            'cursor-pointer border-t border-[#dfe7e2] transition hover:bg-[#EAF7EF]/50',
                                            Number(item.id) === selectedId ? 'bg-[#EAF7EF]/70' : '',
                                        ]"
                                        @click="selectItem(item)"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <span class="flex size-7 shrink-0 items-center justify-center rounded-md bg-[#EAF7EF] text-[#168447]">
                                                    <component :is="activeIcon" class="size-4" aria-hidden="true" />
                                                </span>
                                                <span class="font-medium text-[#101713]">{{ item.nombre }}</span>
                                            </div>
                                        </td>
                                        <td class="max-w-[18rem] px-4 py-3 text-[#405047]">
                                            <span class="line-clamp-2">
                                                {{ activeKey === 'unidad' ? item.abreviatura : rowDescription(item) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-[#101713]">{{ countArticles(item) }}</td>
                                        <td class="px-4 py-3">
                                            <Badge
                                                :class="
                                                    item.estado
                                                        ? 'border-transparent bg-[#EAF7EF] text-[#168447]'
                                                        : 'border-transparent bg-[#ecefed] text-[#536158]'
                                                "
                                            >
                                                {{ item.estado ? 'Activa' : 'Inactiva' }}
                                            </Badge>
                                        </td>
                                        <td class="px-4 py-3 text-right" @click.stop>
                                            <div class="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="gap-2 border-[#cad7ce] text-[#168447]"
                                                    @click="openEdit(item)"
                                                >
                                                    <Pencil class="size-4" aria-hidden="true" />
                                                    Editar
                                                </Button>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon-sm"
                                                            class="border-[#cad7ce]"
                                                            aria-label="Más acciones"
                                                        >
                                                            <MoreVertical class="size-4" aria-hidden="true" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" class="w-48">
                                                        <DropdownMenuItem @select="toggleState(item)">
                                                            <CheckCircle2 class="size-4" aria-hidden="true" />
                                                            {{ item.estado ? 'Desactivar' : 'Activar' }}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem variant="destructive" @select="askDelete(item)">
                                                            <Trash2 class="size-4" aria-hidden="true" />
                                                            Eliminar
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#dfe7e2] px-4 py-3 text-sm text-[#536158] sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            Mostrando {{ filteredRows.length }} de {{ activeRows.length }} {{ activeCatalog.plural }}
                        </p>
                        <p>Conteos calculados con registros actuales</p>
                    </div>
                </section>

                <aside class="company-panel h-fit overflow-hidden xl:sticky xl:top-4">
                    <div class="border-b border-[#dfe7e2] p-5">
                        <p class="text-lg font-semibold text-[#101713]">{{ activeCatalog.detailTitle }}</p>
                    </div>

                    <div v-if="selectedItem" class="space-y-5 p-5">
                        <div class="flex items-start gap-4">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-[#EAF7EF] text-[#168447]">
                                <component :is="activeIcon" class="size-6" aria-hidden="true" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-base font-semibold text-[#101713]">
                                        {{ selectedItem.nombre }}
                                    </h2>
                                    <Badge
                                        :class="
                                            selectedItem.estado
                                                ? 'border-transparent bg-[#EAF7EF] text-[#168447]'
                                                : 'border-transparent bg-[#ecefed] text-[#536158]'
                                        "
                                    >
                                        {{ selectedItem.estado ? 'Activa' : 'Inactiva' }}
                                    </Badge>
                                </div>
                                <p v-if="activeKey === 'unidad'" class="mt-1 text-sm text-[#536158]">
                                    {{ selectedItem.abreviatura }}
                                </p>
                            </div>
                        </div>

                        <div v-if="activeKey !== 'unidad'">
                            <p class="text-sm font-semibold text-[#101713]">Descripción</p>
                            <p class="mt-1 text-sm text-[#405047]">{{ rowDescription(selectedItem) }}</p>
                        </div>

                        <div class="border-t border-[#dfe7e2] pt-4">
                            <p class="text-sm font-semibold text-[#101713]">Artículos asignados</p>
                            <p class="mt-1 text-sm text-[#405047]">
                                {{ selectedCount }} artículos vinculados
                            </p>
                        </div>

                        <div class="rounded-lg border border-[#dfe7e2] bg-[#F5F8F6] p-4">
                            <p class="text-sm font-semibold text-[#101713]">Registros vinculados</p>
                            <p class="mt-1 text-sm text-[#536158]">
                                El backend entrega el conteo real. No hay listado de artículos en esta respuesta.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-10 gap-2 border-[#168447] text-[#168447]"
                                @click="openEdit(selectedItem)"
                            >
                                <Pencil class="size-4" aria-hidden="true" />
                                Editar {{ activeCatalog.singular }}
                            </Button>
                            <Button
                                as="a"
                                :href="articleLink()"
                                variant="outline"
                                class="min-h-10 gap-2 border-[#168447] text-[#168447]"
                            >
                                <Boxes class="size-4" aria-hidden="true" />
                                Ver artículos
                            </Button>
                        </div>
                    </div>

                    <div v-else class="flex min-h-64 flex-col items-center justify-center gap-3 p-6 text-center">
                        <div class="flex size-12 items-center justify-center rounded-xl bg-[#EAF7EF] text-[#168447]">
                            <Boxes class="size-6" aria-hidden="true" />
                        </div>
                        <p class="font-semibold text-[#101713]">Sin detalle disponible</p>
                        <p class="text-sm text-[#536158]">Selecciona un registro del catálogo activo.</p>
                    </div>
                </aside>
            </div>

            <section
                v-if="isFormOpen"
                ref="formPanelRef"
                class="company-panel overflow-hidden"
                :aria-labelledby="`${activeKey}-form-title`"
            >
                <form class="grid gap-4 p-5 lg:grid-cols-[1fr_auto]" @submit.prevent="saveForm">
                    <div class="min-w-0">
                        <button
                            type="button"
                            class="mb-3 inline-flex items-center gap-2 text-left text-sm font-semibold text-[#101713] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none"
                            @click="isFormOpen = !isFormOpen"
                        >
                            <span :id="`${activeKey}-form-title`">{{ formTitle }}</span>
                            <ChevronDown class="size-4" aria-hidden="true" />
                        </button>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[minmax(14rem,0.9fr)_minmax(18rem,1.4fr)_12rem]">
                            <div
                                v-for="field in activeCatalog.fields"
                                :key="field.key"
                                :class="[
                                    'company-field',
                                    field.span === 'full' ? 'md:col-span-2 xl:col-span-1' : '',
                                    field.type === 'checkbox' ? 'self-end' : '',
                                ]"
                            >
                                <template v-if="field.type !== 'checkbox'">
                                    <Label :for="`${activeKey}-${field.key}`" class="company-label">
                                        {{ field.label }}
                                    </Label>
                                    <Input
                                        v-if="field.type !== 'textarea'"
                                        :id="`${activeKey}-${field.key}`"
                                        v-model="activeStore.state.form[field.key]"
                                        type="text"
                                        :required="field.required"
                                        :placeholder="field.placeholder"
                                        class="company-input h-10"
                                    />
                                    <textarea
                                        v-else
                                        :id="`${activeKey}-${field.key}`"
                                        v-model="activeStore.state.form[field.key]"
                                        :placeholder="field.placeholder"
                                        class="company-textarea min-h-10 py-2"
                                    />
                                </template>

                                <label v-else class="company-switch min-h-10 rounded-lg px-3 py-2">
                                    <input
                                        v-model="activeStore.state.form[field.key]"
                                        type="checkbox"
                                        class="size-4 accent-[#168447]"
                                    />
                                    <span class="text-sm font-semibold text-[#101713]">{{ activeCatalog.activeLabel }}</span>
                                </label>

                                <InputError :message="activeStore.state.errors[field.key]?.[0]" />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col justify-end gap-3 sm:flex-row lg:min-w-72">
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-10 border-[#cad7ce]"
                            @click="cancelForm"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            class="company-action-primary min-h-10 min-w-32"
                            :disabled="activeStore.state.saving"
                        >
                            {{ formActionLabel }}
                        </Button>
                    </div>
                </form>
            </section>
        </div>

        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="max-w-md border-[#dfe7e2] bg-white">
                <DialogHeader>
                    <DialogTitle>Eliminar {{ activeCatalog.singular }}</DialogTitle>
                    <DialogDescription>
                        Se quitará el registro
                        <strong>{{ pendingDelete?.nombre }}</strong>
                        del catálogo. Esta acción usará la validación del servidor.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="deleteDialogOpen = false">
                        Cancelar
                    </Button>
                    <Button type="button" variant="destructive" @click="confirmDelete">
                        Eliminar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

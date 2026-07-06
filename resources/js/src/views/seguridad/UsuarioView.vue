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
import SeguridadTabs from '@/src/components/seguridad/SeguridadTabs.vue';
import { useUsuarioStore } from '@/src/stores/usuarioStore';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleUserRound,
    Edit3,
    KeyRound,
    LoaderCircle,
    Plus,
    Save,
    Search,
    ShieldCheck,
    UserCheck,
    UserMinus,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

type Item = Record<string, any>;

const store = useUsuarioStore();
const page = usePage<SharedData>();
const isFormOpen = ref(false);
const isDeactivateOpen = ref(false);
const isDiscardOpen = ref(false);
const accessEditing = ref(false);
const deactivateTarget = ref<Item | null>(null);
const pendingSelection = ref<Item | null>(null);
const roleEditor = ref<HTMLElement | null>(null);
const search = ref('');
const statusFilter = ref('todos');
const roleFilter = ref('todos');
const currentPage = ref(1);
const pageSize = 8;

const currentUserId = computed(() => Number(page.props.auth.user?.id ?? 0));
const isEditing = computed(() => store.state.editingId !== null);
const roles = computed<Item[]>(
    () => (store.state.meta.roles as Item[] | undefined) ?? [],
);
const permissions = computed<Item[]>(
    () => (store.state.meta.permissions as Item[] | undefined) ?? [],
);
const selectedUser = computed<Item | null>(() => store.state.selectedItem);

const getRoles = (item: Item) => (item.roles as Item[] | undefined) ?? [];
const initials = (name: unknown) =>
    String(name ?? 'U')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

const activeUsers = computed(
    () => store.state.items.filter((item) => Boolean(item.estado)).length,
);
const administrators = computed(
    () =>
        store.state.items.filter((item) =>
            getRoles(item).some((role) =>
                ['administrador', 'superadmin'].includes(String(role.slug)),
            ),
        ).length,
);
const filteredUsers = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return store.state.items.filter((item) => {
        const matchesSearch =
            !term ||
            String(item.name ?? '')
                .toLocaleLowerCase('es')
                .includes(term) ||
            String(item.email ?? '')
                .toLocaleLowerCase('es')
                .includes(term);
        const matchesStatus =
            statusFilter.value === 'todos' ||
            (statusFilter.value === 'activos'
                ? Boolean(item.estado)
                : !Boolean(item.estado));
        const matchesRole =
            roleFilter.value === 'todos' ||
            getRoles(item).some((role) => String(role.id) === roleFilter.value);

        return matchesSearch && matchesStatus && matchesRole;
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredUsers.value.length / pageSize)),
);
const paginatedUsers = computed(() =>
    filteredUsers.value.slice(
        (currentPage.value - 1) * pageSize,
        currentPage.value * pageSize,
    ),
);
const rangeStart = computed(() =>
    filteredUsers.value.length ? (currentPage.value - 1) * pageSize + 1 : 0,
);
const rangeEnd = computed(() =>
    Math.min(currentPage.value * pageSize, filteredUsers.value.length),
);

const originalRoleIds = computed(() =>
    selectedUser.value
        ? getRoles(selectedUser.value)
              .map((role) => Number(role.id))
              .sort((a, b) => a - b)
        : [],
);
const accessDirty = computed(() => {
    if (!selectedUser.value) return false;
    const currentRoles = [...store.state.rolesForm.role_ids].sort(
        (a, b) => a - b,
    );

    return (
        JSON.stringify(currentRoles) !==
        JSON.stringify(originalRoleIds.value)
    );
});

const permissionSummary = computed(() => {
    if (!selectedUser.value) return [];
    const slugs =
        (selectedUser.value.permission_slugs as string[] | undefined) ?? [];
    const allAllowed = slugs.includes('*');
    const modules = new Map<
        string,
        { label: string; total: number; allowed: number }
    >();

    permissions.value.forEach((permission) => {
        const module = String(permission.modulo ?? 'otros');
        const entry = modules.get(module) ?? {
            label: module.charAt(0).toUpperCase() + module.slice(1),
            total: 0,
            allowed: 0,
        };
        entry.total += 1;
        if (allAllowed || slugs.includes(String(permission.slug)))
            entry.allowed += 1;
        modules.set(module, entry);
    });

    return [...modules.values()];
});

const openCreate = () => {
    store.startCreate();
    isFormOpen.value = true;
};

const openEdit = (item: Item) => {
    store.startEdit(item);
    isFormOpen.value = true;
};

const saveUser = async () => {
    if (await store.save()) isFormOpen.value = false;
};

const applySelection = (item: Item) => {
    store.prepareAccess(item);
    accessEditing.value = false;
};

const selectUser = (item: Item) => {
    if (Number(selectedUser.value?.id) === Number(item.id)) return;
    if (accessDirty.value) {
        pendingSelection.value = item;
        isDiscardOpen.value = true;
        return;
    }
    applySelection(item);
};

const discardAndSelect = () => {
    if (pendingSelection.value) applySelection(pendingSelection.value);
    pendingSelection.value = null;
    isDiscardOpen.value = false;
};

const startAccessEditing = async () => {
    accessEditing.value = true;
    await nextTick();
    roleEditor.value?.focus();
};

const cancelAccessEditing = () => {
    if (selectedUser.value) store.prepareAccess(selectedUser.value);
    accessEditing.value = false;
};

const saveAccess = async () => {
    if (await store.assignAccess()) accessEditing.value = false;
};

const requestToggle = (item: Item) => {
    if (!item.estado) {
        void store.toggleEstado(item);
        return;
    }
    deactivateTarget.value = item;
    isDeactivateOpen.value = true;
};

const confirmDeactivate = async () => {
    if (
        deactivateTarget.value &&
        (await store.toggleEstado(deactivateTarget.value))
    ) {
        isDeactivateOpen.value = false;
        deactivateTarget.value = null;
    }
};

const resetFilters = () => {
    search.value = '';
    statusFilter.value = 'todos';
    roleFilter.value = 'todos';
};

watch(
    [search, statusFilter, roleFilter],
    () => (currentPage.value = 1),
);
watch(totalPages, (value) => {
    if (currentPage.value > value) currentPage.value = value;
});
onMounted(async () => {
    await store.load();
    if (store.state.items[0]) applySelection(store.state.items[0]);
});
</script>

<template>
    <ModulePageLayout
        title="Usuarios y roles"
        description="Controla quien accede al sistema y que puede hacer."
        :breadcrumbs="[
            { title: 'Seguridad', href: '/dashboard' },
            { title: 'Usuarios y roles', href: '/seguridad/usuarios' },
        ]"
        compact
    >
        <div class="space-y-4">
            <div class="company-panel overflow-hidden">
                <div class="overflow-x-auto border-b border-[#dfe7e2] px-4 pt-1">
                    <SeguridadTabs active="usuarios" />
                </div>
            </div>

            <div
                v-if="store.state.error"
                class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                <AlertCircle
                    class="mt-0.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ store.state.error }}</span>
                <button
                    class="ml-auto"
                    type="button"
                    aria-label="Cerrar mensaje"
                    @click="store.clearFeedback"
                >
                    <X class="size-4" />
                </button>
            </div>
            <div
                v-if="store.state.success"
                class="flex items-start gap-3 rounded-xl border border-[#bde5cb] bg-[#eaf7ef] px-4 py-3 text-sm text-[#116f3b]"
                role="status"
            >
                <Check class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>{{ store.state.success }}</span>
                <button
                    class="ml-auto"
                    type="button"
                    aria-label="Cerrar mensaje"
                    @click="store.clearFeedback"
                >
                    <X class="size-4" />
                </button>
            </div>

            <section
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                aria-label="Resumen de usuarios"
            >
                <article class="company-panel flex items-center gap-4 p-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                    >
                        <Users class="size-7" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-sm text-[#536158]">Usuarios activos</p>
                        <p class="text-2xl font-bold text-[#168447]">
                            {{ activeUsers }}
                        </p>
                        <p class="text-xs text-[#748078]">
                            De {{ store.state.items.length }} registrados
                        </p>
                    </div>
                </article>
                <article class="company-panel flex items-center gap-4 p-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                    >
                        <UserCheck class="size-7" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-sm text-[#536158]">Administradores</p>
                        <p class="text-2xl font-bold text-[#168447]">
                            {{ administrators }}
                        </p>
                        <p class="text-xs text-[#748078]">
                            Con acceso administrativo
                        </p>
                    </div>
                </article>
                <article class="company-panel flex items-center gap-4 p-4">
                    <div
                        class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-[#eaf7ef] text-[#168447]"
                    >
                        <ShieldCheck class="size-7" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-sm text-[#536158]">Roles configurados</p>
                        <p class="text-2xl font-bold text-[#168447]">
                            {{ roles.length }}
                        </p>
                        <p class="text-xs text-[#748078]">
                            Activos y disponibles
                        </p>
                    </div>
                </article>
            </section>

            <div
                class="grid items-start gap-4 2xl:grid-cols-[minmax(0,1fr)_360px]"
            >
                <section class="company-panel min-w-0 overflow-hidden">
                    <div
                        class="flex flex-col gap-4 border-b border-border/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Listado de usuarios
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Administra cuentas, roles y permisos.
                            </p>
                        </div>
                        <Button
                            class="company-action-primary min-h-11 gap-2"
                            @click="openCreate"
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            Nuevo usuario
                        </Button>
                    </div>

                    <div
                        class="grid gap-3 border-b border-[#dfe7e2] bg-[#f8faf9] p-4 lg:grid-cols-[minmax(220px,1.4fr)_repeat(2,minmax(140px,0.8fr))_auto]"
                    >
                        <label class="relative block">
                            <span class="sr-only">Buscar usuario</span>
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#6e7971]"
                            />
                            <Input
                                v-model="search"
                                class="company-input pl-9"
                                placeholder="Buscar por nombre o correo"
                            />
                        </label>
                        <label
                            ><span
                                class="mb-1 block text-xs font-semibold text-[#536158]"
                                >Estado</span
                            ><select
                                v-model="statusFilter"
                                class="company-select"
                            >
                                <option value="todos">Todos</option>
                                <option value="activos">Activos</option>
                                <option value="inactivos">Inactivos</option>
                            </select></label
                        >
                        <label
                            ><span
                                class="mb-1 block text-xs font-semibold text-[#536158]"
                                >Rol</span
                            ><select
                                v-model="roleFilter"
                                class="company-select"
                            >
                                <option value="todos">Todos</option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="String(role.id)"
                                >
                                    {{ role.nombre }}
                                </option>
                            </select></label
                        >
                        <Button
                            variant="outline"
                            class="company-action-secondary self-end"
                            @click="resetFilters"
                            >Limpiar</Button
                        >
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-sm">
                            <thead
                                class="bg-white text-left text-xs text-[#536158]"
                            >
                                <tr>
                                    <th class="px-4 py-3 font-semibold">
                                        Usuario
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        Correo
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        Roles
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        Ultimo acceso
                                    </th>
                                    <th class="px-4 py-3 font-semibold">
                                        Estado
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-semibold"
                                    >
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="store.state.loading">
                                    <td
                                        colspan="6"
                                        class="px-4 py-12 text-center text-[#647068]"
                                    >
                                        <LoaderCircle
                                            class="mx-auto mb-2 size-6 animate-spin text-[#168447]"
                                        />Cargando usuarios...
                                    </td>
                                </tr>
                                <tr v-else-if="!paginatedUsers.length">
                                    <td
                                        colspan="6"
                                        class="px-4 py-12 text-center"
                                    >
                                        <CircleUserRound
                                            class="mx-auto mb-3 size-9 text-[#9caaa1]"
                                        />
                                        <p class="font-semibold text-[#28322c]">
                                            No se encontraron usuarios
                                        </p>
                                        <p class="text-sm text-[#6e7971]">
                                            Ajusta los filtros o registra un
                                            nuevo usuario.
                                        </p>
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in paginatedUsers"
                                    :key="String(item.id)"
                                    :class="[
                                        'cursor-pointer border-t border-[#e4ebe6] transition hover:bg-[#f5f8f6]',
                                        Number(selectedUser?.id) ===
                                        Number(item.id)
                                            ? 'bg-[#eaf7ef]'
                                            : 'bg-white',
                                    ]"
                                    @click="selectUser(item)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="flex size-8 items-center justify-center rounded-full bg-[#dff3e6] text-xs font-bold text-[#168447]"
                                                >{{ initials(item.name) }}</span
                                            ><span
                                                class="font-semibold text-[#1d2821]"
                                                >{{ item.name }}</span
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-[#536158]">
                                        {{ item.email }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div
                                            class="flex max-w-48 flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="role in getRoles(item)"
                                                :key="role.id"
                                                class="rounded-md bg-[#eaf7ef] px-2 py-1 text-xs font-medium text-[#116f3b]"
                                                >{{ role.nombre }}</span
                                            ><span
                                                v-if="!getRoles(item).length"
                                                class="text-xs text-amber-700"
                                                >Sin roles</span
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-[#748078]">
                                        No disponible
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="[
                                                'rounded-md px-2 py-1 text-xs font-semibold',
                                                item.estado
                                                    ? 'bg-[#eaf7ef] text-[#116f3b]'
                                                    : 'bg-red-50 text-red-700',
                                            ]"
                                            >{{
                                                item.estado
                                                    ? 'Activo'
                                                    : 'Inactivo'
                                            }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3" @click.stop>
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="gap-1 border-[#cfd9d2] text-[#116f3b]"
                                                @click="openEdit(item)"
                                                ><Edit3
                                                    class="size-3.5"
                                                />Editar</Button
                                            ><Button
                                                size="sm"
                                                variant="outline"
                                                :class="
                                                    item.estado
                                                        ? 'border-red-200 text-red-700 hover:bg-red-50'
                                                        : 'border-[#bde5cb] text-[#116f3b]'
                                                "
                                                :disabled="
                                                    item.estado &&
                                                    Number(item.id) ===
                                                        currentUserId
                                                "
                                                :title="
                                                    item.estado &&
                                                    Number(item.id) ===
                                                        currentUserId
                                                        ? 'No puedes desactivar tu propio usuario'
                                                        : undefined
                                                "
                                                @click="requestToggle(item)"
                                                >{{
                                                    item.estado
                                                        ? 'Desactivar'
                                                        : 'Activar'
                                                }}</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <footer
                        class="flex flex-col gap-3 border-t border-[#dfe7e2] bg-white px-4 py-3 text-sm text-[#536158] sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span
                            >Mostrando {{ rangeStart }}-{{ rangeEnd }} de
                            {{ filteredUsers.length }} usuarios</span
                        >
                        <div class="flex items-center gap-2">
                            <Button
                                size="icon"
                                variant="outline"
                                :disabled="currentPage === 1"
                                aria-label="Página anterior"
                                @click="currentPage--"
                                ><ChevronLeft class="size-4" /></Button
                            ><span class="min-w-20 text-center"
                                >Página {{ currentPage }} de
                                {{ totalPages }}</span
                            ><Button
                                size="icon"
                                variant="outline"
                                :disabled="currentPage === totalPages"
                                aria-label="Página siguiente"
                                @click="currentPage++"
                                ><ChevronRight class="size-4"
                            /></Button>
                        </div>
                    </footer>
                </section>

                <aside
                    class="company-panel overflow-hidden 2xl:sticky 2xl:top-4"
                    aria-label="Acceso del usuario"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#dfe7e2] px-5 py-4"
                    >
                        <h2 class="text-lg font-bold text-[#101713]">
                            Acceso del usuario
                        </h2>
                        <KeyRound class="size-5 text-[#168447]" />
                    </div>
                    <div
                        v-if="!selectedUser"
                        class="p-8 text-center text-sm text-[#647068]"
                    >
                        <CircleUserRound
                            class="mx-auto mb-3 size-10 text-[#9caaa1]"
                        />Selecciona un usuario para revisar su acceso.
                    </div>
                    <div v-else class="space-y-5 p-5">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-12 items-center justify-center rounded-full bg-[#dff3e6] font-bold text-[#168447]"
                                >{{ initials(selectedUser.name) }}</span
                            >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="truncate font-bold text-[#1b251f]"
                                    >
                                        {{ selectedUser.name }}
                                    </p>
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-0.5 text-xs font-semibold',
                                            selectedUser.estado
                                                ? 'bg-[#eaf7ef] text-[#116f3b]'
                                                : 'bg-red-50 text-red-700',
                                        ]"
                                        >{{
                                            selectedUser.estado
                                                ? 'Activo'
                                                : 'Inactivo'
                                        }}</span
                                    >
                                </div>
                                <p class="truncate text-sm text-[#647068]">
                                    {{ selectedUser.email }}
                                </p>
                            </div>
                        </div>

                        <section class="border-t border-[#e0e7e2] pt-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-[#202a24]">
                                    Roles asignados
                                </h3>
                                <Button
                                    v-if="!accessEditing"
                                    size="sm"
                                    variant="outline"
                                    class="gap-1 border-[#168447] text-[#116f3b]"
                                    @click="startAccessEditing"
                                    ><Plus class="size-3.5" />Asignar
                                    rol</Button
                                >
                            </div>
                            <div
                                v-if="accessEditing"
                                ref="roleEditor"
                                tabindex="-1"
                                class="grid gap-2 outline-none"
                            >
                                <label
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#dfe7e2] p-3 hover:bg-[#f5f8f6]"
                                    ><input
                                        v-model="store.state.rolesForm.role_ids"
                                        type="checkbox"
                                        :value="Number(role.id)"
                                        class="mt-0.5 size-4 accent-[#168447]"
                                    /><span
                                        ><span
                                            class="block text-sm font-semibold text-[#202a24]"
                                            >{{ role.nombre }}</span
                                        ><span
                                            class="block text-xs text-[#6e7971]"
                                            >{{
                                                role.descripcion || role.slug
                                            }}</span
                                        ></span
                                    ></label
                                ><InputError
                                    :message="store.state.errors.role_ids?.[0]"
                                />
                            </div>
                            <div v-else class="flex flex-wrap gap-2">
                                <span
                                    v-for="role in getRoles(selectedUser)"
                                    :key="role.id"
                                    class="rounded-md bg-[#eaf7ef] px-2.5 py-1.5 text-xs font-semibold text-[#116f3b]"
                                    >{{ role.nombre }}</span
                                ><span
                                    v-if="!getRoles(selectedUser).length"
                                    class="text-sm text-amber-700"
                                    >Sin roles asignados</span
                                >
                            </div>
                        </section>

                        <section class="border-t border-[#e0e7e2] pt-4">
                            <h3 class="mb-3 text-sm font-bold text-[#202a24]">
                                Resumen de permisos
                            </h3>
                            <div
                                class="overflow-hidden rounded-xl border border-[#dfe7e2]"
                            >
                                <div
                                    v-for="module in permissionSummary"
                                    :key="module.label"
                                    class="flex items-center justify-between border-b border-[#e7ede9] px-3 py-3 last:border-b-0"
                                >
                                    <div class="flex items-center gap-2">
                                        <ShieldCheck
                                            class="size-4 text-[#168447]"
                                        /><span
                                            class="text-sm font-semibold text-[#29342d]"
                                            >{{ module.label }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-2 text-xs text-[#647068]"
                                    >
                                        <span
                                            >{{ module.allowed }} de
                                            {{ module.total }}</span
                                        ><Check
                                            v-if="
                                                module.allowed ===
                                                    module.total && module.total
                                            "
                                            class="size-4 text-[#168447]"
                                        />
                                    </div>
                                </div>
                                <div
                                    v-if="!permissionSummary.length"
                                    class="p-3 text-sm text-[#6e7971]"
                                >
                                    No hay permisos activos configurados.
                                </div>
                            </div>
                        </section>

                        <div v-if="accessEditing" class="grid gap-2">
                            <Button
                                class="company-action-primary min-h-11 gap-2"
                                :disabled="store.state.saving || !accessDirty"
                                @click="saveAccess"
                                ><LoaderCircle
                                    v-if="store.state.saving"
                                    class="size-4 animate-spin"
                                /><Save v-else class="size-4" />{{
                                    store.state.saving
                                        ? 'Guardando...'
                                        : 'Guardar roles'
                                }}</Button
                            ><Button
                                variant="outline"
                                @click="cancelAccessEditing"
                                >Cancelar cambios</Button
                            >
                        </div>
                        <Button
                            v-if="selectedUser.estado"
                            variant="outline"
                            class="min-h-11 w-full gap-2 border-red-300 text-red-700 hover:bg-red-50"
                            :disabled="
                                Number(selectedUser.id) === currentUserId ||
                                store.state.saving
                            "
                            @click="requestToggle(selectedUser)"
                            ><UserMinus class="size-4" />Desactivar
                            usuario</Button
                        >
                    </div>
                </aside>
            </div>
        </div>

        <Dialog v-model:open="isFormOpen">
            <DialogContent
                class="max-h-[92vh] max-w-3xl overflow-y-auto border-[#dfe7e2] p-0"
            >
                <div class="company-panel overflow-hidden shadow-none">
                    <div class="company-hero px-6 py-5 text-white">
                        <DialogHeader
                            ><DialogTitle
                                class="text-left text-xl font-bold text-white"
                                >{{
                                    isEditing
                                        ? 'Editar usuario'
                                        : 'Nuevo usuario'
                                }}</DialogTitle
                        ><DialogDescription
                                class="text-left text-emerald-50/80"
                                >Completa los datos de acceso y roles.</DialogDescription
                            ></DialogHeader
                        >
                    </div>
                    <form class="space-y-6 p-6" @submit.prevent="saveUser">
                        <div class="company-form-grid">
                            <div class="company-field">
                                <Label for="name" class="company-label"
                                    >Nombre completo</Label
                                ><Input
                                    id="name"
                                    v-model="store.state.form.name"
                                    class="company-input"
                                    required
                                /><InputError
                                    :message="store.state.errors.name?.[0]"
                                />
                            </div>
                            <div class="company-field">
                                <Label for="email" class="company-label"
                                    >Correo electrónico</Label
                                ><Input
                                    id="email"
                                    v-model="store.state.form.email"
                                    type="email"
                                    class="company-input"
                                    required
                                /><InputError
                                    :message="store.state.errors.email?.[0]"
                                />
                            </div>
                            <div class="company-field md:col-span-2">
                                <Label for="password" class="company-label"
                                    >Contraseña</Label
                                ><Input
                                    id="password"
                                    v-model="store.state.form.password"
                                    type="password"
                                    class="company-input"
                                    :required="!isEditing"
                                />
                                <p class="text-xs text-[#6e7971]">
                                    {{
                                        isEditing
                                            ? 'Déjala vacía para conservar la contraseña actual.'
                                            : 'Debe tener al menos 8 caracteres.'
                                    }}
                                </p>
                                <InputError
                                    :message="store.state.errors.password?.[0]"
                                />
                            </div>
                        </div>
                        <fieldset>
                            <legend
                                class="mb-3 text-sm font-bold text-[#202a24]"
                            >
                                Roles iniciales
                            </legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="flex items-center gap-3 rounded-lg border border-[#dfe7e2] p-3"
                                    ><input
                                        v-model="store.state.form.role_ids"
                                        type="checkbox"
                                        :value="Number(role.id)"
                                        class="size-4 accent-[#168447]"
                                    /><span class="text-sm font-semibold">{{
                                        role.nombre
                                    }}</span></label
                                >
                            </div>
                            <InputError
                                :message="store.state.errors.role_ids?.[0]"
                            />
                        </fieldset>
                        <label class="company-switch"
                            ><input
                                v-model="store.state.form.estado"
                                type="checkbox"
                                class="size-4 accent-[#168447]"
                            /><span
                                ><span class="block text-sm font-semibold"
                                    >Usuario activo</span
                                ><span class="block text-sm text-[#647068]"
                                    >Permite iniciar sesión y operar según sus
                                    permisos.</span
                                ></span
                            ></label
                        >
                        <div
                            v-if="store.state.error"
                            class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
                            role="alert"
                        >
                            {{ store.state.error }}
                        </div>
                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="isFormOpen = false"
                                >Cancelar</Button
                            ><Button
                                type="submit"
                                class="company-action-primary"
                                :disabled="store.state.saving"
                                ><LoaderCircle
                                    v-if="store.state.saving"
                                    class="mr-2 size-4 animate-spin"
                                />{{
                                    store.state.saving
                                        ? 'Guardando...'
                                        : isEditing
                                          ? 'Actualizar usuario'
                                          : 'Crear usuario'
                                }}</Button
                            >
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isDeactivateOpen"
            ><DialogContent class="max-w-md"
                ><DialogHeader
                    ><DialogTitle>Desactivar usuario</DialogTitle
                    ><DialogDescription
                        >El usuario {{ deactivateTarget?.name }} dejar? de
                        acceder al sistema. Sus roles se conservarán para una posible
                        reactivación.</DialogDescription
                    ></DialogHeader
                >
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="isDeactivateOpen = false"
                        >Cancelar</Button
                    ><Button
                        class="bg-red-600 text-white hover:bg-red-700"
                        :disabled="store.state.saving"
                        @click="confirmDeactivate"
                        >{{
                            store.state.saving
                                ? 'Desactivando...'
                                : 'Desactivar usuario'
                        }}</Button
                    >
                </div></DialogContent
            ></Dialog
        >
        <Dialog v-model:open="isDiscardOpen"
            ><DialogContent class="max-w-md"
                ><DialogHeader
                    ><DialogTitle>Descartar cambios sin guardar</DialogTitle
                    ><DialogDescription
                        >Hay cambios pendientes en roles. Si
                        continúas, se perderán.</DialogDescription
                    ></DialogHeader
                >
                <div class="flex justify-end gap-3">
                    <Button variant="outline" @click="isDiscardOpen = false"
                        >Seguir editando</Button
                    ><Button
                        class="company-action-primary"
                        @click="discardAndSelect"
                        >Descartar y continuar</Button
                    >
                </div></DialogContent
            ></Dialog
        >
    </ModulePageLayout>
</template>



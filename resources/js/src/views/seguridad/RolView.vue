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
import { useRolStore } from '@/src/stores/rolStore';
import { KeyRound, Search, ShieldCheck, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Item = Record<string, unknown>;

const store = useRolStore();
const isFormOpen = ref(false);
const isPermissionsOpen = ref(false);
const search = ref('');
const estadoFilter = ref('todos');
const moduloFilter = ref('todos');

const isEditing = computed(() => store.state.editingId !== null);
const permissions = computed(
    () => (store.state.meta.permissions as Item[] | undefined) ?? [],
);

const normalize = (value: unknown) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const getPermissions = (item: Item) =>
    (item.permissions as Item[] | undefined) ?? [];

const permissionModules = computed(() =>
    Array.from(
        new Set(permissions.value.map((permission) => String(permission.modulo))),
    ).sort(),
);

const permissionsByModule = computed(() =>
    permissionModules.value.map((module) => ({
        module,
        permissions: permissions.value.filter(
            (permission) => String(permission.modulo) === module,
        ),
    })),
);

const filteredRoles = computed(() => {
    const query = normalize(search.value.trim());

    return store.state.items.filter((item) => {
        const rolePermissions = getPermissions(item);
        const matchesSearch =
            !query ||
            [
                item.nombre,
                item.slug,
                item.descripcion,
                ...rolePermissions.map((permission) => permission.nombre),
                ...rolePermissions.map((permission) => permission.slug),
            ].some((value) => normalize(value).includes(query));
        const matchesStatus =
            estadoFilter.value === 'todos' ||
            Boolean(item.estado) === (estadoFilter.value === 'activos');
        const matchesModule =
            moduloFilter.value === 'todos' ||
            rolePermissions.some(
                (permission) => String(permission.modulo) === moduloFilter.value,
            );

        return matchesSearch && matchesStatus && matchesModule;
    });
});

const kpis = computed(() => ({
    total: store.state.items.length,
    activos: store.state.items.filter((item) => Boolean(item.estado)).length,
    permisosAsignados: store.state.items.reduce(
        (total, item) => total + getPermissions(item).length,
        0,
    ),
    usuariosAsignados: store.state.items.reduce(
        (total, item) => total + Number(item.users_count ?? 0),
        0,
    ),
}));

const openCreate = () => {
    store.startCreate();
    isFormOpen.value = true;
};

const openEdit = (item: Item) => {
    store.startEdit(item);
    isFormOpen.value = true;
};

const openPermissions = (item: Item) => {
    store.preparePermissions(item);
    isPermissionsOpen.value = true;
};

const saveRol = async () => {
    await store.save();
    isFormOpen.value = false;
};

const savePermissions = async () => {
    await store.assignPermissions();
    isPermissionsOpen.value = false;
};

onMounted(async () => {
    await store.load();
});
</script>

<template>
    <ModulePageLayout
        title="Usuarios y roles"
        description="Construye perfiles de acceso y relaciona permisos para el sistema empresarial."
        :breadcrumbs="[
            { title: 'Seguridad', href: '/dashboard' },
            { title: 'Roles', href: '/seguridad/roles' },
        ]"
        compact
    >
        <div class="company-panel overflow-hidden">
            <div class="overflow-x-auto border-b border-[#dfe7e2] px-4 pt-1">
                <SeguridadTabs active="roles" />
            </div>
        </div>

        <section class="grid gap-3 md:grid-cols-4">
            <div class="company-panel p-4">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Roles</p>
                        <p class="text-2xl font-semibold">{{ kpis.total }}</p>
                    </div>
                </div>
            </div>
            <div class="company-panel p-4">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Activos</p>
                        <p class="text-2xl font-semibold">{{ kpis.activos }}</p>
                    </div>
                </div>
            </div>
            <div class="company-panel p-4">
                <div class="flex items-center gap-3">
                    <KeyRound class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Permisos asignados</p>
                        <p class="text-2xl font-semibold">
                            {{ kpis.permisosAsignados }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="company-panel p-4">
                <div class="flex items-center gap-3">
                    <Users class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Usuarios vinculados</p>
                        <p class="text-2xl font-semibold">
                            {{ kpis.usuariosAsignados }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="company-panel overflow-hidden">
            <div
                class="flex flex-col gap-4 border-b border-border/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">Listado de roles</h2>
                    <p class="text-sm text-muted-foreground">
                        Cada rol agrupa permisos reutilizables para el sistema.
                    </p>
                </div>
                <Button class="company-action-primary" @click="openCreate">
                    Nuevo rol
                </Button>
            </div>

            <div class="grid gap-3 border-b border-border/60 px-5 py-4 md:grid-cols-[1fr_180px_220px]">
                <label class="relative">
                    <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        class="company-input pl-9"
                        placeholder="Buscar rol, slug o permiso"
                    />
                </label>
                <select v-model="estadoFilter" class="company-select">
                    <option value="todos">Todos los estados</option>
                    <option value="activos">Activos</option>
                    <option value="inactivos">Inactivos</option>
                </select>
                <select v-model="moduloFilter" class="company-select">
                    <option value="todos">Todos los modulos</option>
                    <option
                        v-for="module in permissionModules"
                        :key="module"
                        :value="module"
                    >
                        {{ module }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/55 text-left">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Rol</th>
                            <th class="px-4 py-3 font-semibold">Slug</th>
                            <th class="px-4 py-3 font-semibold">Permisos</th>
                            <th class="px-4 py-3 font-semibold">Uso</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="store.state.loading">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                Cargando roles...
                            </td>
                        </tr>
                        <tr
                            v-for="item in filteredRoles"
                            :key="String(item.id)"
                            class="border-t border-border/60 hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ item.nombre }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.descripcion || 'Sin descripcion' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ item.slug }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="getPermissions(item).length"
                                    class="flex max-w-xl flex-wrap gap-1"
                                >
                                    <span
                                        v-for="permission in getPermissions(item)"
                                        :key="String(permission.id)"
                                        class="rounded-full bg-[#eaf7ef] px-2 py-1 text-xs font-medium text-[#116f3b]"
                                    >
                                        {{ permission.nombre }}
                                    </span>
                                </div>
                                <span
                                    v-else
                                    class="text-xs font-medium text-amber-700"
                                >
                                    Sin permisos asignados
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ Number(item.users_count ?? 0) }} usuarios
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'rounded-md px-2.5 py-1 text-xs font-semibold',
                                        item.estado
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-rose-100 text-rose-800',
                                    ]"
                                >
                                    {{ item.estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        class="company-action-secondary"
                                        @click="openEdit(item)"
                                    >
                                        Editar
                                    </Button>
                                    <Button
                                        variant="outline"
                                        class="company-action-secondary"
                                        @click="openPermissions(item)"
                                    >
                                        Permisos
                                    </Button>
                                    <Button
                                        variant="outline"
                                        @click="store.toggleEstado(item)"
                                    >
                                        {{ item.estado ? 'Desactivar' : 'Activar' }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="!store.state.loading && !filteredRoles.length"
                        >
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No hay roles que coincidan con los filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="isFormOpen">
            <DialogContent class="max-w-2xl border-border/80 p-0">
                <div class="company-panel overflow-hidden shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader>
                            <DialogTitle
                                class="text-left text-lg font-semibold text-white"
                            >
                                {{ isEditing ? 'Editar rol' : 'Nuevo rol' }}
                            </DialogTitle>
                            <DialogDescription class="text-left text-emerald-50/80">
                                Define nombre, slug y descripcion del rol.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <form class="space-y-5 p-5 md:p-6" @submit.prevent="saveRol">
                        <div class="company-form-grid">
                            <div class="company-field">
                                <Label for="nombre" class="company-label">
                                    Nombre
                                </Label>
                                <Input
                                    id="nombre"
                                    v-model="store.state.form.nombre"
                                    class="company-input"
                                />
                                <InputError :message="store.state.errors.nombre?.[0]" />
                            </div>
                            <div class="company-field">
                                <Label for="slug" class="company-label">Slug</Label>
                                <Input
                                    id="slug"
                                    v-model="store.state.form.slug"
                                    class="company-input"
                                />
                                <InputError :message="store.state.errors.slug?.[0]" />
                            </div>
                            <div class="company-field md:col-span-2">
                                <Label for="descripcion" class="company-label">
                                    Descripcion
                                </Label>
                                <textarea
                                    id="descripcion"
                                    v-model="store.state.form.descripcion"
                                    class="company-textarea"
                                />
                                <InputError
                                    :message="store.state.errors.descripcion?.[0]"
                                />
                            </div>
                            <label class="company-switch md:col-span-2">
                                <input
                                    v-model="store.state.form.estado"
                                    type="checkbox"
                                    class="h-4 w-4 accent-[hsl(150_54%_19%)]"
                                />
                                <span class="space-y-1">
                                    <span class="block text-sm font-semibold text-foreground">
                                        Rol activo
                                    </span>
                                    <span class="block text-sm text-muted-foreground">
                                        Puede ser asignado a usuarios.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="company-action-secondary"
                                @click="isFormOpen = false"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                class="company-action-primary"
                                :disabled="store.state.saving"
                            >
                                {{
                                    store.state.saving
                                        ? 'Guardando...'
                                        : isEditing
                                          ? 'Actualizar'
                                          : 'Guardar'
                                }}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isPermissionsOpen">
            <DialogContent class="max-w-3xl border-border/80 p-0">
                <div class="company-panel overflow-hidden shadow-none">
                    <div class="company-hero px-5 py-5 text-white">
                        <DialogHeader>
                            <DialogTitle class="text-left text-lg font-semibold text-white">
                                Asignar permisos
                            </DialogTitle>
                            <DialogDescription class="text-left text-emerald-50/80">
                                Selecciona los permisos que formaran parte del rol.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <form
                        class="space-y-5 p-5 md:p-6"
                        @submit.prevent="savePermissions"
                    >
                        <div class="company-field">
                            <Label class="company-label">Permisos disponibles</Label>
                            <div
                                class="max-h-[420px] space-y-4 overflow-y-auto rounded-xl border border-border bg-muted/20 p-4"
                            >
                                <section
                                    v-for="group in permissionsByModule"
                                    :key="group.module"
                                    class="space-y-2"
                                >
                                    <h3 class="text-sm font-semibold text-emerald-800">
                                        {{ group.module }}
                                    </h3>
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <label
                                            v-for="permission in group.permissions"
                                            :key="String(permission.id)"
                                            class="flex items-start gap-2 rounded-lg bg-card p-3 text-sm shadow-sm"
                                        >
                                            <input
                                                v-model="
                                                    store.state.permissionsForm
                                                        .permission_ids
                                                "
                                                type="checkbox"
                                                class="mt-1 h-4 w-4 accent-[hsl(150_54%_19%)]"
                                                :value="Number(permission.id)"
                                            />
                                            <span>
                                                <span class="block font-semibold">
                                                    {{ permission.nombre }}
                                                </span>
                                                <span
                                                    class="block text-xs text-muted-foreground"
                                                >
                                                    {{ permission.slug }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </section>
                            </div>
                            <InputError
                                :message="store.state.errors.permission_ids?.[0]"
                            />
                        </div>

                        <div class="flex justify-end gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="company-action-secondary"
                                @click="isPermissionsOpen = false"
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                class="company-action-primary"
                                :disabled="store.state.saving"
                            >
                                Guardar permisos
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </ModulePageLayout>
</template>

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
import { usePermisoStore } from '@/src/stores/permisoStore';
import { KeyRound, Layers3, Search, ShieldCheck } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Item = Record<string, unknown>;

const store = usePermisoStore();
const isFormOpen = ref(false);
const search = ref('');
const estadoFilter = ref('todos');
const moduloFilter = ref('todos');

const isEditing = computed(() => store.state.editingId !== null);
const modules = computed(() => (store.state.meta.modules as Item[] | undefined) ?? []);

const normalize = (value: unknown) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const moduleLabel = (value: unknown) =>
    modules.value.find((module) => String(module.value) === String(value))
        ?.label ?? String(value ?? 'Sin módulo');

const filteredPermisos = computed(() => {
    const query = normalize(search.value.trim());

    return store.state.items.filter((item) => {
        const matchesSearch =
            !query ||
            [item.nombre, item.slug, item.descripcion, item.modulo].some(
                (value) => normalize(value).includes(query),
            );
        const matchesStatus =
            estadoFilter.value === 'todos' ||
            Boolean(item.estado) === (estadoFilter.value === 'activos');
        const matchesModule =
            moduloFilter.value === 'todos' ||
            String(item.modulo) === moduloFilter.value;

        return matchesSearch && matchesStatus && matchesModule;
    });
});

const kpis = computed(() => ({
    total: store.state.items.length,
    activos: store.state.items.filter((item) => Boolean(item.estado)).length,
    modulos: new Set(store.state.items.map((item) => String(item.modulo))).size,
    rolesVinculados: store.state.items.reduce(
        (total, item) => total + Number(item.roles_count ?? 0),
        0,
    ),
}));

const groupedByModule = computed(() =>
    modules.value
        .map((module) => {
            const value = String(module.value);
            const items = store.state.items.filter(
                (item) => String(item.modulo) === value,
            );

            return {
                value,
                label: String(module.label),
                total: items.length,
                activos: items.filter((item) => Boolean(item.estado)).length,
            };
        })
        .filter((module) => module.total > 0),
);

const openCreate = () => {
    store.startCreate();
    isFormOpen.value = true;
};

const openEdit = (item: Item) => {
    store.startEdit(item);
    isFormOpen.value = true;
};

const savePermiso = async () => {
    await store.save();
    isFormOpen.value = false;
};

onMounted(async () => {
    await store.load();
});
</script>

<template>
    <ModulePageLayout
        title="Usuarios y roles"
        description="Administra permisos por modulo para construir acceso fino a vistas y acciones."
        :breadcrumbs="[
            { title: 'Seguridad', href: '/dashboard' },
            { title: 'Permisos', href: '/seguridad/permisos' },
        ]"
        compact
    >
        <div class="company-panel overflow-hidden">
            <div class="overflow-x-auto border-b border-[#dfe7e2] px-4 pt-1">
                <SeguridadTabs active="permisos" />
            </div>
        </div>

        <section class="grid gap-3 md:grid-cols-4">
            <div class="company-panel p-4">
                <div class="flex items-center gap-3">
                    <KeyRound class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Permisos</p>
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
                    <Layers3 class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Modulos cubiertos</p>
                        <p class="text-2xl font-semibold">{{ kpis.modulos }}</p>
                    </div>
                </div>
            </div>
            <div class="company-panel p-4">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="size-5 text-emerald-700" />
                    <div>
                        <p class="text-xs text-muted-foreground">Usos en roles</p>
                        <p class="text-2xl font-semibold">
                            {{ kpis.rolesVinculados }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="groupedByModule.length"
            class="company-panel grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <button
                v-for="module in groupedByModule"
                :key="module.value"
                type="button"
                class="rounded-xl border border-border bg-card p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50/50"
                @click="moduloFilter = module.value"
            >
                <p class="font-semibold">{{ module.label }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ module.activos }} activos de {{ module.total }} permisos
                </p>
            </button>
        </section>

        <div class="company-panel overflow-hidden">
            <div
                class="flex flex-col gap-4 border-b border-border/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">Listado de permisos</h2>
                    <p class="text-sm text-muted-foreground">
                        Base para menus dinamicos, middleware y politicas futuras.
                    </p>
                </div>
                <Button class="company-action-primary" @click="openCreate">
                    Nuevo permiso
                </Button>
            </div>

            <div class="grid gap-3 border-b border-border/60 px-5 py-4 md:grid-cols-[1fr_180px_220px]">
                <label class="relative">
                    <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        class="company-input pl-9"
                        placeholder="Buscar permiso, slug o modulo"
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
                        v-for="module in modules"
                        :key="String(module.value)"
                        :value="String(module.value)"
                    >
                        {{ module.label }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/55 text-left">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Permiso</th>
                            <th class="px-4 py-3 font-semibold">Slug</th>
                            <th class="px-4 py-3 font-semibold">Modulo</th>
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
                                Cargando permisos...
                            </td>
                        </tr>
                        <tr
                            v-for="item in filteredPermisos"
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
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">
                                    {{ moduleLabel(item.modulo) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ Number(item.roles_count ?? 0) }} roles
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
                                        @click="store.toggleEstado(item)"
                                    >
                                        {{ item.estado ? 'Desactivar' : 'Activar' }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-if="!store.state.loading && !filteredPermisos.length"
                        >
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No hay permisos que coincidan con los filtros.
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
                            <DialogTitle class="text-left text-lg font-semibold text-white">
                                {{ isEditing ? 'Editar permiso' : 'Nuevo permiso' }}
                            </DialogTitle>
                            <DialogDescription class="text-left text-emerald-50/80">
                                Define el permiso y el modulo funcional al que pertenece.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <form
                        class="space-y-5 p-5 md:p-6"
                        @submit.prevent="savePermiso"
                    >
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
                            <div class="company-field">
                                <Label for="modulo" class="company-label">
                                    Modulo
                                </Label>
                                <select
                                    id="modulo"
                                    v-model="store.state.form.modulo"
                                    class="company-select"
                                >
                                    <option value="">Seleccione un modulo</option>
                                    <option
                                        v-for="module in modules"
                                        :key="String(module.value)"
                                        :value="String(module.value)"
                                    >
                                        {{ module.label }}
                                    </option>
                                </select>
                                <InputError :message="store.state.errors.modulo?.[0]" />
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
                                        Permiso activo
                                    </span>
                                    <span class="block text-sm text-muted-foreground">
                                        Disponible para asignarlo a roles.
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
    </ModulePageLayout>
</template>

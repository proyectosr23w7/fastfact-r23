<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import { ApiError } from '@/src/services/apiClient';
import { clienteVentaService } from '@/src/services/clienteVentaService';
import { CheckCircle2, Pencil, Plus, Search, UsersRound, XCircle } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type Item = Record<string, unknown>;

const items = ref<Item[]>([]);
const meta = ref<Record<string, unknown>>({});
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const estado = ref('todos');
const editingId = ref<number | null>(null);
const errors = ref<Record<string, string[]>>({});
const notice = ref('');
const loadError = ref('');

const form = reactive({
    razon_social: '',
    nit_ci: '',
    tipo_documento_identidad: '',
    complemento: '',
    telefono: '',
    correo: '',
    estado: true,
});

const documentosIdentidad = computed(
    () =>
        (meta.value.documentos_identidad as Item[] | undefined) ?? [],
);
const isEditing = computed(() => editingId.value !== null);
const kpis = computed(() => ({
    total: items.value.length,
    activos: items.value.filter((item) => Boolean(item.estado)).length,
    inactivos: items.value.filter((item) => !Boolean(item.estado)).length,
}));

const resetForm = () => {
    editingId.value = null;
    errors.value = {};
    form.razon_social = '';
    form.nit_ci = '';
    form.tipo_documento_identidad = '';
    form.complemento = '';
    form.telefono = '';
    form.correo = '';
    form.estado = true;
};

const load = async () => {
    loading.value = true;
    notice.value = '';
    loadError.value = '';

    try {
        const response = await clienteVentaService.list({
            search: search.value,
            estado: estado.value,
        });
        items.value = response.data;
        meta.value = response.meta ?? {};
    } catch (error) {
        loadError.value =
            error instanceof ApiError
                ? error.message
                : 'No se pudo cargar el listado de clientes.';
        items.value = [];
    } finally {
        loading.value = false;
    }
};

const edit = (item: Item) => {
    editingId.value = Number(item.id);
    errors.value = {};
    form.razon_social = String(item.razon_social ?? '');
    form.nit_ci = String(item.nit_ci ?? '');
    form.tipo_documento_identidad = String(item.tipo_documento_identidad ?? '');
    form.complemento = String(item.complemento ?? '');
    form.telefono = String(item.telefono ?? '');
    form.correo = String(item.correo ?? '');
    form.estado = Boolean(item.estado);
};

const save = async () => {
    saving.value = true;
    errors.value = {};
    notice.value = '';

    try {
        const payload = { ...form };
        const response = isEditing.value
            ? await clienteVentaService.update(Number(editingId.value), payload)
            : await clienteVentaService.create(payload);

        notice.value = String(response.message ?? 'Cliente guardado correctamente.');
        resetForm();
        await load();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            errors.value = error.errors;
            notice.value = error.message;
            return;
        }

        throw error;
    } finally {
        saving.value = false;
    }
};

const toggleEstado = async (item: Item) => {
    const id = Number(item.id);
    if (!id) return;

    await clienteVentaService.updateEstado(id, !Boolean(item.estado));
    await load();
};

onMounted(load);
</script>

<template>
    <ModulePageLayout
        title="Clientes"
        description="Administra los datos fiscales usados en la emisión de facturas."
        :breadcrumbs="[
            { title: 'Facturación', href: '/facturacion/facturas' },
            { title: 'Clientes', href: '/facturacion/clientes' },
        ]"
        compact
    >
        <template #actions>
            <Button class="bg-[#168447] text-white hover:bg-[#126B3B]" @click="resetForm">
                <Plus class="size-4" />
                Nuevo cliente
            </Button>
        </template>

        <div class="grid gap-4 lg:grid-cols-[1fr_360px]">
            <section class="company-panel overflow-hidden">
                <div class="grid gap-3 border-b border-[#dfe7e2] p-4 md:grid-cols-[1fr_160px_auto]">
                    <div class="relative">
                        <Search class="pointer-events-none absolute top-3 left-3 size-4 text-[#68766D]" />
                        <Input
                            v-model="search"
                            class="company-input pl-9"
                            placeholder="Buscar por razón social, NIT/CI o código"
                            @keyup.enter="load"
                        />
                    </div>
                    <select v-model="estado" class="company-select">
                        <option value="todos">Todos</option>
                        <option value="activos">Activos</option>
                        <option value="inactivos">Inactivos</option>
                    </select>
                    <Button variant="outline" class="border-[#A8D2B7] text-[#126B3B]" @click="load">
                        Buscar
                    </Button>
                </div>

                <div
                    v-if="loadError"
                    class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ loadError }}
                </div>

                <div class="grid gap-3 border-b border-[#dfe7e2] p-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-[#dfe7e2] bg-white p-3">
                        <p class="text-xs text-[#65736b]">Clientes</p>
                        <p class="text-2xl font-semibold">{{ kpis.total }}</p>
                    </div>
                    <div class="rounded-lg border border-[#dfe7e2] bg-white p-3">
                        <p class="text-xs text-[#65736b]">Activos</p>
                        <p class="text-2xl font-semibold">{{ kpis.activos }}</p>
                    </div>
                    <div class="rounded-lg border border-[#dfe7e2] bg-white p-3">
                        <p class="text-xs text-[#65736b]">Inactivos</p>
                        <p class="text-2xl font-semibold">{{ kpis.inactivos }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#dfe7e2] text-sm">
                        <thead class="bg-[#f6faf7] text-left text-xs font-semibold text-[#536158] uppercase">
                            <tr>
                                <th class="px-4 py-3">Cliente</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Contacto</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf2ef] bg-white">
                            <tr v-if="loading">
                                <td colspan="5" class="px-4 py-8 text-center text-[#65736b]">Cargando clientes...</td>
                            </tr>
                            <tr v-else-if="items.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-[#65736b]">No se encontraron clientes.</td>
                            </tr>
                            <tr v-for="item in items" v-else :key="String(item.id)" class="hover:bg-[#f9fbfa]">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-[#101713]">{{ item.razon_social || item.nombre }}</p>
                                    <p class="text-xs text-[#65736b]">{{ item.codigo }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-mono text-[#101713]">{{ item.nit_ci }}{{ item.complemento ? `-${item.complemento}` : '' }}</p>
                                    <p class="text-xs text-[#65736b]">Tipo {{ item.tipo_documento_identidad || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-[#536158]">
                                    <p>{{ item.telefono || 'Sin teléfono' }}</p>
                                    <p class="text-xs">{{ item.correo || 'Sin correo' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold',
                                            item.estado ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600',
                                        ]"
                                    >
                                        {{ item.estado ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" class="h-8 px-2" @click="edit(item)">
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button size="sm" variant="outline" class="h-8 px-2" @click="toggleEstado(item)">
                                            <CheckCircle2 v-if="!item.estado" class="size-4" />
                                            <XCircle v-else class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="company-panel p-4">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-[#eaf7ef] text-[#168447]">
                        <UsersRound class="size-5" />
                    </div>
                    <div>
                        <h2 class="font-semibold">{{ isEditing ? 'Editar cliente' : 'Nuevo cliente' }}</h2>
                        <p class="text-xs text-[#65736b]">Datos fiscales para facturación</p>
                    </div>
                </div>

                <form class="space-y-4" @submit.prevent="save">
                    <div class="space-y-2">
                        <Label for="razon_social">Razón social</Label>
                        <Input id="razon_social" v-model="form.razon_social" class="company-input" />
                        <InputError :message="errors.razon_social?.[0]" />
                    </div>
                    <div class="space-y-2">
                        <Label for="tipo_documento_identidad">Tipo de documento SIAT</Label>
                        <select id="tipo_documento_identidad" v-model="form.tipo_documento_identidad" class="company-select">
                            <option value="">Seleccione un tipo</option>
                            <option
                                v-for="documento in documentosIdentidad"
                                :key="String(documento.codigo_clasificador)"
                                :value="String(documento.codigo_clasificador)"
                            >
                                {{ documento.descripcion }}
                            </option>
                        </select>
                        <InputError :message="errors.tipo_documento_identidad?.[0]" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[1fr_110px]">
                        <div class="space-y-2">
                            <Label for="nit_ci">NIT / CI</Label>
                            <Input id="nit_ci" v-model="form.nit_ci" class="company-input" />
                            <InputError :message="errors.nit_ci?.[0]" />
                        </div>
                        <div v-if="String(form.tipo_documento_identidad) === '1'" class="space-y-2">
                            <Label for="complemento">Complemento</Label>
                            <Input id="complemento" v-model="form.complemento" class="company-input" />
                            <InputError :message="errors.complemento?.[0]" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="telefono">Teléfono</Label>
                        <Input id="telefono" v-model="form.telefono" class="company-input" />
                        <InputError :message="errors.telefono?.[0]" />
                    </div>
                    <div class="space-y-2">
                        <Label for="correo">Correo</Label>
                        <Input id="correo" v-model="form.correo" class="company-input" />
                        <InputError :message="errors.correo?.[0]" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.estado" type="checkbox" class="size-4 accent-[#168447]" />
                        Cliente activo
                    </label>
                    <p v-if="notice" class="rounded-lg border border-[#dfe7e2] bg-[#f6faf7] px-3 py-2 text-sm text-[#536158]">
                        {{ notice }}
                    </p>
                    <div class="flex gap-2">
                        <Button type="submit" class="flex-1 bg-[#168447] text-white hover:bg-[#126B3B]" :disabled="saving">
                            {{ saving ? 'Guardando...' : 'Guardar' }}
                        </Button>
                        <Button type="button" variant="outline" class="border-[#dfe7e2]" @click="resetForm">
                            Limpiar
                        </Button>
                    </div>
                </form>
            </aside>
        </div>
    </ModulePageLayout>
</template>

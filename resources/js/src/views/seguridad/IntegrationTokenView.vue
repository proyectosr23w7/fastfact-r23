<script setup lang="ts">
import { ApiError } from '@/src/services/apiClient';
import { integrationTokenService } from '@/src/services/integrationTokenService';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import ModulePageLayout from '@/layouts/modules/ModulePageLayout.vue';
import SeguridadTabs from '@/src/components/seguridad/SeguridadTabs.vue';
import {
    AlertCircle,
    Check,
    Clipboard,
    KeyRound,
    LoaderCircle,
    Plus,
    ShieldCheck,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

type Item = Record<string, any>;

const defaultForm = () => ({
    user_id: '',
    name: 'API Administrador',
    expires_at: '',
    abilities: [] as string[],
});

const state = reactive({
    tokens: [] as Item[],
    users: [] as Item[],
    availableAbilities: [] as string[],
    form: defaultForm(),
    loading: false,
    saving: false,
    revokingId: null as number | null,
    errors: {} as Record<string, string[]>,
    error: '',
    success: '',
});

const generatedToken = ref('');
const copied = ref(false);

const abilityLabels: Record<string, string> = {
    'integracion.productos.manage': 'Productos',
    'integracion.clientes.manage': 'Clientes',
    'integracion.facturas.emitir': 'Emitir facturas',
    'integracion.facturas.anular': 'Anular facturas',
    'integracion.facturas.revertir': 'Revertir anulacion',
    'integracion.cuis.manage': 'CUIS',
    'integracion.cufd.manage': 'CUFD',
};

const activeTokens = computed(
    () => state.tokens.filter((token) => token.is_usable).length,
);

const clearFeedback = () => {
    state.errors = {};
    state.error = '';
    state.success = '';
    copied.value = false;
};

const errorMessage = (error: unknown) =>
    error instanceof ApiError
        ? error.message
        : 'No fue posible completar la operacion. Intenta nuevamente.';

const load = async () => {
    state.loading = true;
    state.error = '';

    try {
        const response = await integrationTokenService.list();
        state.tokens = response.data;
        state.users = (response.meta?.users as Item[] | undefined) ?? [];
        state.availableAbilities =
            (response.meta?.abilities as string[] | undefined) ?? [];

        if (!state.form.user_id && state.users[0]) {
            state.form.user_id = String(state.users[0].id);
        }

        if (!state.form.abilities.length) {
            state.form.abilities = [...state.availableAbilities];
        }
    } catch (error) {
        state.error = errorMessage(error);
    } finally {
        state.loading = false;
    }
};

const createToken = async () => {
    state.saving = true;
    clearFeedback();
    generatedToken.value = '';

    try {
        const payload: Record<string, unknown> = {
            user_id: Number(state.form.user_id),
            name: state.form.name,
            abilities: state.form.abilities,
        };

        if (state.form.expires_at) {
            payload.expires_at = state.form.expires_at;
        }

        const response = await integrationTokenService.create(payload);
        const data = response.data as Item;
        generatedToken.value = String(data.plain_text_token ?? '');
        state.success = response.message ?? 'Token generado correctamente.';
        state.form = {
            ...defaultForm(),
            user_id: state.form.user_id,
            abilities: [...state.availableAbilities],
        };
        await load();
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            state.errors = error.errors;
        }
        state.error = errorMessage(error);
    } finally {
        state.saving = false;
    }
};

const revokeToken = async (token: Item) => {
    state.revokingId = Number(token.id);
    clearFeedback();

    try {
        await integrationTokenService.revoke(Number(token.id));
        state.success = 'Token revocado correctamente.';
        await load();
    } catch (error) {
        state.error = errorMessage(error);
    } finally {
        state.revokingId = null;
    }
};

const copyToken = async () => {
    if (!generatedToken.value) return;
    await navigator.clipboard.writeText(generatedToken.value);
    copied.value = true;
};

const formatDate = (value: unknown) => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(String(value)));
};

onMounted(load);
</script>

<template>
    <ModulePageLayout
        title="Tokens API"
        description="Genera y revoca credenciales Bearer para integraciones externas."
        :breadcrumbs="[
            { title: 'Seguridad', href: '/dashboard' },
            { title: 'Tokens API', href: '/seguridad/tokens-integracion' },
        ]"
        compact
    >
        <div class="space-y-4">
            <div class="company-panel overflow-hidden">
                <div class="overflow-x-auto border-b border-[#dfe7e2] px-4 pt-1">
                    <SeguridadTabs active="tokens" />
                </div>
            </div>

            <div
                v-if="state.error"
                class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                <AlertCircle class="mt-0.5 size-4 shrink-0" />
                <span>{{ state.error }}</span>
                <button class="ml-auto" type="button" @click="clearFeedback">
                    <X class="size-4" />
                </button>
            </div>

            <div
                v-if="state.success"
                class="flex items-start gap-3 rounded-xl border border-[#bde5cb] bg-[#eaf7ef] px-4 py-3 text-sm text-[#116f3b]"
                role="status"
            >
                <Check class="mt-0.5 size-4 shrink-0" />
                <span>{{ state.success }}</span>
                <button class="ml-auto" type="button" @click="clearFeedback">
                    <X class="size-4" />
                </button>
            </div>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,420px)_1fr]">
                <form class="company-panel space-y-5 p-5" @submit.prevent="createToken">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-[#101713]">
                                Nuevo token
                            </h2>
                            <p class="text-sm text-[#536158]">
                                El valor secreto se muestra solo al crearlo.
                            </p>
                        </div>
                        <KeyRound class="size-5 text-[#168447]" />
                    </div>

                    <div class="company-field">
                        <Label for="token-user" class="company-label">
                            Usuario administrador
                        </Label>
                        <select
                            id="token-user"
                            v-model="state.form.user_id"
                            class="company-select"
                            required
                        >
                            <option value="" disabled>Seleccionar usuario</option>
                            <option
                                v-for="user in state.users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }} - {{ user.email }}
                            </option>
                        </select>
                        <InputError :message="state.errors.user_id?.[0]" />
                    </div>

                    <div class="company-field">
                        <Label for="token-name" class="company-label">
                            Nombre
                        </Label>
                        <Input
                            id="token-name"
                            v-model="state.form.name"
                            class="company-input"
                            required
                        />
                        <InputError :message="state.errors.name?.[0]" />
                    </div>

                    <div class="company-field">
                        <Label for="token-expiration" class="company-label">
                            Vencimiento opcional
                        </Label>
                        <Input
                            id="token-expiration"
                            v-model="state.form.expires_at"
                            type="datetime-local"
                            class="company-input"
                        />
                        <InputError :message="state.errors.expires_at?.[0]" />
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-sm font-bold text-[#202a24]">
                            Permisos de la API
                        </legend>
                        <label
                            v-for="ability in state.availableAbilities"
                            :key="ability"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#dfe7e2] p-3 hover:bg-[#f5f8f6]"
                        >
                            <input
                                v-model="state.form.abilities"
                                type="checkbox"
                                :value="ability"
                                class="mt-0.5 size-4 accent-[#168447]"
                            />
                            <span>
                                <span class="block text-sm font-semibold text-[#202a24]">
                                    {{ abilityLabels[ability] ?? ability }}
                                </span>
                                <span class="block text-xs text-[#6e7971]">
                                    {{ ability }}
                                </span>
                            </span>
                        </label>
                        <InputError :message="state.errors.abilities?.[0]" />
                    </fieldset>

                    <Button
                        type="submit"
                        class="company-action-primary min-h-11 w-full gap-2"
                        :disabled="state.saving || !state.form.user_id"
                    >
                        <LoaderCircle
                            v-if="state.saving"
                            class="size-4 animate-spin"
                        />
                        <Plus v-else class="size-4" />
                        {{ state.saving ? 'Generando...' : 'Generar token' }}
                    </Button>
                </form>

                <div class="space-y-4">
                    <div
                        v-if="generatedToken"
                        class="company-panel border-[#bde5cb] bg-[#f3fbf6] p-5"
                    >
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-bold text-[#101713]">
                                    Token generado
                                </h2>
                                <p class="text-sm text-[#536158]">
                                    Copialo ahora. Luego no se podra volver a ver.
                                </p>
                            </div>
                            <ShieldCheck class="size-5 text-[#168447]" />
                        </div>
                        <div
                            class="break-all rounded-lg border border-[#cfe7d7] bg-white p-3 font-mono text-sm text-[#1d2b22]"
                        >
                            {{ generatedToken }}
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-3 gap-2 border-[#168447] text-[#116f3b]"
                            @click="copyToken"
                        >
                            <Clipboard class="size-4" />
                            {{ copied ? 'Copiado' : 'Copiar token' }}
                        </Button>
                    </div>

                    <section class="company-panel overflow-hidden">
                        <div
                            class="flex flex-col gap-3 border-b border-[#dfe7e2] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h2 class="text-lg font-bold text-[#101713]">
                                    Tokens registrados
                                </h2>
                                <p class="text-sm text-[#536158]">
                                    {{ activeTokens }} activos de
                                    {{ state.tokens.length }} registrados
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2"
                                :disabled="state.loading"
                                @click="load"
                            >
                                <LoaderCircle
                                    v-if="state.loading"
                                    class="size-4 animate-spin"
                                />
                                Actualizar
                            </Button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[780px] text-sm">
                                <thead class="bg-white text-left text-xs text-[#536158]">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">
                                            Nombre
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            Usuario
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            Permisos
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            Ultimo uso
                                        </th>
                                        <th class="px-4 py-3 font-semibold">
                                            Estado
                                        </th>
                                        <th class="px-4 py-3 text-right font-semibold">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="state.loading">
                                        <td colspan="6" class="px-4 py-10 text-center text-[#647068]">
                                            <LoaderCircle class="mx-auto mb-2 size-6 animate-spin text-[#168447]" />
                                            Cargando tokens...
                                        </td>
                                    </tr>
                                    <tr v-else-if="!state.tokens.length">
                                        <td colspan="6" class="px-4 py-10 text-center text-[#647068]">
                                            No hay tokens registrados.
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="token in state.tokens"
                                        v-else
                                        :key="token.id"
                                        class="border-t border-[#e4ebe6] bg-white"
                                    >
                                        <td class="px-4 py-3 font-semibold text-[#202a24]">
                                            {{ token.name }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-[#202a24]">
                                                {{ token.user?.name ?? '-' }}
                                            </p>
                                            <p class="text-xs text-[#647068]">
                                                {{ token.user?.email ?? '-' }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex max-w-sm flex-wrap gap-1">
                                                <span
                                                    v-for="ability in token.abilities"
                                                    :key="ability"
                                                    class="rounded-md bg-[#eaf7ef] px-2 py-1 text-xs font-semibold text-[#116f3b]"
                                                >
                                                    {{ abilityLabels[ability] ?? ability }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-[#647068]">
                                            {{ formatDate(token.last_used_at) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="[
                                                    'rounded-md px-2 py-1 text-xs font-semibold',
                                                    token.is_usable
                                                        ? 'bg-[#eaf7ef] text-[#116f3b]'
                                                        : 'bg-red-50 text-red-700',
                                                ]"
                                            >
                                                {{ token.is_usable ? 'Activo' : 'Revocado/vencido' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <Button
                                                v-if="token.is_usable"
                                                type="button"
                                                variant="outline"
                                                class="border-red-300 text-red-700 hover:bg-red-50"
                                                :disabled="state.revokingId === Number(token.id)"
                                                @click="revokeToken(token)"
                                            >
                                                {{
                                                    state.revokingId === Number(token.id)
                                                        ? 'Revocando...'
                                                        : 'Revocar'
                                                }}
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </ModulePageLayout>
</template>

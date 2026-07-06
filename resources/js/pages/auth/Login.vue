<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const csrfToken = computed(
    () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
);
const systemName = computed(() => String(page.props.name ?? 'FastFact R23W7'));
const companyName = computed(() =>
    String(
        (page.props.appContext as Record<string, unknown> | undefined)
            ?.empresa ?? 'TechDev servicios R23W7',
    ),
);
</script>

<template>
    <div class="min-h-svh bg-[#EDF3EF] px-4 py-6 sm:px-6 lg:px-8">
        <Head title="Acceso privado" />

        <main
            class="mx-auto grid min-h-[calc(100svh-3rem)] w-full max-w-6xl overflow-hidden rounded-2xl border border-[#D8E5DD] bg-white shadow-[0_24px_70px_rgba(16,23,19,0.12)] lg:min-h-[620px] lg:grid-cols-[minmax(0,1fr)_430px]"
            aria-labelledby="login-heading"
        >
            <section
                class="hidden bg-[linear-gradient(135deg,rgba(16,23,19,0.96),rgba(18,107,59,0.88))] p-10 text-white lg:grid lg:content-between"
                aria-label="Identidad del sistema"
            >
                <div class="inline-flex items-center gap-3 font-black">
                    <span
                        class="grid size-9 place-items-center rounded-lg bg-[#168447] text-sm font-black text-white"
                    >
                        TD
                    </span>
                    <span>{{ companyName }}</span>
                </div>

                <div>
                    <p
                        class="mb-3 text-xs font-black text-[#80E3A5] uppercase"
                    >
                        Sistema
                    </p>
                    <h1
                        class="max-w-xl text-5xl leading-tight font-black tracking-normal"
                    >
                        {{ systemName }}
                    </h1>
                    <p class="mt-3 text-base text-[#D7EADC]">
                        {{ companyName }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="chip in [
                            'Acceso privado',
                            'Usuarios autorizados',
                            'Facturacion agil',
                        ]"
                        :key="chip"
                        class="rounded-full border border-white/20 bg-white/10 px-3 py-2 text-xs font-black text-[#D7EADC]"
                    >
                        {{ chip }}
                    </span>
                </div>
            </section>

            <section
                class="grid place-items-center bg-[#F8FBF9] p-6 sm:p-8 lg:p-10"
            >
                <div
                    class="w-full max-w-[360px] rounded-2xl border border-[#D8E5DD] bg-white p-7 shadow-[0_18px_44px_rgba(16,23,19,0.08)]"
                >
                    <div class="mb-7 inline-flex items-center gap-3 font-black lg:hidden">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-[#168447] text-sm text-white"
                        >
                            TD
                        </span>
                        <span>{{ companyName }}</span>
                    </div>

                    <p class="text-xs font-black text-[#72D99B] uppercase">
                        Acceso privado
                    </p>
                    <h2
                        id="login-heading"
                        class="mt-3 text-3xl leading-tight font-black tracking-normal text-[#101713]"
                    >
                        Iniciar sesion
                    </h2>
                    <p class="mt-2 text-sm text-[#65746A]">
                        Usa tu cuenta asignada.
                    </p>

                    <div
                        v-if="status"
                        class="mt-5 rounded-lg border border-[#CFE3D6] bg-[#EAF7EF] px-3 py-2 text-sm font-medium text-[#126B3B]"
                    >
                        {{ status }}
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password']"
                        v-slot="{ errors, processing }"
                        class="mt-6 grid gap-4"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <div class="grid gap-2">
                            <Label for="email">Correo o usuario</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="usuario@empresa.com"
                                class="h-11 rounded-lg border-[#CDD9D1] focus-visible:ring-[#168447]/20"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">Contrasena</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="Contrasena"
                                class="h-11 rounded-lg border-[#CDD9D1] focus-visible:ring-[#168447]/20"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <Label
                                for="remember"
                                class="flex items-center gap-2 text-xs font-semibold text-[#65746A]"
                            >
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    :tabindex="3"
                                />
                                <span>Recordar equipo</span>
                            </Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-xs font-bold text-[#126B3B]"
                                :tabindex="5"
                            >
                                Recuperar acceso
                            </TextLink>
                        </div>

                        <Button
                            type="submit"
                            class="mt-1 h-11 w-full rounded-lg bg-[#168447] font-black text-white shadow-[0_12px_24px_rgba(22,132,71,0.22)] hover:bg-[#126B3B]"
                            :tabindex="4"
                            :disabled="processing"
                            data-test="login-button"
                        >
                            <Spinner v-if="processing" />
                            Entrar al sistema
                        </Button>

                        <div
                            class="rounded-lg border border-[#CFE3D6] bg-[#EAF7EF] px-3 py-2 text-xs leading-5 text-[#315B3F]"
                        >
                            Solo el administrador puede crear usuarios.
                        </div>
                    </Form>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase
        title="Registro cerrado"
        description="Las cuentas de usuario se crean desde el modulo de Seguridad."
    >
        <Head title="Registro cerrado" />

        <div class="grid gap-5 text-center">
            <div
                class="rounded-lg border border-[#CFE3D6] bg-[#F1F8F4] px-4 py-3 text-sm text-[#315B3F]"
            >
                Este sistema es privado. Solicita tu usuario al administrador
                de TechDev servicios R23W7.
            </div>

            <Button as-child class="w-full">
                <TextLink :href="login()">Volver al inicio de sesion</TextLink>
            </Button>
        </div>
    </AuthBase>
</template>

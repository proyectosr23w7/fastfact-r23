<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Cable, KeyRound, ShieldCheck, Users } from 'lucide-vue-next';

defineProps<{
    active: 'usuarios' | 'roles' | 'permisos' | 'tokens';
}>();

const tabs = [
    {
        id: 'usuarios',
        label: 'Usuarios',
        href: '/seguridad/usuarios',
        icon: Users,
    },
    {
        id: 'roles',
        label: 'Roles',
        href: '/seguridad/roles',
        icon: ShieldCheck,
    },
    {
        id: 'permisos',
        label: 'Permisos',
        href: '/seguridad/permisos',
        icon: KeyRound,
    },
    {
        id: 'tokens',
        label: 'Tokens API',
        href: '/seguridad/tokens-integracion',
        icon: Cable,
    },
] as const;
</script>

<template>
    <nav class="flex min-w-max gap-1" aria-label="Secciones de seguridad">
        <Link
            v-for="tab in tabs"
            :key="tab.id"
            :href="tab.href"
            :aria-current="active === tab.id ? 'page' : undefined"
            :class="[
                'flex min-h-11 items-center gap-2 border-b-2 px-4 text-sm font-semibold transition focus-visible:rounded-md focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none',
                active === tab.id
                    ? 'border-[#168447] text-[#116f3b]'
                    : 'border-transparent text-[#5f6b63] hover:border-[#b8d8c4] hover:text-[#101713]',
            ]"
        >
            <component :is="tab.icon" class="size-4" aria-hidden="true" />
            {{ tab.label }}
        </Link>
    </nav>
</template>

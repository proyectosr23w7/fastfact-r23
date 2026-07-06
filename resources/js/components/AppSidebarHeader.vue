<script setup lang="ts">
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useInitials } from '@/composables/useInitials';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Building2, ChevronDown, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const { getInitials } = useInitials();
const user = computed(() => page.props.auth.user);
const role = computed(() => page.props.auth.roles?.[0]?.nombre ?? 'Usuario');
const context = computed(
    () =>
        page.props.appContext ?? {
            empresa: 'Empresa sin nombre',
            sucursal: 'Sucursal no asignada',
            puntoVenta: null,
        },
);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex min-h-14 shrink-0 items-center gap-2 border-b border-[#dfe7e2] bg-white/95 px-3 backdrop-blur sm:min-h-16 sm:gap-3 sm:px-4 lg:px-6 dark:border-white/10 dark:bg-[#101713]/95"
    >
        <SidebarTrigger
            class="shrink-0 text-[#19221D] hover:bg-[#EAF7EF] dark:text-white"
        />

        <div class="ml-auto flex min-w-0 items-center gap-1.5 sm:gap-3">
            <div
                class="hidden min-w-0 items-center gap-2 rounded-lg border border-[#dfe7e2] bg-white px-3 py-2 text-sm shadow-sm md:flex dark:border-white/10 dark:bg-white/5"
            >
                <Building2 class="size-4 shrink-0 text-[#168447]" />
                <span class="max-w-52 truncate font-medium">{{
                    context.empresa
                }}</span>
            </div>

            <div
                class="hidden min-w-0 items-center gap-2 rounded-lg border border-[#dfe7e2] bg-white px-3 py-2 text-sm shadow-sm sm:flex dark:border-white/10 dark:bg-white/5"
            >
                <MapPin class="size-4 shrink-0 text-[#168447]" />
                <span class="max-w-40 truncate">{{ context.sucursal }}</span>
                <span
                    v-if="context.puntoVenta"
                    class="hidden text-muted-foreground xl:inline"
                    >· {{ context.puntoVenta }}</span
                >
            </div>

            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-[#EAF7EF] focus-visible:ring-2 focus-visible:ring-[#168447] focus-visible:outline-none dark:hover:bg-white/10"
                        aria-label="Abrir menú de usuario"
                    >
                        <Avatar
                            class="size-9 border border-[#dfe7e2] dark:border-white/10"
                        >
                            <AvatarFallback
                                class="bg-[#EAF7EF] text-sm font-bold text-[#168447] dark:bg-[#168447]/25 dark:text-[#71e5a0]"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="hidden max-w-32 leading-tight lg:block">
                            <div class="truncate text-sm font-semibold">
                                {{ user.name }}
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ role }}
                            </div>
                        </div>
                        <ChevronDown
                            class="hidden size-4 text-muted-foreground lg:block"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-64" align="end" :side-offset="8">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>

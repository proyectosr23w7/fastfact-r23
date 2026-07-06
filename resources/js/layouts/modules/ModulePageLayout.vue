<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    title: string;
    breadcrumbs: BreadcrumbItem[];
    description?: string;
    compact?: boolean;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            :class="[
                'company-mesh flex h-full flex-1 flex-col gap-5 p-4 md:p-6',
                compact ? '' : 'rounded-[1.75rem]',
            ]"
        >
            <header
                :class="
                    compact
                        ? 'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'
                        : 'company-hero overflow-hidden rounded-[1.5rem] border border-black/10 px-6 py-7 text-white shadow-[0_26px_55px_-30px_rgba(5,24,17,0.75)]'
                "
            >
                <div :class="compact ? 'space-y-1' : 'max-w-3xl space-y-2'">
                    <p
                        v-if="!compact"
                        class="text-xs font-semibold tracking-[0.28em] text-emerald-100/80 uppercase"
                    >
                        Sistema empresarial
                    </p>
                    <h1
                        :class="
                            compact
                                ? 'text-2xl font-bold tracking-tight text-[#101713] md:text-3xl'
                                : 'text-2xl font-semibold tracking-tight md:text-3xl'
                        "
                    >
                        {{ title }}
                    </h1>
                    <p
                        :class="
                            compact
                                ? 'max-w-2xl text-sm text-[#536158]'
                                : 'max-w-2xl text-sm text-emerald-50/80'
                        "
                    >
                        {{
                            description ??
                            'Formulario y vistas base organizadas por modulos para un crecimiento ordenado.'
                        }}
                    </p>
                </div>
                <slot name="actions" />
            </header>

            <section
                :class="
                    compact ? '' : 'company-panel overflow-hidden p-4 md:p-6'
                "
            >
                <slot />
            </section>
        </div>
    </AppLayout>
</template>

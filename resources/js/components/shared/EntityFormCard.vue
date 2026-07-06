<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="company-panel overflow-hidden">
        <div class="company-hero px-5 py-5 text-white">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p class="mt-1 text-sm text-emerald-50/80">{{ description }}</p>
        </div>

        <div class="space-y-6 p-5 md:p-6">
            <slot />
        </div>
    </div>
</template>

<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="#20A85B"
            d="M24 3 44 14.5v7L27.5 12v33L21 41.3V12L4 21.7v-7.2L24 3Z"
        />
        <path
            fill="#168447"
            d="m8 23.8 7-4v17.8l-7-4V23.8Zm33-4 3 1.7v12.1l-7 4V22.1l4-2.3Z"
        />
        <path
            fill="#35D978"
            d="m24 3 20 11.5-6.2 3.6L24 10.2 10.2 18.1 4 14.5 24 3Z"
        />
    </svg>
</template>

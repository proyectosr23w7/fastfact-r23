import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

const manualChunks = (id: string): string | undefined => {
    const normalizedId = id.replace(/\\/g, '/');

    if (normalizedId.includes('/node_modules/lucide-vue-next/')) {
        return 'icons';
    }

    if (normalizedId.includes('/node_modules/reka-ui/') || normalizedId.includes('/node_modules/vue-input-otp/')) {
        return 'ui-vendor';
    }

    if (normalizedId.includes('/node_modules/@tanstack/vue-table/')) {
        return 'tables';
    }

    if (
        normalizedId.includes('/node_modules/vue/')
        || normalizedId.includes('/node_modules/@vue/')
        || normalizedId.includes('/node_modules/@inertiajs/')
        || normalizedId.includes('/node_modules/@vueuse/')
    ) {
        return 'framework';
    }

    if (normalizedId.includes('/resources/js/components/ui/')) {
        return 'ui-kit';
    }

    if (
        normalizedId.includes('/resources/js/components/AppSidebar')
        || normalizedId.includes('/resources/js/components/AppShell')
        || normalizedId.includes('/resources/js/components/AppContent')
        || normalizedId.includes('/resources/js/components/AppSidebarHeader')
        || normalizedId.includes('/resources/js/components/NavMain')
        || normalizedId.includes('/resources/js/components/NavFooter')
        || normalizedId.includes('/resources/js/components/NavUser')
        || normalizedId.includes('/resources/js/layouts/app/')
    ) {
        return 'app-shell';
    }

    if (normalizedId.includes('/node_modules/')) {
        return 'vendor';
    }

    return undefined;
};

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks,
            },
        },
    },
});

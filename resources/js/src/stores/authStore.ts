import type { AppPageProps } from '@/types';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuthStore() {
    const page = usePage<AppPageProps>();

    const user = computed(() => page.props.auth.user);
    const roles = computed(() => page.props.auth.roles ?? []);
    const permissions = computed(() => page.props.auth.permissions ?? []);

    return {
        user,
        roles,
        permissions,
    };
}

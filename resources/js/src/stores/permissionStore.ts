import { computed } from 'vue';
import { useAuthStore } from '@/src/stores/authStore';

export function usePermissionStore() {
    const authStore = useAuthStore();

    const permissions = computed(() => authStore.permissions.value);

    const hasPermission = (permission: string) =>
        permissions.value.includes('*') || permissions.value.includes(permission);

    const hasAnyPermission = (required: string[]) =>
        permissions.value.includes('*') || required.some((permission) => permissions.value.includes(permission));

    const hasModuleAccess = (module: string) =>
        permissions.value.includes('*')
        || permissions.value.some((permission) => permission.startsWith(`${module}.`))
        || permissions.value.includes(`${module}.access`);

    return {
        permissions,
        hasPermission,
        hasAnyPermission,
        hasModuleAccess,
    };
}

import { usePermissionStore } from '@/stores/permission.store';

export function usePermissions() {
    const permissionStore = usePermissionStore();

    return {
        can: permissionStore.hasPermission,
        permissions: permissionStore.all,
    };
}

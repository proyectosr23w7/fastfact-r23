import { computed, reactive, readonly } from 'vue';

const state = reactive({
    permissions: [] as string[],
});

function setPermissions(permissions: string[]) {
    state.permissions = permissions;
}

function hasPermission(permission: string) {
    return state.permissions.includes(permission);
}

export function usePermissionStore() {
    return {
        state: readonly(state),
        all: computed(() => state.permissions),
        hasPermission,
        setPermissions,
    };
}

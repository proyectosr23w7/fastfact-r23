import { computed, reactive, readonly } from 'vue';
import type { User } from '@/types';

const state = reactive({
    user: null as User | null,
    loaded: false,
});

function setUser(user: User | null) {
    state.user = user;
    state.loaded = true;
}

export function useAuthStore() {
    return {
        state: readonly(state),
        isAuthenticated: computed(() => Boolean(state.user)),
        setUser,
    };
}

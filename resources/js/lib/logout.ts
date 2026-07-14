import { login, logout } from '@/routes';

const redirectToLogin = () => {
    window.location.assign(login().url);
};

export const performLogout = async (beforeLogout?: () => void) => {
    beforeLogout?.();

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    try {
        const response = await fetch(logout().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: new URLSearchParams({ _token: csrfToken }),
        });

        if (response.ok || [401, 419].includes(response.status)) {
            redirectToLogin();
            return;
        }
    } catch {
        redirectToLogin();
        return;
    }

    redirectToLogin();
};

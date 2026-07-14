import { login, logout } from '@/routes';

let logoutInProgress = false;

const redirectToLogin = () => {
    const loginUrl = new URL(login().url, window.location.origin);
    loginUrl.searchParams.set('logged_out', Date.now().toString());

    window.location.replace(loginUrl.toString());
};

export const performLogout = async (beforeLogout?: () => void) => {
    if (logoutInProgress) {
        return;
    }

    logoutInProgress = true;
    beforeLogout?.();

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    try {
        const response = await fetch(logout().url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'Cache-Control': 'no-cache',
                'Content-Type':
                    'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: new URLSearchParams(csrfToken ? { _token: csrfToken } : {}),
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

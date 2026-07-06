type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface ApiResponse<T> {
    success?: boolean;
    data: T;
    meta?: Record<string, unknown>;
    message?: string;
}

class ApiError extends Error {
    status: number;
    errors: Record<string, string[]>;

    constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

function getCookieValue(name: string): string | null {
    const escapedName = name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1');
    const match = document.cookie.match(new RegExp(`(?:^|; )${escapedName}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

function appendFormData(formData: FormData, key: string, value: unknown): void {
    if (value === undefined || value === null || value === '') {
        return;
    }

    if (value instanceof File) {
        formData.append(key, value);
        return;
    }

    if (Array.isArray(value)) {
        value.forEach((item, index) => appendFormData(formData, `${key}[${index}]`, item));
        return;
    }

    if (typeof value === 'object') {
        Object.entries(value as Record<string, unknown>).forEach(([nestedKey, nestedValue]) => {
            appendFormData(formData, `${key}[${nestedKey}]`, nestedValue);
        });
        return;
    }

    if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0');
        return;
    }

    formData.append(key, String(value));
}

function buildFormData(payload: Record<string, unknown>): FormData {
    const formData = new FormData();

    Object.entries(payload).forEach(([key, value]) => appendFormData(formData, key, value));

    return formData;
}

async function request<T>(method: HttpMethod, url: string, payload?: Record<string, unknown>): Promise<ApiResponse<T>> {
    const csrfToken =
        getCookieValue('XSRF-TOKEN') ??
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken
                ? {
                      'X-CSRF-TOKEN': csrfToken,
                      'X-XSRF-TOKEN': csrfToken,
                  }
                : {}),
        },
        body: payload ? JSON.stringify(payload) : undefined,
    });

    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(response.status, json.message ?? 'Error en la solicitud.', json.errors ?? {});
    }

    return json as ApiResponse<T>;
}

async function requestForm<T>(method: HttpMethod, url: string, payload: Record<string, unknown>): Promise<ApiResponse<T>> {
    const csrfToken =
        getCookieValue('XSRF-TOKEN') ??
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const formData = buildFormData(payload);
    const actualMethod = method === 'POST' ? method : 'POST';

    if (method !== 'POST') {
        formData.append('_method', method);
    }

    const response = await fetch(url, {
        method: actualMethod,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken
                ? {
                      'X-CSRF-TOKEN': csrfToken,
                      'X-XSRF-TOKEN': csrfToken,
                  }
                : {}),
        },
        body: formData,
    });

    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(response.status, json.message ?? 'Error en la solicitud.', json.errors ?? {});
    }

    return json as ApiResponse<T>;
}

export { ApiError };

export const apiClient = {
    get<T>(url: string) {
        return request<T>('GET', url);
    },
    post<T>(url: string, payload: Record<string, unknown>) {
        return request<T>('POST', url, payload);
    },
    postForm<T>(url: string, payload: Record<string, unknown>) {
        return requestForm<T>('POST', url, payload);
    },
    put<T>(url: string, payload: Record<string, unknown>) {
        return request<T>('PUT', url, payload);
    },
    putForm<T>(url: string, payload: Record<string, unknown>) {
        return requestForm<T>('PUT', url, payload);
    },
    patch<T>(url: string, payload: Record<string, unknown>) {
        return request<T>('PATCH', url, payload);
    },
    delete<T>(url: string) {
        return request<T>('DELETE', url);
    },
};

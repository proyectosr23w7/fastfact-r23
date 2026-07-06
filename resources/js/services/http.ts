const defaultHeaders = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

async function parseResponse<T>(response: Response): Promise<T> {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    if (response.status === 204) {
        return null as T;
    }

    return response.json() as Promise<T>;
}

export const http = {
    async get<T>(url: string): Promise<T> {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: defaultHeaders,
        });

        return parseResponse<T>(response);
    },
};

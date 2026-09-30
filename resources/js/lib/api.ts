/** Error returned by the JSON endpoints, with Laravel validation messages when there are any. */
export class ApiError extends Error {
    constructor(
        message: string,
        readonly status: number,
        readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
    }

    /** First validation message for a field, or the general message. */
    first(field?: string): string {
        return (field ? this.errors[field]?.[0] : undefined) ?? this.message;
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * JSON request against the app. Sends the XSRF cookie Laravel sets, so it works
 * with the regular CSRF protection.
 */
export async function api<T = void>(
    method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE',
    url: string,
    body?: Record<string, unknown>,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(
            payload?.message ?? 'No se pudo completar la acción.',
            response.status,
            payload?.errors ?? {},
        );
    }

    return payload as T;
}

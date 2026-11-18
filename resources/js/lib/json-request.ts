// Reads the CSRF token that Laravel keeps in the XSRF-TOKEN cookie.
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

// Sends JSON to the server and ignores the answer body. Throws when the server says no.
export async function sendJson(
    url: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body: unknown,
): Promise<void> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`The server answered ${response.status}.`);
    }
}

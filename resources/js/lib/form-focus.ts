import type { Errors } from '@inertiajs/core';

// Moves focus to the first field with a server error, so screen readers read it.
// Waits a frame, so the error messages are on the page and linked to their fields.
export function focusFirstError(errors: Errors): void {
    const fields = Object.keys(errors);

    if (fields.length === 0) {
        return;
    }

    requestAnimationFrame(() => {
        const named = fields
            .map((field) => document.getElementById(field))
            .find((element) => element !== null);

        (
            named ??
            document.querySelector<HTMLElement>('[aria-invalid="true"]')
        )?.focus();
    });
}

import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

// Returns a check for the capabilities of the current user. The server checks again on every request.
export function useCan(): (key: string) => boolean {
    const can = usePage().props.can as Record<string, boolean> | undefined;

    return useCallback((key: string) => can?.[key] === true, [can]);
}

import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    DEFAULT_TIMEZONE,
    formatDate,
    formatDay,
    fromLocalInput,
    toLocalInput,
} from '@/lib/dates';

// Date helpers bound to the site time zone shared by the server.
export function useSiteDates() {
    const timeZone = usePage().props.siteTimezone || DEFAULT_TIMEZONE;

    return useMemo(
        () => ({
            timeZone,
            formatDate: (iso: string | null) => formatDate(iso, timeZone),
            formatDay: (iso: string | null) => formatDay(iso, timeZone),
            toLocalInput: (iso: string | null) => toLocalInput(iso, timeZone),
            fromLocalInput,
        }),
        [timeZone],
    );
}

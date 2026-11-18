import { useSiteDates } from '@/hooks/use-site-dates';

type Props = {
    iso: string | null;
    dayOnly?: boolean;
    className?: string;
};

// A <time> tag that shows a UTC date in the site time zone.
export function SiteDate({ iso, dayOnly = false, className }: Props) {
    const { formatDate, formatDay } = useSiteDates();

    if (!iso) {
        return null;
    }

    return (
        <time dateTime={iso} className={className}>
            {dayOnly ? formatDay(iso) : formatDate(iso)}
        </time>
    );
}

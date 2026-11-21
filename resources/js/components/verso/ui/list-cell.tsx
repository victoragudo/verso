import { formatNumber, msg, t } from '@/lib/i18n';
import { plainText } from '@/lib/utils';
import { SiteDate } from './site-date';
import { StatusBadge } from './status-badge';
import type { StatusTone } from './status-badge';

// How the server describes an optional column.
export type ServerListColumn = {
    key: string;
    label: string;
    kind:
        | 'text'
        | 'number'
        | 'date'
        | 'image'
        | 'tags'
        | 'seo'
        | 'money'
        | 'status';
    defaultFrom: 'always' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | 'never';
};

const SEO_LABELS: Record<string, { label: string; tone: StatusTone }> = {
    good: { label: msg('Good'), tone: 'success' },
    ok: { label: msg('Needs work'), tone: 'warning' },
    poor: { label: msg('Poor'), tone: 'danger' },
    complete: { label: msg('Complete'), tone: 'success' },
    partial: { label: msg('Partial'), tone: 'warning' },
    missing: { label: msg('Missing'), tone: 'neutral' },
};

// Shown for an empty value, with words for screen readers.
function Empty() {
    return (
        <span className="text-muted-foreground">
            <span aria-hidden="true">—</span>
            <span className="sr-only">{t('None')}</span>
        </span>
    );
}

// Classes for the cells of a column of this kind: numbers line up on the right.
export function listCellClass(kind: ServerListColumn['kind']): string {
    switch (kind) {
        case 'number':
        case 'money':
            return 'text-end whitespace-nowrap';
        case 'date':
            return 'whitespace-nowrap text-muted-foreground';
        case 'image':
            return 'w-14';
        case 'tags':
            return 'max-w-[16rem]';
        default:
            return 'whitespace-nowrap';
    }
}

// One value of an optional column, shown the way its kind needs.
export function ListCell({
    kind,
    value,
}: {
    kind: ServerListColumn['kind'];
    value: unknown;
}) {
    if (value === null || value === undefined || value === '') {
        return <Empty />;
    }

    switch (kind) {
        case 'image': {
            const image = value as { src: string; alt: string };

            return (
                <img
                    src={image.src}
                    alt={image.alt}
                    width={40}
                    height={40}
                    loading="lazy"
                    decoding="async"
                    className="size-10 rounded-md border object-cover"
                />
            );
        }
        case 'tags': {
            const names = Array.isArray(value) ? (value as string[]) : [];

            if (names.length === 0) {
                return <Empty />;
            }

            // The full list stays in the text, so nothing is lost when it is cut.
            return (
                <span className="line-clamp-2 [overflow-wrap:anywhere]">
                    {names.join(', ')}
                </span>
            );
        }
        case 'number':
            return (
                <span className="tabular-nums">
                    {formatNumber(Number(value))}
                </span>
            );
        case 'date':
            return <SiteDate iso={plainText(value)} />;
        case 'seo': {
            const seo = SEO_LABELS[plainText(value)] ?? SEO_LABELS.missing;

            return <StatusBadge tone={seo.tone}>{t(seo.label)}</StatusBadge>;
        }
        default:
            return <span className="tabular-nums">{plainText(value)}</span>;
    }
}

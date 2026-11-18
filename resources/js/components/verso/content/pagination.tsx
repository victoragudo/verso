import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatNumber, t } from '@/lib/i18n';
import type { Paginated } from '@/types';

// Previous and next links under a paginated list.
export function Pagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    const previous = page.links[0]?.url ?? null;
    const next = page.links[page.links.length - 1]?.url ?? null;

    return (
        <nav
            className="flex items-center justify-between gap-4 text-sm text-muted-foreground"
            aria-label={t('Pagination')}
        >
            <span>
                {t('{from}-{to} of {total}', {
                    from: formatNumber(page.from ?? 0),
                    to: formatNumber(page.to ?? 0),
                    total: formatNumber(page.total),
                })}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!previous}
                    asChild={!!previous}
                >
                    {previous ? (
                        <Link href={previous} preserveScroll>
                            {t('Previous')}
                        </Link>
                    ) : (
                        <span>{t('Previous')}</span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!next}
                    asChild={!!next}
                >
                    {next ? (
                        <Link href={next} preserveScroll>
                            {t('Next')}
                        </Link>
                    ) : (
                        <span>{t('Next')}</span>
                    )}
                </Button>
            </div>
        </nav>
    );
}

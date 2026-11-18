import { useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { breadcrumbUrl, truncate } from './seo-text';

// Characters Google shows on each screen, roughly. Real cuts depend on pixel width.
const LIMITS = {
    desktop: { title: 60, description: 158 },
    mobile: { title: 70, description: 120 },
} as const;

type Device = keyof typeof LIMITS;

type Props = {
    title: string;
    description: string;
    url: string;
    siteName: string;
};

// How the page may look in Google results, on a computer or a phone.
export function SnippetPreview({ title, description, url, siteName }: Props) {
    const [device, setDevice] = useState<Device>('desktop');
    const limits = LIMITS[device];

    return (
        <div className="grid min-w-0 gap-2">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium text-muted-foreground">
                    {t('Google preview')}
                </p>
                <ToggleGroup
                    type="single"
                    size="sm"
                    variant="outline"
                    value={device}
                    onValueChange={(value) =>
                        value && setDevice(value as Device)
                    }
                    aria-label={t('Preview size')}
                >
                    <ToggleGroupItem value="desktop" className="px-2 text-xs">
                        {t('Computer')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="mobile" className="px-2 text-xs">
                        {t('Phone')}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <figure
                className={cn(
                    'min-w-0 rounded-md border bg-white p-3 text-start font-[arial,sans-serif] dark:bg-zinc-950',
                    device === 'mobile' &&
                        'mx-auto w-full max-w-[22rem] rounded-xl shadow-xs',
                )}
                aria-label={
                    device === 'mobile'
                        ? t('Search result preview on a phone')
                        : t('Search result preview on a computer')
                }
                data-device={device}
            >
                <div className="flex min-w-0 items-center gap-2">
                    <span
                        className="grid size-7 shrink-0 place-items-center rounded-full border bg-zinc-50 text-[0.65rem] font-semibold text-zinc-600 dark:bg-zinc-900"
                        aria-hidden="true"
                    >
                        {siteName.slice(0, 1).toUpperCase() || 'V'}
                    </span>
                    <div className="min-w-0 leading-tight">
                        <p className="truncate text-sm text-zinc-800 dark:text-zinc-200">
                            {siteName}
                        </p>
                        <p className="truncate text-xs text-zinc-600 dark:text-zinc-400">
                            {breadcrumbUrl(url)}
                        </p>
                    </div>
                </div>
                <p
                    className={cn(
                        'mt-2 leading-snug break-words text-[#1a0dab] dark:text-[#8ab4f8]',
                        device === 'mobile' ? 'text-base' : 'text-lg',
                    )}
                    data-testid="snippet-title"
                >
                    {truncate(title || t('Untitled'), limits.title)}
                </p>
                <p
                    className="mt-1 text-sm leading-snug break-words text-zinc-600 dark:text-zinc-400"
                    data-testid="snippet-description"
                >
                    {description
                        ? truncate(description, limits.description)
                        : t(
                              'No description yet. Search engines will pick some text from the page.',
                          )}
                </p>
            </figure>
        </div>
    );
}

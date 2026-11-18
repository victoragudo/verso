import { Eraser, ImageIcon, Search } from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { MediaPicker } from '@/components/verso/media/media-picker';
import { t } from '@/lib/i18n';
import { matchesSearch, normalizeSearchText } from '@/lib/option-search';
import { cn } from '@/lib/utils';

// Inputs for the richer field types of plugin and builder blocks: a picture, an icon and a color.

export type MediaValue = {
    id: number;
    url: string;
    alt?: string;
    width?: number | null;
    height?: number | null;
};

export function isMediaValue(value: unknown): value is MediaValue {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as MediaValue).id === 'number' &&
        typeof (value as MediaValue).url === 'string'
    );
}

// A picture from the media library, stored with its address and alt text.
export function MediaField({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: string;
    value: unknown;
    onChange: (value: MediaValue | null) => void;
}) {
    const [picking, setPicking] = useState(false);
    const media = isMediaValue(value) ? value : null;

    return (
        <div className="grid gap-1.5">
            {media && (
                <img
                    src={media.url}
                    alt={media.alt ?? ''}
                    className="aspect-video w-full max-w-64 rounded border object-cover"
                />
            )}
            <div className="flex flex-wrap gap-1.5">
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPicking(true)}
                >
                    <ImageIcon />
                    {media
                        ? t('Replace {field}', { field: label })
                        : t('Choose {field}', { field: label })}
                </Button>
                {media && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => onChange(null)}
                    >
                        <Eraser />
                        {t('Remove')}
                    </Button>
                )}
            </div>
            <MediaPicker
                open={picking}
                onOpenChange={setPicking}
                accept="image"
                onSelect={(item) =>
                    onChange({
                        id: item.id,
                        url: item.url,
                        alt: item.alt ?? '',
                        width: item.width,
                        height: item.height,
                    })
                }
            />
        </div>
    );
}

// The bundled icon set, loaded once from the public JSON file.
let iconSet: Promise<Record<string, string>> | null = null;

export function loadIcons(url: string): Promise<Record<string, string>> {
    iconSet ??= fetch(url)
        .then((response) => response.json())
        .then((data: { icons?: Record<string, string> }) => data.icons ?? {})
        .catch(() => {
            iconSet = null;

            return {};
        });

    return iconSet;
}

// Where the editor finds the icon set. Set from the editor manifest.
let iconsUrl = '';

export function setIconsUrl(url: string | null | undefined): void {
    iconsUrl = url ?? '';
}

function IconSvg({ markup }: { markup: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className="size-5"
            // The markup comes from the bundled icon file of the site, never from people.
            dangerouslySetInnerHTML={{ __html: markup }}
        />
    );
}

// A searchable grid of icons. The chosen icon is pressed.
export function IconField({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string | null) => void;
}) {
    const [icons, setIcons] = useState<Record<string, string>>({});
    const [query, setQuery] = useState('');
    const searchId = useId();

    useEffect(() => {
        if (iconsUrl !== '') {
            void loadIcons(iconsUrl).then(setIcons);
        }
    }, []);

    const shown = useMemo(() => {
        const needle = normalizeSearchText(query);
        const names = Object.keys(icons);

        return needle === ''
            ? names
            : names.filter((name) =>
                  matchesSearch(
                      normalizeSearchText(name.replace(/-/g, ' ')),
                      needle,
                  ),
              );
    }, [icons, query]);

    return (
        <div className="grid gap-1.5" id={id}>
            <div className="relative">
                <Search className="pointer-events-none absolute start-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id={searchId}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t('Search icons')}
                    aria-label={t('Search icons for {field}', { field: label })}
                    className="h-8 ps-8"
                />
            </div>
            <div
                role="group"
                aria-label={label}
                className="grid max-h-40 grid-cols-[repeat(auto-fill,minmax(2.5rem,1fr))] gap-1 overflow-auto rounded border p-1"
            >
                {shown.map((name) => (
                    <button
                        key={name}
                        type="button"
                        title={name.replace(/-/g, ' ')}
                        aria-label={name.replace(/-/g, ' ')}
                        aria-pressed={value === name}
                        onClick={() => onChange(name)}
                        className={cn(
                            'flex size-10 items-center justify-center rounded hover:bg-zinc-100 dark:hover:bg-zinc-800',
                            value === name &&
                                'bg-zinc-900 text-white hover:bg-zinc-900 dark:bg-zinc-100 dark:text-zinc-900',
                        )}
                    >
                        <IconSvg markup={icons[name]} />
                    </button>
                ))}
                {shown.length === 0 && (
                    <p className="col-span-full p-2 text-xs text-muted-foreground">
                        {Object.keys(icons).length === 0
                            ? t('Loading icons…')
                            : t('No icon matches.')}
                    </p>
                )}
            </div>
        </div>
    );
}

// A color: the picker of the browser or a theme color token like var:color.accent.
export function ColorField({
    id,
    value,
    onChange,
}: {
    id: string;
    value: string;
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="flex items-center gap-1.5">
            <input
                type="color"
                aria-hidden="true"
                tabIndex={-1}
                value={/^#[0-9a-f]{6}$/i.test(value) ? value : '#000000'}
                onChange={(event) => onChange(event.target.value)}
                className="h-9 w-10 cursor-pointer rounded border bg-transparent p-0.5"
            />
            <Input
                id={id}
                value={value}
                placeholder="#1f6f5c"
                onChange={(event) =>
                    onChange(
                        event.target.value.trim() === ''
                            ? null
                            : event.target.value.trim(),
                    )
                }
                className="font-mono"
            />
        </div>
    );
}

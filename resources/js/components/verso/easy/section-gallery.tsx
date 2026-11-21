import { Search } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { SectionIllustration } from '@/components/verso/easy/section-illustration';
import { filterSections, sectionCategories } from '@/lib/easy/sections';
import type { SectionInfo } from '@/lib/easy/sections';
import { t } from '@/lib/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sections: SectionInfo[];
    // Key of the section being added, while the server builds it.
    busyKey: string | null;
    error: string | null;
    onPick: (key: string) => void;
    // Where focus goes on close. Call event.preventDefault() to move it yourself.
    onCloseAutoFocus?: (event: Event) => void;
};

const ALL = 'all';

// "Add section": ready sections with a drawing, grouped by category and searchable.
export function SectionGallery({
    open,
    onOpenChange,
    sections,
    busyKey,
    error,
    onPick,
    onCloseAutoFocus,
}: Props) {
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState(ALL);
    const searchId = useId();
    const categories = useMemo(() => sectionCategories(sections), [sections]);
    const shown = useMemo(
        () =>
            filterSections(sections, query, category === ALL ? null : category),
        [sections, query, category],
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90dvh] w-[calc(100vw-2rem)] flex-col gap-4 open:flex sm:max-w-[min(90vw,72rem)]"
                onCloseAutoFocus={onCloseAutoFocus}
            >
                <DialogHeader>
                    <DialogTitle>{t('Add a section')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Pick a ready section. It takes the colors and fonts of your site, and you can change every text and picture.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap items-center gap-3">
                    <label htmlFor={searchId} className="sr-only">
                        {t('Search sections')}
                    </label>
                    <div className="relative min-w-[min(100%,16rem)] flex-1">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id={searchId}
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={t('Search, like prices or map')}
                            className="h-11 ps-9"
                            autoComplete="off"
                        />
                    </div>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={category}
                        onValueChange={(value) => setCategory(value || ALL)}
                        aria-label={t('Kind of section')}
                        className="flex-wrap"
                    >
                        <ToggleGroupItem value={ALL} className="h-11 px-3">
                            {t('All')}
                        </ToggleGroupItem>
                        {categories.map((item) => (
                            <ToggleGroupItem
                                key={item.value}
                                value={item.value}
                                className="h-11 px-3"
                            >
                                {item.label}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>

                {error && (
                    <p
                        role="alert"
                        className="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}

                <p role="status" className="sr-only">
                    {t('{count, plural, one {# section} other {# sections}}', {
                        count: shown.length,
                    })}
                </p>

                <div className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1 pb-1">
                    {shown.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">
                            {t(
                                'No section matches “{query}”. Try another word.',
                                {
                                    query,
                                },
                            )}
                        </p>
                    ) : (
                        <ul className="auto-fit-grid gap-3 [--grid-min:13rem]">
                            {shown.map((section) => (
                                <li key={section.key}>
                                    <button
                                        type="button"
                                        onClick={() => onPick(section.key)}
                                        disabled={busyKey !== null}
                                        aria-busy={busyKey === section.key}
                                        data-test={`section-${section.key}`}
                                        className="flex h-full w-full flex-col gap-3 rounded-lg border bg-card p-3 text-start shadow-xs transition-colors hover:border-zinc-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-70 dark:hover:border-zinc-600"
                                    >
                                        <span className="block overflow-hidden rounded-md border bg-muted/40 p-2">
                                            <SectionIllustration
                                                sectionKey={section.key}
                                            />
                                        </span>
                                        <span className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                            {section.label}
                                            {busyKey === section.key && (
                                                <Spinner aria-hidden="true" />
                                            )}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {section.description}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

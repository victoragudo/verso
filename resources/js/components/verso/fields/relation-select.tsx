import { X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { SearchableSelect } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { itemsById, searchItems } from './lookup';
import type { LookupFilters, LookupItem, LookupKind } from './lookup';

const SEARCH_DELAY_MS = 250;

type Props = {
    id?: string;
    kind: LookupKind;
    filters: LookupFilters;
    multiple: boolean;
    value: number | number[] | null;
    onChange: (value: number | number[] | null) => void;
    maxItems?: number | null;
    invalid?: boolean;
    label: string;
};

function idsOf(value: number | number[] | null): number[] {
    if (Array.isArray(value)) {
        return value;
    }

    return value ? [value] : [];
}

// Picks one or many entries, terms or users, searching on the server as the user types.
export function RelationSelect({
    id,
    kind,
    filters,
    multiple,
    value,
    onChange,
    maxItems,
    invalid,
    label,
}: Props) {
    const ids = idsOf(value);
    const [known, setKnown] = useState<Map<string, LookupItem>>(new Map());
    const [found, setFound] = useState<LookupItem[]>([]);
    const [query, setQuery] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const filtersRef = useRef(filters);
    const idsKey = ids.join(',');

    const remember = (items: LookupItem[]) =>
        setKnown((current) => {
            const next = new Map(current);
            items.forEach((item) => next.set(item.value, item));

            return next;
        });

    useEffect(() => {
        const missing = idsKey
            .split(',')
            .filter((item) => item !== '' && !known.has(item))
            .map(Number);

        if (missing.length === 0) {
            return;
        }

        itemsById(kind, missing, filtersRef.current)
            .then(remember)
            .catch(() => setError(t('Could not load the picked items.')));
        // Only new ids need a lookup; known labels stay in the map.
    }, [kind, idsKey]);

    useEffect(() => {
        if (query === null) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            searchItems(kind, query, filtersRef.current, controller.signal)
                .then((items) => {
                    setError(null);
                    setFound(items);
                    remember(items);
                })
                .catch(() => {
                    if (!controller.signal.aborted) {
                        setError(t('Could not search this list.'));
                    }
                });
        }, SEARCH_DELAY_MS);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [kind, query]);

    const options = useMemo(
        () =>
            found
                .filter(
                    (item) => !multiple || !ids.includes(Number(item.value)),
                )
                .map((item) => ({
                    value: item.value,
                    label: item.label,
                    description: item.hint,
                })),
        // ids changes with idsKey.
        [found, multiple, idsKey],
    );

    const full = multiple && maxItems != null && ids.length >= maxItems;
    const selectedSingle =
        !multiple && ids.length === 1 ? known.get(String(ids[0])) : undefined;
    const singleOptions = selectedSingle
        ? [
              { value: selectedSingle.value, label: selectedSingle.label },
              ...options.filter(
                  (option) => option.value !== selectedSingle.value,
              ),
          ]
        : options;

    return (
        <div className="grid gap-2">
            {multiple && ids.length > 0 && (
                <ul
                    className="grid gap-1.5"
                    aria-label={t('Picked for {label}', { label })}
                >
                    {ids.map((itemId) => (
                        <li
                            key={itemId}
                            className="flex min-h-10 items-center justify-between gap-2 rounded-md border bg-background px-3 text-sm md:min-h-9"
                        >
                            <span className="truncate">
                                {known.get(String(itemId))?.label ??
                                    `#${itemId}`}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={t('Remove {name}', {
                                    name:
                                        known.get(String(itemId))?.label ??
                                        itemId,
                                })}
                                onClick={() =>
                                    onChange(
                                        ids.filter((other) => other !== itemId),
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            {!full && (
                <SearchableSelect
                    id={id}
                    value={multiple ? '' : String(ids[0] ?? '')}
                    onValueChange={(picked) => {
                        const pickedId = Number(picked);

                        if (multiple) {
                            onChange([...ids, pickedId]);
                        } else {
                            onChange(pickedId || null);
                        }
                    }}
                    options={multiple ? options : singleOptions}
                    placeholder={
                        multiple ? t('Add an item') : t('Pick an item')
                    }
                    searchPlaceholder={t('Type to search')}
                    emptyText={error ?? t('No match yet. Keep typing.')}
                    onSearchChange={setQuery}
                    aria-invalid={invalid}
                    aria-label={label}
                    className="w-full"
                />
            )}
            {!multiple && ids.length === 1 && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="justify-self-start"
                    onClick={() => onChange(null)}
                >
                    {t('Clear')}
                </Button>
            )}
        </div>
    );
}

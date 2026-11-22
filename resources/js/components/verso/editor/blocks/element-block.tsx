import { Plus, Trash } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { msg, t } from '@/lib/i18n';
import { plainText } from '@/lib/utils';
import type { BlockFieldProps } from './shared';
import { stringAttr, withAttrs } from './shared';

export type ElementTag = { value: string; label: string };

// Tags the server allows; the editor settings replace this list when they load.
let elementTags: ElementTag[] = [
    { value: 'div', label: msg('Box (div)') },
    { value: 'section', label: msg('Section') },
    { value: 'span', label: msg('Inline text (span)') },
];

export function setElementTags(tags: ElementTag[]): void {
    if (tags.length > 0) {
        elementTags = tags;
    }
}

export function getElementTags(): ElementTag[] {
    return elementTags;
}

// Attribute names allowed on every element, plus the ones some elements add.
const GLOBAL_ATTRIBUTES = ['id', 'class', 'role', 'lang', 'title', 'dir'];
const ELEMENT_ATTRIBUTES: Record<string, string[]> = {
    a: ['href', 'target', 'rel'],
    time: ['datetime'],
    details: ['open'],
    blockquote: ['cite'],
    ol: ['start', 'reversed'],
};
const CUSTOM_NAME = /^(data-[a-z0-9]+(-[a-z0-9]+)*|aria-[a-z]{2,24})$/;

// A warning for an attribute name the server would refuse, or null. The server check stays the final one.
export function attributeWarning(tag: string, name: string): string | null {
    if (name === '') {
        return null;
    }

    if (
        GLOBAL_ATTRIBUTES.includes(name) ||
        (ELEMENT_ATTRIBUTES[tag] ?? []).includes(name) ||
        CUSTOM_NAME.test(name)
    ) {
        return null;
    }

    return /^on/i.test(name) || name === 'style'
        ? t(
              '"{name}" is not allowed here. Use the HTML block for scripts and inline styles.',
              { name },
          )
        : t('"{name}" can not be used on {tag}.', { name, tag: `<${tag}>` });
}

type Row = [string, string];

function attributeRows(value: unknown): Row[] {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? Object.entries(value as Record<string, unknown>).map(
              ([name, item]): Row => [name, plainText(item)],
          )
        : [];
}

// The "HTML element" block: pick a tag, write its text and add attributes. Blocks can go inside.
export function ElementBlockFields({ block, onChange }: BlockFieldProps) {
    const id = useId();
    const tag = stringAttr(block, 'tag') || 'div';
    const rows = attributeRows(block.attrs?.attributes);

    const setRows = (next: Row[]) =>
        onChange(
            withAttrs(block, {
                attributes: Object.fromEntries(
                    next.filter(([name]) => name !== ''),
                ),
            }),
        );

    return (
        <div className="grid gap-3">
            <div className="grid gap-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)]">
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-tag`}>{t('Element')}</Label>
                    <OptionSelect
                        id={`${id}-tag`}
                        value={tag}
                        onValueChange={(next) =>
                            onChange(withAttrs(block, { tag: next }))
                        }
                        options={getElementTags().map((option) => ({
                            value: option.value,
                            label: t(option.label),
                            keywords: [option.value],
                        }))}
                        searchPlaceholder={t('Search elements')}
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`${id}-text`}>{t('Text (optional)')}</Label>
                    <Input
                        id={`${id}-text`}
                        value={block.content ?? ''}
                        placeholder={t('Text shown inside the element')}
                        onChange={(event) =>
                            onChange({ ...block, content: event.target.value })
                        }
                    />
                </div>
            </div>
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">
                    {t('Attributes')}
                </legend>
                {rows.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'For example an id, a class, or aria-label for screen readers.',
                        )}
                    </p>
                )}
                {rows.map(([name, value], index) => {
                    const warning = attributeWarning(tag, name);

                    return (
                        <div key={index} className="grid gap-1">
                            <div className="flex gap-2">
                                <Input
                                    aria-label={t('Attribute {number} name', {
                                        number: index + 1,
                                    })}
                                    value={name}
                                    placeholder={t('name')}
                                    aria-invalid={warning !== null}
                                    onChange={(event) =>
                                        setRows(
                                            rows.map((row, i): Row =>
                                                i === index
                                                    ? [
                                                          event.target.value
                                                              .trim()
                                                              .toLowerCase(),
                                                          row[1],
                                                      ]
                                                    : row,
                                            ),
                                        )
                                    }
                                    className="h-8 w-40 font-mono text-xs"
                                />
                                <Input
                                    aria-label={t('Attribute {number} value', {
                                        number: index + 1,
                                    })}
                                    value={value}
                                    placeholder={t('value')}
                                    onChange={(event) =>
                                        setRows(
                                            rows.map((row, i): Row =>
                                                i === index
                                                    ? [
                                                          row[0],
                                                          event.target.value,
                                                      ]
                                                    : row,
                                            ),
                                        )
                                    }
                                    className="h-8 min-w-0 flex-1 text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 px-2"
                                    aria-label={t('Remove attribute {name}', {
                                        name: name || String(index + 1),
                                    })}
                                    onClick={() =>
                                        setRows(
                                            rows.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <Trash />
                                </Button>
                            </div>
                            {warning && (
                                <p className="text-xs text-destructive">
                                    {warning}
                                </p>
                            )}
                        </div>
                    );
                })}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            const used = rows.map(([name]) => name);
                            const free = ['id', 'class', 'aria-label'].find(
                                (name) => !used.includes(name),
                            );
                            setRows([
                                ...rows,
                                [free ?? `data-item-${rows.length + 1}`, ''],
                            ]);
                        }}
                    >
                        <Plus />
                        {t('Add attribute')}
                    </Button>
                </div>
            </fieldset>
        </div>
    );
}

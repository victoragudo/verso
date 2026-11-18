import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { SectionCard } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { styles as saveStyles } from '@/routes/admin/site-editor';
import type { SitePartStyle } from '@/types';

type Props = {
    styles: SitePartStyle[];
    previewUrl: string;
};

// Big choices for the header and footer styles of the theme, with a preview of the home page.
export function PartStylePicker({ styles, previewUrl }: Props) {
    const [picked, setPicked] = useState<Record<string, string>>(() =>
        Object.fromEntries(styles.map((style) => [style.name, style.chosen])),
    );
    const [saving, setSaving] = useState(false);

    const query = new URLSearchParams(
        Object.entries(picked).map(([name, key]) => [`styles[${name}]`, key]),
    ).toString();
    const changed = styles.filter(
        (style) => picked[style.name] !== style.chosen,
    );

    const save = () => {
        setSaving(true);
        changed.forEach((style, index) =>
            router.put(
                saveStyles.url(),
                { part: style.name, style: picked[style.name] },
                {
                    preserveScroll: true,
                    onFinish: () =>
                        index === changed.length - 1 && setSaving(false),
                },
            ),
        );
    };

    return (
        <SectionCard
            title={t('Header and footer style')}
            description={t(
                'Pick a look. The preview shows it on your home page before you use it.',
            )}
            actions={
                <Button
                    type="button"
                    disabled={changed.length === 0 || saving}
                    onClick={save}
                >
                    {t('Use these styles')}
                </Button>
            }
        >
            <div className="grid gap-6 @4xl/page:grid-cols-[clamp(20rem,22cqi,28rem)_minmax(0,1fr)]">
                <div className="grid content-start gap-6">
                    {styles.map((style) => (
                        <fieldset key={style.name} className="grid gap-3">
                            <legend className="mb-2 text-sm font-semibold">
                                {style.label}
                            </legend>
                            {style.variants.map((variant) => {
                                const checked =
                                    picked[style.name] === variant.key;

                                return (
                                    <label
                                        key={variant.key}
                                        className={cn(
                                            'flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                            checked
                                                ? 'border-primary bg-accent'
                                                : 'hover:bg-accent/50',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name={`style-${style.name}`}
                                            value={variant.key}
                                            checked={checked}
                                            onChange={() =>
                                                setPicked((current) => ({
                                                    ...current,
                                                    [style.name]: variant.key,
                                                }))
                                            }
                                            className="mt-1 size-4 accent-primary"
                                        />
                                        <span className="grid gap-1">
                                            <span className="text-sm font-medium">
                                                {variant.key === style.chosen
                                                    ? t('{style} (in use)', {
                                                          style: variant.label,
                                                      })
                                                    : variant.label}
                                            </span>
                                            {variant.description && (
                                                <span className="text-sm text-muted-foreground">
                                                    {variant.description}
                                                </span>
                                            )}
                                        </span>
                                    </label>
                                );
                            })}
                        </fieldset>
                    ))}
                </div>
                <iframe
                    title={t('Preview of the picked styles on your home page')}
                    src={`${previewUrl}?${query}`}
                    className="h-[70vh] min-h-[28rem] w-full rounded-md border bg-white"
                    data-test="style-preview"
                />
            </div>
        </SectionCard>
    );
}

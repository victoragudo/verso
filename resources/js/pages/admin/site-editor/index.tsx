import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    EmptyState,
    PageHeader,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import { CustomizationActions } from '@/components/verso/site/customization-actions';
import { PartStylePicker } from '@/components/verso/site/part-style-picker';
import {
    PART_STATUS,
    partNameInSentence,
} from '@/components/verso/site/site-part-status';
import { StylesPanel } from '@/components/verso/site/styles-panel';
import { msg, t, Trans } from '@/lib/i18n';
import { siteEditor } from '@/routes/admin';
import { edit } from '@/routes/admin/site-editor';
import type { GlobalStylesData, SitePartStyle, SitePartSummary } from '@/types';

type Props = {
    theme: { name: string; slug: string };
    parts: SitePartSummary[];
    styles: SitePartStyle[];
    globalStyles: GlobalStylesData;
    previewUrl: string;
};

// Short help for the parts most themes have. Other parts get a general line.
const PART_HELP: Record<string, string> = {
    header: msg('The top of every page: logo, menu and search.'),
    footer: msg(
        'The bottom of every page: links, social profiles and copyright.',
    ),
};

// Site Editor home: the header, footer and other parts of the active theme.
export default function SiteEditorIndex({
    theme,
    parts,
    styles,
    globalStyles,
    previewUrl,
}: Props) {
    const errors =
        usePage<{ errors?: Record<string, string> }>().props.errors ?? {};

    return (
        <>
            <Head title={t('Site Editor')} />
            <PageHeader
                title={t('Site Editor')}
                icon="siteEditor"
                description={t(
                    'Change the parts of {theme} that every page shares. Edits stay private until you publish them.',
                    { theme: theme.name },
                )}
                actions={<CustomizationActions themeName={theme.name} />}
            />
            {errors.file && (
                <p
                    role="alert"
                    className="mb-6 rounded-md bg-danger-soft p-3 text-sm text-danger-text"
                >
                    {errors.file}
                </p>
            )}

            {parts.length === 0 ? (
                <EmptyState
                    icon="siteEditor"
                    title={t('This theme has no parts to edit')}
                    description={t(
                        'Pick a theme with a header or footer in Themes to use the Site Editor.',
                    )}
                />
            ) : (
                <ul
                    className="auto-grid gap-4 [--grid-min:18rem]"
                    data-test="site-parts"
                >
                    {parts.map((part) => (
                        <li
                            key={part.key}
                            className="flex flex-col gap-4 rounded-lg border bg-card p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h2 className="text-base font-semibold">
                                    {part.label}
                                </h2>
                                <StatusBadge
                                    tone={PART_STATUS[part.status].tone}
                                >
                                    {t(PART_STATUS[part.status].label)}
                                </StatusBadge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    PART_HELP[part.key.split('-')[0]] ??
                                        msg(
                                            'A piece of the theme used on some pages.',
                                        ),
                                )}
                            </p>
                            {part.editedAt && (
                                <p className="text-xs text-muted-foreground">
                                    <Trans
                                        text={t('Last change <date></date>')}
                                        components={{
                                            date: () => (
                                                <SiteDate iso={part.editedAt} />
                                            ),
                                        }}
                                    />
                                </p>
                            )}
                            <Button
                                asChild
                                variant="outline"
                                className="mt-auto self-start"
                            >
                                <Link href={edit(part.key).url}>
                                    <Pencil aria-hidden="true" />
                                    {t('Edit {part}', {
                                        part: partNameInSentence(part.label),
                                    })}
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {styles.length > 0 && (
                <div className="mt-8">
                    <PartStylePicker styles={styles} previewUrl={previewUrl} />
                </div>
            )}

            {globalStyles.groups.length > 0 && (
                <div className="mt-8">
                    <StylesPanel
                        data={globalStyles}
                        previewUrl={previewUrl}
                        errors={errors}
                    />
                </div>
            )}
        </>
    );
}

SiteEditorIndex.layout = {
    breadcrumbs: [{ title: msg('Site Editor'), href: siteEditor() }],
};

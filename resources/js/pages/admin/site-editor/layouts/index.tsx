import { Head, Link, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { conditionSummary } from '@/components/verso/layouts/conditions';
import type {
    LayoutConditionDefinition,
    LayoutKindName,
    LayoutSummary,
} from '@/components/verso/layouts/types';
import {
    EmptyState,
    PageHeader,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import { msg, t, Trans } from '@/lib/i18n';
import { store } from '@/routes/admin/theme-builder';

type Props = {
    kinds: { value: LayoutKindName; label: string }[];
    layouts: LayoutSummary[];
    conditions: LayoutConditionDefinition[];
};

// What each kind of layout is for, in plain words.
const KIND_HELP: Record<LayoutKindName, string> = {
    template: msg(
        'Whole pages for posts, pages, archives, search and more, with dynamic blocks and query loops.',
    ),
    header: msg(
        'Headers that replace the theme header where their rules match: sticky, see through, mega menus, a mobile menu.',
    ),
    footer: msg(
        'Footers that replace the theme footer where their rules match.',
    ),
    popup: msg(
        'Popups, slide-ins, top bars and the cookie notice, with triggers, how often they show and on which screens.',
    ),
};

const STATUS: Record<
    LayoutSummary['status'],
    { label: string; tone: 'success' | 'warning' | 'neutral' }
> = {
    published: { label: msg('Live'), tone: 'success' },
    draft: { label: msg('Unpublished changes'), tone: 'warning' },
    off: { label: msg('Off'), tone: 'neutral' },
};

// Theme builder: every template, header, footer and popup built with blocks, grouped by kind.
export default function ThemeBuilderIndex({
    kinds,
    layouts,
    conditions,
}: Props) {
    return (
        <>
            <Head title={t('Theme builder')} />
            <PageHeader
                title={t('Theme builder')}
                icon="siteEditor"
                description={t(
                    'Build templates, headers, footers and popups with blocks, and choose where each one shows.',
                )}
            />
            <div className="grid gap-10">
                {kinds.map((kind) => (
                    <KindSection
                        key={kind.value}
                        kind={kind}
                        layouts={layouts.filter(
                            (layout) => layout.kind === kind.value,
                        )}
                        conditions={conditions}
                    />
                ))}
            </div>
        </>
    );
}

function KindSection({
    kind,
    layouts,
    conditions,
}: {
    kind: { value: LayoutKindName; label: string };
    layouts: LayoutSummary[];
    conditions: LayoutConditionDefinition[];
}) {
    const id = useId();
    const form = useForm({ kind: kind.value, name: '' });

    return (
        <section
            aria-labelledby={`${id}-title`}
            className="grid gap-4"
            data-test={`layouts-${kind.value}`}
        >
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div className="grid gap-1">
                    <h2 id={`${id}-title`} className="text-lg font-semibold">
                        {kind.label}
                    </h2>
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {t(KIND_HELP[kind.value])}
                    </p>
                </div>
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store().url);
                    }}
                >
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-name`} className="text-xs">
                            {t('Name of the new {kind}', {
                                kind: kind.label.toLowerCase(),
                            })}
                        </Label>
                        <Input
                            id={`${id}-name`}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            maxLength={120}
                            required
                            className="h-9 w-64"
                            aria-invalid={form.errors.name ? true : undefined}
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={
                            form.processing || form.data.name.trim() === ''
                        }
                    >
                        <Plus />
                        {t('Create')}
                    </Button>
                    {form.errors.name && (
                        <p
                            role="alert"
                            className="basis-full text-sm text-destructive"
                        >
                            {form.errors.name}
                        </p>
                    )}
                </form>
            </div>
            {layouts.length === 0 ? (
                <EmptyState
                    icon="siteEditor"
                    title={t('Nothing here yet')}
                    description={t(
                        'Give it a name and press Create to start from a ready layout.',
                    )}
                    className="rounded-lg border border-dashed py-8"
                />
            ) : (
                <ul className="auto-grid gap-4 [--grid-min:20rem]">
                    {layouts.map((layout) => (
                        <li
                            key={layout.id}
                            className="flex flex-col gap-3 rounded-lg border bg-card p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <h3 className="text-base font-semibold">
                                    {layout.name}
                                </h3>
                                <StatusBadge tone={STATUS[layout.status].tone}>
                                    {t(STATUS[layout.status].label)}
                                </StatusBadge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    {t('Shows on:')}
                                </span>{' '}
                                {conditionSummary(
                                    layout.conditions,
                                    conditions,
                                )}
                            </p>
                            {layout.priority !== 0 && (
                                <p className="text-xs text-muted-foreground">
                                    {t('Priority {priority}', {
                                        priority: layout.priority,
                                    })}
                                </p>
                            )}
                            {layout.updatedAt && (
                                <p className="text-xs text-muted-foreground">
                                    <Trans
                                        text={t('Last change <date></date>')}
                                        components={{
                                            date: () => (
                                                <SiteDate
                                                    iso={layout.updatedAt}
                                                />
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
                                <Link href={layout.editUrl}>
                                    <Pencil />
                                    {t('Edit {name}', { name: layout.name })}
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

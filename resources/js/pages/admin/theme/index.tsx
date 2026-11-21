import { Head, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { RemoveExtensionDialog } from '@/components/verso/extensions/remove-extension-dialog';
import { RiskBadge } from '@/components/verso/extensions/verification-report';
import type { RemovableExtension } from '@/components/verso/extensions/remove-extension-dialog';
import {
    EmptyState,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import { index as extensionsIndex } from '@/routes/admin/extensions';
import { activate, customize, index, show } from '@/routes/admin/themes';
import { clear } from '@/routes/admin/themes/cache';
import { msg, t, Trans } from '@/lib/i18n';
import { themeFeatures } from '@/lib/theme-features';
import type { PageCacheInfo, ThemeInfo } from '@/types';

type Props = {
    themes: ThemeInfo[];
    pageCache: PageCacheInfo;
};

// Lists the installed themes, lets admins activate one and clear the page cache.
export default function ThemesIndex({ themes, pageCache }: Props) {
    const [busy, setBusy] = useState(false);
    const busyWhileSending = {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    };

    const activateTheme = (theme: ThemeInfo) =>
        router.post(activate.url(theme.slug), {}, busyWhileSending);

    const [removing, setRemoving] = useState<RemovableExtension | null>(null);

    const removeTheme = (theme: ThemeInfo) =>
        setRemoving({ kind: 'theme', slug: theme.slug, name: theme.name });

    const clearCache = () => router.post(clear.url(), {}, busyWhileSending);

    return (
        <>
            <Head title={t('Themes')} />
            <PageHeader
                icon="themes"
                title={t('Themes')}
                description={t(
                    'Choose how your public site looks. Themes live in the themes folder.',
                )}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={clearCache}
                            disabled={busy}
                            className="min-h-10 md:min-h-9"
                        >
                            {t('Clear page cache')}
                        </Button>
                        <Button asChild className="min-h-10 md:min-h-9">
                            <Link href={extensionsIndex.url()}>
                                {t('Add theme')}
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="mb-4 flex flex-col gap-2 rounded-lg border bg-card px-4 py-3 text-sm shadow-xs sm:flex-row sm:items-center sm:gap-3">
                <StatusBadge
                    tone={pageCache.enabled ? 'success' : 'neutral'}
                    className="self-start sm:self-auto"
                >
                    {pageCache.enabled ? t('Cache on') : t('Cache off')}
                </StatusBadge>
                <p className="text-muted-foreground">
                    {pageCache.enabled
                        ? t(
                              '{seconds, plural, one {Page cache is on. Pages are kept for # second and refresh when content changes.} other {Page cache is on. Pages are kept for # seconds and refresh when content changes.}}',
                              { seconds: pageCache.ttl },
                          )
                        : t('Page cache is off.')}
                </p>
            </div>

            {themes.length === 0 ? (
                <SectionCard>
                    <EmptyState
                        icon="themes"
                        title={t('No themes found.')}
                        description={t(
                            'Add a theme to the themes folder to see it here.',
                        )}
                    />
                </SectionCard>
            ) : (
                <div className="auto-grid gap-4 [--grid-min:20rem]">
                    {themes.map((theme) => (
                        <ThemeCard
                            key={theme.slug}
                            theme={theme}
                            onActivate={() => activateTheme(theme)}
                            busy={busy}
                            onRemove={() => removeTheme(theme)}
                        />
                    ))}
                </div>
            )}

            <RemoveExtensionDialog
                extension={removing}
                onClose={() => setRemoving(null)}
            />
        </>
    );
}

// One theme with its details and the activate button.
function ThemeCard({
    theme,
    onActivate,
    onRemove,
    busy,
}: {
    theme: ThemeInfo;
    busy: boolean;
    onActivate: () => void;
    onRemove: () => void;
}) {
    return (
        <article className="flex min-w-0 flex-col rounded-lg border bg-card shadow-xs">
            <div className="flex flex-1 flex-col gap-2 p-4 sm:p-5">
                <div className="flex items-start justify-between gap-2">
                    <h2 className="min-w-0 text-sm font-semibold break-words text-foreground">
                        {theme.name}
                    </h2>
                    <div className="flex flex-wrap justify-end gap-1.5">
                        {theme.risk !== null && <RiskBadge risk={theme.risk} />}
                        {theme.active && (
                            <StatusBadge tone="success">
                                {t('Active')}
                            </StatusBadge>
                        )}
                    </div>
                </div>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {theme.author !== ''
                        ? t('Version {version} · {author}', {
                              version: theme.version,
                              author: theme.author,
                          })
                        : t('Version {version}', { version: theme.version })}
                </p>
                <p className="text-sm text-body">
                    {theme.description || t('No description.')}
                </p>
                {theme.parent !== null && (
                    <p
                        className="text-xs text-muted-foreground"
                        data-test="theme-parent"
                    >
                        <Trans
                            text={t('Child of <name>{parent}</name>', {
                                parent: theme.parentName ?? theme.parent,
                            })}
                            components={{
                                name: (text) => (
                                    <span className="font-medium text-foreground">
                                        {text}
                                    </span>
                                ),
                            }}
                        />
                    </p>
                )}
                {theme.missingParent !== null && (
                    <StatusBadge tone="danger" className="self-start">
                        {t('Parent theme {parent} is missing', {
                            parent: theme.missingParent,
                        })}
                    </StatusBadge>
                )}
                {themeFeatures(theme) !== '' && (
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {themeFeatures(theme)}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-2 rounded-b-lg border-t bg-muted/40 px-4 py-3 sm:px-5">
                {theme.active ? (
                    <Button
                        variant="ghost"
                        disabled
                        className="min-h-10 md:min-h-9"
                    >
                        <Check />
                        {t('In use')}
                    </Button>
                ) : (
                    <Button
                        onClick={onActivate}
                        disabled={busy || theme.missingParent !== null}
                        className="min-h-10 md:min-h-9"
                    >
                        {t('Activate')}
                    </Button>
                )}
                {theme.customizable && (
                    <Button
                        asChild
                        variant="outline"
                        className="min-h-10 md:min-h-9"
                    >
                        <Link href={customize.url(theme.slug)}>
                            {t('Customize')}
                        </Link>
                    </Button>
                )}
                <Button asChild variant="ghost" className="min-h-10 md:min-h-9">
                    <Link href={show.url(theme.slug)}>
                        {theme.risk === null ? t('Check') : t('Details')}
                    </Link>
                </Button>
                {theme.removable && (
                    <Button
                        variant="ghost"
                        onClick={onRemove}
                        className="ms-auto min-h-10 md:min-h-9"
                    >
                        {t('Remove')}
                    </Button>
                )}
            </div>
        </article>
    );
}

ThemesIndex.layout = {
    breadcrumbs: [{ title: msg('Themes'), href: index() }],
};

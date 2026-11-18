import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { VersoIcon } from '@/components/verso/icons';
import {
    AuditLogCard,
    ServerLimitsCard,
    SettingField,
} from '@/components/verso/system';
import type {
    AuditEntry,
    ServerLimits,
    SettingValue,
    SystemGroup,
    SystemSetting,
} from '@/components/verso/system';
import { NoticeBanner, PageHeader, SectionCard } from '@/components/verso/ui';
import { formatList, formatNumber, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { reset, update } from '@/routes/admin/system/settings';

type Props = {
    groups: SystemGroup[];
    parameters: SystemSetting[];
    server: ServerLimits;
    audit: AuditEntry[];
    healthUrl: string;
};

type Values = Record<string, SettingValue>;

// The group in the address bar (?group=uploads), so a link or a reload opens the same page.
function initialGroup(groups: SystemGroup[]): string {
    const wanted =
        typeof window === 'undefined'
            ? null
            : new URLSearchParams(window.location.search).get('group');

    return (
        groups.find((group) => group.key === wanted)?.key ??
        groups[0]?.key ??
        ''
    );
}

function same(a: SettingValue, b: SettingValue): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

// System settings: every registered setting in groups, with search, units, reset and server limits.
export default function SystemSettingsPage({
    groups,
    parameters,
    server,
    audit,
    healthUrl,
}: Props) {
    const saved = useMemo(
        () =>
            Object.fromEntries(
                parameters.map((setting) => [setting.key, setting.value]),
            ) as Values,
        [parameters],
    );
    const form = useForm<{ values: Values }>({ values: saved });
    const [group, setGroup] = useState(() => initialGroup(groups));
    const [query, setQuery] = useState('');
    const [changedOnly, setChangedOnly] = useState(false);
    const [version, setVersion] = useState(0);
    const errors = form.errors as Record<string, string | undefined>;
    const labels = useMemo(
        () => Object.fromEntries(groups.map((item) => [item.key, item.label])),
        [groups],
    );

    const dirtyKeys = parameters
        .filter(
            (setting) => !same(form.data.values[setting.key], setting.value),
        )
        .map((setting) => setting.key);
    const problems = parameters.filter((setting) => setting.problem !== null);
    const search = query.trim().toLowerCase();
    const visible = parameters.filter((setting) => {
        if (
            changedOnly &&
            !setting.changed &&
            !dirtyKeys.includes(setting.key)
        ) {
            return false;
        }

        if (search !== '') {
            return [
                setting.label,
                setting.help,
                setting.key,
                labels[setting.group] ?? '',
            ].some((text) => text.toLowerCase().includes(search));
        }

        return changedOnly || setting.group === group;
    });
    const current = groups.find((item) => item.key === group);
    const showingAll = search !== '' || changedOnly;

    form.transform((data) => ({
        values: Object.fromEntries(
            dirtyKeys.map((key) => [key, data.values[key]]),
        ),
    }));

    const pickGroup = (key: string) => {
        setGroup(key);
        setQuery('');
        setChangedOnly(false);
        const url = new URL(window.location.href);
        url.searchParams.set('group', key);
        window.history.replaceState(window.history.state, '', url);
    };

    const change = (key: string, value: SettingValue) =>
        form.setData('values', { ...form.data.values, [key]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: (page) =>
                refresh(page.props.parameters as SystemSetting[], []),
            onError: () => {
                requestAnimationFrame(() =>
                    document
                        .querySelector<HTMLElement>('[aria-invalid="true"]')
                        ?.focus(),
                );
            },
        });
    };

    const discard = () => {
        form.setData('values', saved);
        form.clearErrors();
        setVersion((value) => value + 1);
    };

    // Take the saved values from fresh props, but keep unsaved edits of the other settings.
    const refresh = (fresh: SystemSetting[], keep: string[]) => {
        const next = Object.fromEntries(
            fresh.map((setting) => [setting.key, setting.value]),
        ) as Values;
        keep.forEach((key) => {
            next[key] = form.data.values[key];
        });
        form.setData('values', next);
        setVersion((value) => value + 1);
    };

    const resetSetting = (key: string) =>
        window.confirm(t('Go back to the default value of this setting?')) &&
        router.post(
            reset.url(),
            { key },
            {
                preserveScroll: true,
                onSuccess: (page) =>
                    refresh(
                        page.props.parameters as SystemSetting[],
                        dirtyKeys.filter((dirty) => dirty !== key),
                    ),
            },
        );

    const changedIn = (key: string) =>
        parameters.filter((setting) => setting.group === key && setting.changed)
            .length;

    return (
        <>
            <Head title={t('System settings')} />
            <PageHeader
                icon="customize"
                title={t('System settings')}
                description={t(
                    'Limits, sizes and time outs of Verso and its plugins. Values set in the server environment stay locked.',
                )}
                actions={
                    <Button variant="outline" asChild>
                        <Link href={healthUrl}>
                            <VersoIcon name="shield" size={16} />
                            {t('Server health')}
                        </Link>
                    </Button>
                }
            />

            {problems.length > 0 && (
                <NoticeBanner
                    className="mb-4"
                    tone="warning"
                    icon="warning"
                    title={t(
                        '{count, plural, one {# saved value is not valid any more} other {# saved values are not valid any more}}',
                        { count: problems.length },
                    )}
                    description={t(
                        'Verso uses the default instead: {settings}.',
                        {
                            settings: formatList(
                                problems.map((setting) => setting.label),
                            ),
                        },
                    )}
                />
            )}

            <div className="@container/system">
                <div className="grid items-start gap-6 @5xl/system:grid-cols-[13rem_minmax(0,1fr)] @[120rem]/system:grid-cols-[14rem_minmax(0,1fr)_minmax(24rem,30rem)]">
                    <nav
                        aria-label={t('Settings groups')}
                        className="min-w-0 @5xl/system:sticky @5xl/system:top-20"
                    >
                        <ul className="-m-1 flex gap-1 overflow-x-auto p-1 @5xl/system:m-0 @5xl/system:grid @5xl/system:overflow-visible @5xl/system:p-0">
                            {groups.map((item) => {
                                const active =
                                    !showingAll && item.key === group;
                                const count = changedIn(item.key);

                                return (
                                    <li key={item.key} className="shrink-0">
                                        <button
                                            type="button"
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                            onClick={() => pickGroup(item.key)}
                                            className={cn(
                                                'flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-start text-sm whitespace-nowrap transition-colors md:min-h-9 @5xl/system:py-2 @5xl/system:whitespace-normal',
                                                active
                                                    ? 'bg-card font-medium text-foreground shadow-xs ring-1 ring-border'
                                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                            )}
                                        >
                                            <VersoIcon
                                                name={item.icon}
                                                size={16}
                                                className="shrink-0"
                                            />
                                            <span className="min-w-0 flex-1">
                                                {item.label}
                                            </span>
                                            {count > 0 && (
                                                <span
                                                    className="rounded bg-info-soft px-1.5 text-xs text-info-text tabular-nums"
                                                    title={t(
                                                        '{count} changed',
                                                        { count },
                                                    )}
                                                >
                                                    <span aria-hidden="true">
                                                        {formatNumber(count)}
                                                    </span>
                                                    <span className="sr-only">
                                                        {t('{count} changed', {
                                                            count,
                                                        })}
                                                    </span>
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </nav>

                    <form
                        onSubmit={submit}
                        noValidate={false}
                        className="grid min-w-0 gap-4"
                    >
                        <search className="flex flex-wrap items-center gap-3">
                            <label
                                htmlFor="settings-search"
                                className="sr-only"
                            >
                                {t('Search all settings')}
                            </label>
                            <Input
                                id="settings-search"
                                type="search"
                                value={query}
                                placeholder={t('Search all settings')}
                                autoComplete="off"
                                enterKeyHint="search"
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                onKeyDown={(event) =>
                                    event.key === 'Enter' &&
                                    event.preventDefault()
                                }
                                className="min-w-0 flex-1 basis-64"
                            />
                            <label className="flex min-h-10 items-center gap-2 text-sm md:min-h-9">
                                <Checkbox
                                    checked={changedOnly}
                                    onCheckedChange={(checked) =>
                                        setChangedOnly(checked === true)
                                    }
                                />
                                {t('Show changed settings only')}
                            </label>
                        </search>

                        <SectionCard
                            icon={showingAll ? 'search' : current?.icon}
                            title={
                                showingAll
                                    ? search !== ''
                                        ? t('Results for "{query}"', {
                                              query: query.trim(),
                                          })
                                        : t('Changed settings')
                                    : current?.label
                            }
                            description={
                                showingAll
                                    ? t(
                                          '{count, plural, one {# setting in every group.} other {# settings in every group.}}',
                                          { count: visible.length },
                                      )
                                    : current?.description
                            }
                            contentClassName="@container/settings"
                        >
                            {visible.length === 0 ? (
                                <p
                                    role="status"
                                    className="text-sm text-muted-foreground"
                                >
                                    {search !== ''
                                        ? t('No setting matches your search.')
                                        : t(
                                              'No setting is changed yet. Every value is the default.',
                                          )}
                                </p>
                            ) : (
                                <div className="grid divide-y @[110rem]/settings:grid-cols-2 @[110rem]/settings:gap-x-12 @[110rem]/settings:divide-y-0 @[110rem]/settings:[&>*]:border-b @[110rem]/settings:[&>*]:py-4">
                                    {visible.map((setting) => (
                                        <SettingField
                                            key={`${setting.key}-${version}`}
                                            setting={setting}
                                            value={
                                                form.data.values[setting.key]
                                            }
                                            error={
                                                errors[`values.${setting.key}`]
                                            }
                                            onChange={(value) =>
                                                change(setting.key, value)
                                            }
                                            onReset={() =>
                                                resetSetting(setting.key)
                                            }
                                            groupLabel={
                                                showingAll
                                                    ? labels[setting.group]
                                                    : undefined
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                        </SectionCard>

                        {dirtyKeys.length > 0 && (
                            <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
                                <p role="status" className="text-sm">
                                    {t(
                                        '{count, plural, one {# unsaved change} other {# unsaved changes}}',
                                        { count: dirtyKeys.length },
                                    )}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={discard}
                                    >
                                        {t('Discard')}
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {t('Save changes')}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </form>

                    <aside className="grid min-w-0 gap-4 @5xl/system:col-start-2 @[120rem]/system:col-start-3 @[120rem]/system:row-start-1">
                        <ServerLimitsCard server={server} />
                        <AuditLogCard entries={audit} />
                    </aside>
                </div>
            </div>
        </>
    );
}

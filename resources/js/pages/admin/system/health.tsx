import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { VersoIcon } from '@/components/verso/icons';
import { CodeSnippet } from '@/components/verso/system';
import type {
    HealthCheck,
    HealthStatus,
    IniValue,
} from '@/components/verso/system';
import {
    PageHeader,
    SectionCard,
    StatCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { StatusTone } from '@/components/verso/ui';
import { formatList, msg, t } from '@/lib/i18n';
import { fix } from '@/routes/admin/system/health';

type Props = {
    php: {
        version: string;
        minimum: string;
        sapi: string;
        webServer: string;
        iniFile: string | null;
        user: string;
        group: string;
    };
    extensions: { name: string; loaded: boolean; required: boolean }[];
    loadedExtensions: string[];
    ini: IniValue[];
    checks: HealthCheck[];
    plan: { description: string; done: boolean; error: string | null }[];
    modes: { folders: string; files: string; env: string };
    settingsUrl: string;
};

const TONES: Record<HealthStatus, StatusTone> = {
    ok: 'success',
    info: 'info',
    warning: 'warning',
    danger: 'danger',
};
const WORDS: Record<HealthStatus, string> = {
    ok: msg('Good'),
    info: msg('Note'),
    warning: msg('Warning'),
    danger: msg('Risk'),
};
const GROUPS: {
    key: HealthCheck['group'];
    title: string;
    description: string;
    icon: string;
}[] = [
    {
        key: 'web',
        title: msg('Web exposure'),
        description: msg('What visitors could reach or see.'),
        icon: 'shield',
    },
    {
        key: 'files',
        title: msg('Secrets and files'),
        description: msg('The .env file, the storage link and file modes.'),
        icon: 'fields',
    },
    {
        key: 'folders',
        title: msg('Folders'),
        description: msg('Folders PHP must be able to write to.'),
        icon: 'importer',
    },
];

// The Server health page: PHP, extensions, ini values, folders, modes and risky states.
export default function ServerHealthPage({
    php,
    extensions,
    loadedExtensions,
    ini,
    checks,
    plan,
    modes,
    settingsUrl,
}: Props) {
    const [open, setOpen] = useState(false);
    const risks = checks.filter((check) => check.status === 'danger').length;
    const warnings = checks.filter(
        (check) => check.status === 'warning',
    ).length;
    const fixable = plan.filter((item) => item.error === null);
    const missing = extensions.filter(
        (extension) => extension.required && !extension.loaded,
    );

    return (
        <>
            <Head title={t('Server health')} />
            <PageHeader
                icon="shield"
                title={t('Server health')}
                description={t(
                    'PHP, folders, file modes and risky settings of this server, with safe fixes.',
                )}
                actions={
                    <>
                        {fixable.length > 0 && (
                            <Button type="button" onClick={() => setOpen(true)}>
                                {t(
                                    '{count, plural, one {Fix # problem} other {Fix # problems}}',
                                    { count: fixable.length },
                                )}
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={settingsUrl}>
                                <VersoIcon name="customize" size={16} />
                                {t('System settings')}
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="@container/health grid gap-6">
                <div className="auto-fit-grid gap-4 [--grid-min:13rem]">
                    <StatCard
                        label={t('Risks')}
                        value={risks}
                        icon="warning"
                        hint={
                            risks === 0
                                ? t('Nothing risky found.')
                                : t('Fix these first.')
                        }
                    />
                    <StatCard
                        label={t('Warnings')}
                        value={warnings}
                        icon="shield"
                        hint={t(
                            '{count, plural, one {# check in total.} other {# checks in total.}}',
                            { count: checks.length },
                        )}
                    />
                    <StatCard
                        label="PHP"
                        value={php.version}
                        icon="settings"
                        hint={t('{sapi}, needs {minimum} or newer.', {
                            sapi: php.sapi,
                            minimum: php.minimum,
                        })}
                    />
                    <StatCard
                        label={t('Runs as')}
                        value={php.user}
                        icon="users"
                        hint={
                            php.group
                                ? t('Group {group}, on {server}.', {
                                      group: php.group,
                                      server: php.webServer,
                                  })
                                : php.webServer
                        }
                    />
                </div>

                <div className="grid items-start gap-4 @5xl/health:grid-cols-[minmax(0,3fr)_minmax(22rem,2fr)] @[150rem]/health:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <div className="grid min-w-0 gap-4">
                        {GROUPS.map((group) => (
                            <SectionCard
                                key={group.key}
                                icon={group.icon}
                                title={t(group.title)}
                                description={t(group.description)}
                            >
                                <ul className="grid divide-y">
                                    {checks
                                        .filter(
                                            (check) =>
                                                check.group === group.key,
                                        )
                                        .map((check) => (
                                            <CheckRow
                                                key={check.key}
                                                check={check}
                                            />
                                        ))}
                                </ul>
                            </SectionCard>
                        ))}
                    </div>

                    <div className="grid min-w-0 gap-4">
                        <SectionCard
                            icon="customize"
                            title={t('Safe fixes')}
                            description={t(
                                'What Verso can fix itself, inside the project only. This list is a dry run: nothing has changed yet.',
                            )}
                        >
                            {plan.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'There is nothing Verso can fix by itself.',
                                    )}
                                </p>
                            ) : (
                                <ul className="grid gap-2 text-sm">
                                    {plan.map((item) => (
                                        <li
                                            key={item.description}
                                            className="flex items-start gap-2"
                                        >
                                            <StatusBadge
                                                tone={
                                                    item.error
                                                        ? 'neutral'
                                                        : 'info'
                                                }
                                            >
                                                {item.error
                                                    ? t('Skipped')
                                                    : t('Planned')}
                                            </StatusBadge>
                                            <span className="min-w-0 break-words">
                                                {item.description}
                                                {item.error && (
                                                    <span className="block text-muted-foreground">
                                                        {item.error}
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <p className="mt-4 text-xs text-muted-foreground">
                                {t(
                                    'Recommended modes: folders {folders}, files {files}, .env {env}.',
                                    modes,
                                )}
                            </p>
                        </SectionCard>

                        <SectionCard
                            icon="plugins"
                            title={t('PHP extensions')}
                            description={t(
                                '{count, plural, =0 {Every required extension is loaded.} one {# required extension is missing.} other {# required extensions are missing.}}',
                                { count: missing.length },
                            )}
                        >
                            <ul className="flex flex-wrap gap-2">
                                {extensions.map((extension) => (
                                    <li key={extension.name}>
                                        <StatusBadge
                                            tone={
                                                extension.loaded
                                                    ? 'success'
                                                    : extension.required
                                                      ? 'danger'
                                                      : 'neutral'
                                            }
                                        >
                                            {extension.name}
                                            <span className="sr-only">
                                                {' '}
                                                {extension.loaded
                                                    ? t('loaded')
                                                    : t('missing')}
                                            </span>
                                        </StatusBadge>
                                    </li>
                                ))}
                            </ul>
                            <details className="mt-4 text-sm">
                                <summary className="min-h-9 cursor-pointer font-medium">
                                    {t(
                                        '{count, plural, one {All # loaded extension} other {All # loaded extensions}}',
                                        { count: loadedExtensions.length },
                                    )}
                                </summary>
                                <p className="mt-2 break-words text-muted-foreground">
                                    {formatList(loadedExtensions)}
                                </p>
                            </details>
                        </SectionCard>
                    </div>

                    <SectionCard
                        icon="settings"
                        title={t('PHP values')}
                        description={
                            php.iniFile
                                ? t('Loaded from {file}.', {
                                      file: php.iniFile,
                                  })
                                : t('No php.ini file is loaded.')
                        }
                        className="min-w-0 @5xl/health:col-span-2 @[150rem]/health:col-span-1"
                    >
                        <IniTable values={ini} />
                    </SectionCard>
                </div>
            </div>

            <FixDialog
                open={open}
                onOpenChange={setOpen}
                fixes={fixable.map((item) => item.description)}
            />
        </>
    );
}

function CheckRow({ check }: { check: HealthCheck }) {
    return (
        <li className="grid gap-2 py-3 first:pt-0 last:pb-0">
            <div className="flex flex-wrap items-center gap-2">
                <StatusBadge tone={TONES[check.status]}>
                    {t(WORDS[check.status])}
                </StatusBadge>
                <span className="text-sm font-medium">{check.label}</span>
                {check.mode && (
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {check.recommended && check.status !== 'ok'
                            ? t('Mode {mode} (use {recommended})', {
                                  mode: check.mode,
                                  recommended: check.recommended,
                              })
                            : t('Mode {mode}', { mode: check.mode })}
                    </span>
                )}
            </div>
            {check.detail && (
                <p className="text-sm text-muted-foreground">{check.detail}</p>
            )}
            {check.command && check.status !== 'ok' && (
                <div className="grid gap-1">
                    <p className="text-xs text-muted-foreground">
                        {check.fixes.length > 0
                            ? t('Verso can fix this, or run:')
                            : t('Run on the server:')}
                    </p>
                    <CodeSnippet
                        code={check.command}
                        label={t('Copy the command for {check}', {
                            check: check.label,
                        })}
                    />
                </div>
            )}
        </li>
    );
}

function IniTable({ values }: { values: IniValue[] }) {
    return (
        <div className="-mx-4 overflow-x-auto sm:-mx-5">
            <table className="w-full text-sm">
                <caption className="sr-only">
                    {t('PHP ini values that matter to Verso')}
                </caption>
                <thead>
                    <tr className="border-b text-start text-xs text-muted-foreground">
                        <th
                            scope="col"
                            className="px-4 py-2 text-start font-medium sm:px-5"
                        >
                            {t('Name')}
                        </th>
                        <th
                            scope="col"
                            className="px-2 py-2 text-start font-medium"
                        >
                            {t('Value')}
                        </th>
                        <th
                            scope="col"
                            className="px-4 py-2 text-start font-medium sm:px-5"
                        >
                            {t('Can change')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {values.map((row) => (
                        <tr key={row.name} className="border-b last:border-b-0">
                            <th
                                scope="row"
                                className="px-4 py-2 text-start align-top font-normal sm:px-5"
                            >
                                <span className="font-mono text-xs">
                                    {row.name}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {row.about}
                                </span>
                            </th>
                            <td className="px-2 py-2 align-top font-medium tabular-nums">
                                {row.value ?? t('Not set')}
                            </td>
                            <td className="px-4 py-2 align-top text-xs sm:px-5">
                                {row.runtime
                                    ? t('At runtime')
                                    : row.perDirectory
                                      ? t('.user.ini or .htaccess')
                                      : t('php.ini only')}
                                <span className="block text-muted-foreground">
                                    {row.scope}
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function FixDialog({
    open,
    onOpenChange,
    fixes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fixes: string[];
}) {
    const form = useForm({ confirm: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(fix.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Fix these problems?')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Verso changes only paths inside the project and never follows links out of it.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <ul className="grid list-disc gap-1 ps-5 text-sm">
                        {fixes.map((description) => (
                            <li key={description}>{description}</li>
                        ))}
                    </ul>
                    <div className="grid gap-1">
                        <label className="flex items-start gap-3 text-sm">
                            <Checkbox
                                checked={form.data.confirm}
                                onCheckedChange={(checked) =>
                                    form.setData('confirm', checked === true)
                                }
                                className="mt-0.5"
                            />
                            {t(
                                'I checked the list and want Verso to make these changes.',
                            )}
                        </label>
                        <InputError message={form.errors.confirm} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Fix now')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

import { useForm } from '@inertiajs/react';
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
import { SectionCard, StatusBadge } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { phpLimits } from '@/routes/admin/system/settings';
import { CodeSnippet } from './code-snippet';
import type { ServerLimits } from './types';

// PHP and web server limits next to what the settings need: Apply to a managed file, or copy a snippet.
export function ServerLimitsCard({ server }: { server: ServerLimits }) {
    const [open, setOpen] = useState(false);
    const target = server.target;
    const canApply =
        target !== null && target.writable && server.recommended.length > 0;

    return (
        <SectionCard
            icon="devices"
            title={<span id="server-limits">{t('Server limits')}</span>}
            description={t('PHP {sapi} on {server}.', {
                sapi: server.sapi,
                server: server.webServer,
            })}
            className="scroll-mt-20"
            contentClassName="grid gap-4"
        >
            <dl className="grid grid-cols-2 gap-3 text-sm">
                {Object.entries(server.phpUpload).map(([name, value]) => (
                    <div
                        key={name}
                        className="min-w-0 rounded-md border px-3 py-2"
                    >
                        <dt className="font-mono text-xs break-all text-muted-foreground">
                            {name}
                        </dt>
                        <dd className="font-medium tabular-nums">
                            {value ?? t('Not set')}
                        </dd>
                    </div>
                ))}
            </dl>

            {server.recommended.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('PHP limits already fit your settings.')}
                </p>
            ) : (
                <div className="grid gap-3">
                    <p className="text-sm">
                        {t(
                            'These PHP values are lower than your settings need:',
                        )}
                    </p>
                    <ul className="grid gap-2 text-sm">
                        {server.recommended.map((row) => (
                            <li
                                key={row.name}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2"
                            >
                                <span className="font-mono text-xs">
                                    {row.name}
                                </span>
                                <span className="tabular-nums">
                                    {row.current ?? t('unset')} →{' '}
                                    <strong>{row.value}</strong>
                                </span>
                                <StatusBadge
                                    tone={
                                        row.scope === 'PHP_INI_ALL'
                                            ? 'success'
                                            : 'neutral'
                                    }
                                >
                                    {row.scope}
                                </StatusBadge>
                            </li>
                        ))}
                    </ul>
                    {target === null ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'This PHP setup ({sapi}) reads no file in public/, so copy a snippet below into the server configuration.',
                                { sapi: server.sapi },
                            )}
                        </p>
                    ) : !target.writable ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Verso cannot write {file}. Copy a snippet below, or give PHP write access to the file.',
                                { file: target.file },
                            )}
                        </p>
                    ) : null}
                    {canApply && (
                        <div>
                            <Button type="button" onClick={() => setOpen(true)}>
                                {t('Apply to {file}', { file: target.file })}
                            </Button>
                        </div>
                    )}
                </div>
            )}

            {server.snippets.length > 0 && (
                <div className="grid gap-2">
                    <h3 className="text-sm font-medium">
                        {t('Snippets for your server')}
                    </h3>
                    {server.snippets.map((snippet) => (
                        <details
                            key={snippet.key}
                            open={snippet.recommended}
                            className="group rounded-md border px-3 py-2"
                        >
                            <summary className="flex min-h-9 cursor-pointer items-center gap-2 text-sm font-medium">
                                {snippet.label}
                                {snippet.recommended && (
                                    <StatusBadge tone="info">
                                        {t('Fits this server')}
                                    </StatusBadge>
                                )}
                            </summary>
                            <div className="grid gap-2 pt-2 pb-1">
                                <p className="text-xs break-all text-muted-foreground">
                                    {snippet.file}
                                </p>
                                <CodeSnippet
                                    code={snippet.code}
                                    label={t('Copy the {name} snippet', {
                                        name: snippet.label,
                                    })}
                                />
                            </div>
                        </details>
                    ))}
                </div>
            )}

            {canApply && target && (
                <ApplyDialog
                    open={open}
                    onOpenChange={setOpen}
                    server={server}
                    target={target}
                />
            )}
        </SectionCard>
    );
}

function ApplyDialog({
    open,
    onOpenChange,
    server,
    target,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    server: ServerLimits;
    target: NonNullable<ServerLimits['target']>;
}) {
    const form = useForm({ target: target.key, confirm: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(phpLimits.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('Write PHP limits to {file}?', {
                            file: target.file,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Verso adds or replaces only this block. A copy of the old file is kept in storage/app/private/system/backups.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    {target.preview && (
                        <CodeSnippet
                            code={target.preview}
                            label={t('Copy the block')}
                        />
                    )}
                    {target.key === 'user_ini' && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                '{seconds, plural, one {PHP reads .user.ini again after up to # second (user_ini.cache_ttl), so the new limits can take a few minutes. A php_admin_value in the PHP-FPM pool still wins over this file.} other {PHP reads .user.ini again after up to # seconds (user_ini.cache_ttl), so the new limits can take a few minutes. A php_admin_value in the PHP-FPM pool still wins over this file.}}',
                                { seconds: server.cacheTtl },
                            )}
                        </p>
                    )}
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
                                'I understand that Verso changes {file} on this server.',
                                { file: target.file },
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
                            {t('Write the limits')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

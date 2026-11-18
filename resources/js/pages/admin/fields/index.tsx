import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatBytes, formatNumber, msg, t, Trans } from '@/lib/i18n';
import {
    copy,
    create,
    destroy,
    edit,
    exportMethod,
    importMethod,
    index,
} from '@/routes/admin/fields';

export type FieldGroupRow = {
    id: number | null;
    key: string;
    title: string;
    description: string;
    source: 'database' | 'code' | 'json';
    position: 'main' | 'sidebar';
    active: boolean;
    fields: number;
    location: string;
};

type Props = {
    groups: FieldGroupRow[];
    problems: Record<string, string>;
    importMaxKb: number;
};

const SOURCE_LABELS: Record<FieldGroupRow['source'], string> = {
    database: msg('Admin'),
    code: msg('Plugin code'),
    json: msg('JSON file'),
};

// Lists every field group, with import and export.
export default function FieldGroupsIndex({
    groups,
    problems,
    importMaxKb,
}: Props) {
    const [importing, setImporting] = useState(false);
    const problemList = Object.entries(problems);

    const columns: DataTableColumn<FieldGroupRow>[] = [
        {
            key: 'title',
            header: t('Field group'),
            primary: true,
            wrap: true,
            cell: (group) => (
                <div className="min-w-0">
                    {group.id ? (
                        <Link
                            href={edit(group.id)}
                            className="font-medium text-foreground hover:underline"
                        >
                            {group.title}
                        </Link>
                    ) : (
                        <span className="font-medium text-foreground">
                            {group.title}
                        </span>
                    )}
                    <p className="truncate font-mono text-xs text-muted-foreground">
                        {group.key}
                    </p>
                </div>
            ),
        },
        {
            key: 'location',
            header: t('Shows on'),
            wrap: true,
            cell: (group) => (
                <span className="text-sm text-muted-foreground">
                    {group.location}
                </span>
            ),
        },
        {
            key: 'fields',
            header: t('Fields'),
            className: 'tabular-nums',
            cell: (group) => formatNumber(group.fields),
        },
        {
            key: 'position',
            header: t('Position'),
            hideOnMobile: true,
            cell: (group) =>
                group.position === 'main' ? t('Main column') : t('Sidebar'),
        },
        {
            key: 'source',
            header: t('Source'),
            cell: (group) => (
                <StatusBadge
                    tone={group.source === 'database' ? 'info' : 'neutral'}
                >
                    {t(SOURCE_LABELS[group.source])}
                </StatusBadge>
            ),
        },
        {
            key: 'status',
            header: t('Status'),
            cell: (group) => (
                <StatusBadge tone={group.active ? 'success' : 'neutral'}>
                    {group.active ? t('Active') : t('Off')}
                </StatusBadge>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Custom fields')} />
            <PageHeader
                icon="fields"
                title={t('Custom fields')}
                description={t(
                    'Field groups add extra inputs to the entry editor.',
                )}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setImporting(true)}
                        >
                            <Upload />
                            {t('Import')}
                        </Button>
                        {groups.length > 0 && (
                            <Button variant="outline" asChild>
                                <a href={exportMethod.url()}>
                                    <Download />
                                    {t('Export all')}
                                </a>
                            </Button>
                        )}
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                {t('New field group')}
                            </Link>
                        </Button>
                    </>
                }
            />

            {problemList.length > 0 && (
                <SectionCard
                    title={t('Some field groups could not be read')}
                    className="mb-4"
                    contentClassName="grid gap-1 text-sm"
                >
                    {problemList.map(([source, message]) => (
                        <p key={source}>
                            <Trans
                                text={t('<source>{path}</source>: {message}', {
                                    path: source,
                                    message,
                                })}
                                components={{
                                    source: (text) => (
                                        <span className="font-mono">
                                            {text}
                                        </span>
                                    ),
                                }}
                            />
                        </p>
                    ))}
                </SectionCard>
            )}

            <SectionCard contentClassName="p-0 sm:p-0">
                <DataTable
                    rows={groups}
                    columns={columns}
                    rowKey={(group) => group.key}
                    caption={t('Field groups')}
                    rowActions={(group) => [
                        {
                            label: t('Edit'),
                            href: group.id ? edit.url(group.id) : undefined,
                            hidden: group.id === null,
                        },
                        {
                            label: t('Copy here to edit'),
                            hidden: group.id !== null,
                            onSelect: () => router.post(copy.url(group.key)),
                        },
                        {
                            label: t('Export JSON'),
                            href: exportMethod.url({
                                query: { key: group.key },
                            }),
                            external: true,
                        },
                        {
                            label: t('Delete'),
                            destructive: true,
                            hidden: group.id === null,
                            onSelect: () => {
                                if (
                                    group.id &&
                                    window.confirm(
                                        t(
                                            'Delete "{title}"? Values saved in entries stay.',
                                            { title: group.title },
                                        ),
                                    )
                                ) {
                                    router.delete(destroy.url(group.id));
                                }
                            },
                        },
                    ]}
                    empty={
                        <EmptyState
                            icon="fields"
                            title={t('No field groups yet.')}
                            description={t(
                                'Create one to add fields like a subtitle or a gallery to your entries.',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={create()}>
                                        <Plus />
                                        {t('New field group')}
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />
            </SectionCard>

            <ImportDialog
                open={importing}
                onOpenChange={setImporting}
                maxKb={importMaxKb}
            />
        </>
    );
}

type ImportForm = { file: File | null };

// Uploads a JSON export. Groups with a known key are replaced.
function ImportDialog({
    open,
    onOpenChange,
    maxKb,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    maxKb: number;
}) {
    const form = useForm<ImportForm>({ file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(importMethod.url(), {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{t('Import field groups')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                'Pick a JSON file exported from Verso (up to {size}). Groups with the same key are replaced.',
                                { size: formatBytes(maxKb * 1024) },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <Input
                        type="file"
                        accept="application/json,.json"
                        aria-label={t('JSON file')}
                        onChange={(event) =>
                            form.setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <InputError message={form.errors.file} />
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={!form.data.file || form.processing}
                        >
                            {t('Import')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

FieldGroupsIndex.layout = () => ({
    breadcrumbs: [{ title: t('Custom fields'), href: index() }],
});

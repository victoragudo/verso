import { Head, Link, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ImportStatusBadge } from '@/components/verso/importer/import-status-badge';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SectionCard,
    SiteDate,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatBytes, formatNumber, t } from '@/lib/i18n';
import { index, show, store } from '@/routes/admin/import';
import type { ImportSummary } from '@/types';

type Props = {
    imports: ImportSummary[];
    maxUploadKb: number;
};

const importColumns = (): DataTableColumn<ImportSummary>[] => [
    {
        key: 'file',
        header: t('File'),
        primary: true,
        className: 'max-w-0 w-full',
        cell: (item) => (
            <Link
                href={show.url(item.id)}
                className="block truncate font-medium text-foreground hover:text-brand"
            >
                {item.original_name}
            </Link>
        ),
    },
    {
        key: 'status',
        header: t('Status'),
        cell: (item) => (
            <ImportStatusBadge status={item.status} label={item.status_label} />
        ),
    },
    {
        key: 'user',
        header: t('Started by'),
        className: 'whitespace-nowrap',
        hideOnMobile: true,
        cell: (item) => item.user ?? t('Deleted user'),
    },
    {
        key: 'items',
        header: t('Items'),
        className: 'whitespace-nowrap text-end',
        hideOnMobile: true,
        cell: (item) =>
            item.total === null
                ? formatNumber(item.processed)
                : t('{processed} / {total}', {
                      processed: item.processed,
                      total: item.total,
                  }),
    },
    {
        key: 'uploaded',
        header: t('Uploaded'),
        className: 'whitespace-nowrap text-muted-foreground',
        cell: (item) => <SiteDate iso={item.created_at} />,
    },
];

// Upload a WordPress export file and see the latest import runs.
export default function ImportIndex({ imports, maxUploadKb }: Props) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), { forceFormData: true });
    };

    return (
        <>
            <Head title={t('Import')} />
            <PageHeader
                icon="importer"
                title={t('Import')}
                description={t(
                    'Move posts, pages, categories, tags, authors, images and comments from WordPress.',
                )}
            />

            <SectionCard
                title="WordPress"
                icon="importer"
                description={t(
                    'In WordPress, open Tools > Export, choose All content and download the file. Then upload it here. Running the same file again updates what was imported before, it does not copy it.',
                )}
            >
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-4 sm:flex-row sm:items-start"
                >
                    <div className="grid min-w-0 flex-1 gap-2">
                        <Label htmlFor="file">{t('Export file (.xml)')}</Label>
                        <Input
                            id="file"
                            type="file"
                            accept=".xml,text/xml,application/xml"
                            className="min-h-10 md:min-h-9"
                            onChange={(event) =>
                                form.setData(
                                    'file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('Up to {size}.', {
                                size: formatBytes(maxUploadKb * 1024),
                            })}
                        </p>
                        <InputError message={form.errors.file} />
                    </div>
                    <Button
                        type="submit"
                        disabled={!form.data.file || form.processing}
                        className="min-h-10 sm:mt-[1.375rem] md:min-h-9"
                    >
                        <Upload />
                        {form.processing
                            ? t('Uploading {percent}', {
                                  percent: formatNumber(
                                      (form.progress?.percentage ?? 0) / 100,
                                      { style: 'percent' },
                                  ),
                              })
                            : t('Start import')}
                    </Button>
                </form>
            </SectionCard>

            <h2 className="mt-8 mb-3 text-sm font-semibold text-foreground">
                {t('Recent imports')}
            </h2>
            <DataTable
                rows={imports}
                columns={importColumns()}
                rowKey={(item) => item.id}
                caption={t('Recent imports')}
                empty={
                    <EmptyState
                        icon="importer"
                        title={t('No imports yet.')}
                        description={t('Upload an export file above to start.')}
                    />
                }
            />
        </>
    );
}

ImportIndex.layout = () => ({
    breadcrumbs: [{ title: t('Import'), href: index() }],
});

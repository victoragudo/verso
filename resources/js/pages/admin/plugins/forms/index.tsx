import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    CopyShortcode,
    DataTable,
    EmptyState,
    PageHeader,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatNumber, msg, t } from '@/lib/i18n';

type FormRow = {
    id: number;
    title: string;
    slug: string;
    shortcode: string;
    fields_count: number;
    submissions_count: number;
    new_count: number;
    updated_at: string | null;
    edit_url: string;
    destroy_url: string;
    submissions_url: string;
};

type Props = {
    forms: FormRow[];
    urls: {
        index: string;
        create: string;
        inbox: string;
        settings: string | null;
    };
};

// The list of forms with their message counts.
export default function FormsIndex({ forms, urls }: Props) {
    const columns: DataTableColumn<FormRow>[] = [
        {
            key: 'title',
            header: t('forms::Form'),
            primary: true,
            className: 'max-w-0 w-1/2',
            cell: (row) => (
                <div className="min-w-0">
                    <Link
                        href={row.edit_url}
                        className="block truncate font-medium text-foreground hover:underline"
                    >
                        {row.title}
                    </Link>
                    <div className="truncate font-mono text-xs text-muted-foreground">
                        {row.slug}
                    </div>
                </div>
            ),
        },
        {
            key: 'shortcode',
            header: t('forms::Shortcode'),
            hideOnMobile: true,
            className: 'w-64',
            cell: (row) => (
                <CopyShortcode
                    value={row.shortcode}
                    label={t('forms::Copy the shortcode of {title}', {
                        title: row.title,
                    })}
                    compact
                />
            ),
        },
        {
            key: 'fields',
            header: t('forms::Fields'),
            hideOnMobile: true,
            className: 'tabular-nums text-muted-foreground',
            cell: (row) => formatNumber(row.fields_count),
        },
        {
            key: 'messages',
            header: t('forms::Messages'),
            cell: (row) => (
                <span className="flex items-center gap-2 tabular-nums">
                    <Link
                        href={row.submissions_url}
                        className="hover:underline"
                    >
                        {formatNumber(row.submissions_count)}
                    </Link>
                    {row.new_count > 0 && (
                        <StatusBadge tone="info">
                            {t('forms::{count, number} new', {
                                count: row.new_count,
                            })}
                        </StatusBadge>
                    )}
                </span>
            ),
        },
        {
            key: 'updated',
            header: t('forms::Changed'),
            hideOnMobile: true,
            className: 'text-muted-foreground',
            cell: (row) => <SiteDate iso={row.updated_at} dayOnly />,
        },
    ];

    return (
        <>
            <Head title={t('forms::Forms')} />
            <PageHeader
                icon="forms"
                title={t('forms::Forms')}
                description={t(
                    'forms::Contact forms and other forms for your pages. Messages arrive in the inbox.',
                )}
                actions={
                    <>
                        {urls.settings && (
                            <Button asChild variant="outline">
                                <Link href={urls.settings}>
                                    {t('Settings')}
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={urls.inbox}>{t('forms::Inbox')}</Link>
                        </Button>
                        <Button asChild>
                            <Link href={urls.create}>
                                <Plus className="size-4" />
                                {t('forms::New form')}
                            </Link>
                        </Button>
                    </>
                }
            />
            <DataTable
                rows={forms}
                columns={columns}
                rowKey={(row) => row.id}
                caption={t('forms::Forms')}
                rowLabel={(row) => row.title}
                rowActions={(row) => [
                    { label: t('Edit'), href: row.edit_url },
                    { label: t('forms::Messages'), href: row.submissions_url },
                    {
                        label: t('Delete'),
                        destructive: true,
                        onSelect: () => {
                            if (
                                window.confirm(
                                    t(
                                        'forms::Delete {title}? Its messages and uploaded files are deleted too.',
                                        { title: row.title },
                                    ),
                                )
                            ) {
                                router.delete(row.destroy_url, {
                                    preserveScroll: true,
                                });
                            }
                        },
                    },
                ]}
                empty={
                    <EmptyState
                        icon="forms"
                        title={t('forms::No forms yet.')}
                        description={t(
                            'forms::Build a contact form, then add the Form block to a page.',
                        )}
                        action={
                            <Button asChild>
                                <Link href={urls.create}>
                                    {t('forms::New form')}
                                </Link>
                            </Button>
                        }
                    />
                }
            />
        </>
    );
}

FormsIndex.layout = {
    breadcrumbs: [{ title: msg('forms::Forms'), href: '/admin/forms' }],
};

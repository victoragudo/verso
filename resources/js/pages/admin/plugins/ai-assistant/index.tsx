import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/verso/content/pagination';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SectionCard,
    StatCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { DataTableColumn } from '@/components/verso/ui';
import { formatNumber, t } from '@/lib/i18n';
import type { Paginated } from '@/types';
import type { AiSettingsInfo, AiUsageRow } from './types';

type Props = {
    settings: AiSettingsInfo;
    usage: Paginated<AiUsageRow>;
    totals: { requests: number; input_tokens: number; output_tokens: number };
    days: number;
    urls: { index: string; update: string; forgetKey: string };
};

type SettingsForm = {
    api_key: string;
    model: string;
    max_tokens: number;
    rate_limit: number;
};

const API_KEY_PATTERN = 'sk-ant-...';

function usageColumns(): DataTableColumn<AiUsageRow>[] {
    return [
        {
            key: 'day',
            header: t('ai-assistant::Day'),
            cell: (row) => <span className="tabular-nums">{row.day}</span>,
        },
        {
            key: 'user',
            header: t('ai-assistant::User'),
            primary: true,
            cell: (row) => (
                <div className="min-w-0">
                    <p className="truncate font-medium text-foreground">
                        {row.user}
                    </p>
                    {row.email && (
                        <p className="truncate text-xs text-muted-foreground">
                            {row.email}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'requests',
            header: t('ai-assistant::Requests'),
            className: 'text-end',
            cell: (row) => (
                <span className="tabular-nums">
                    {formatNumber(row.requests)}
                </span>
            ),
        },
        {
            key: 'input',
            header: t('ai-assistant::Tokens in'),
            className: 'text-end',
            cell: (row) => (
                <span className="tabular-nums">
                    {formatNumber(row.input_tokens)}
                </span>
            ),
        },
        {
            key: 'output',
            header: t('ai-assistant::Tokens out'),
            className: 'text-end',
            cell: (row) => (
                <span className="tabular-nums">
                    {formatNumber(row.output_tokens)}
                </span>
            ),
        },
    ];
}

// Settings of the AI assistant plugin and the tokens used per user and day.
export default function AiAssistantIndex({
    settings,
    usage,
    totals,
    days,
    urls,
}: Props) {
    const form = useForm<SettingsForm>({
        api_key: '',
        model: settings.model,
        max_tokens: settings.maxTokens,
        rate_limit: settings.rateLimit,
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, {
            preserveScroll: true,
            onSuccess: () => form.reset('api_key'),
        });
    };

    const forgetKey = () => {
        if (
            window.confirm(
                t(
                    'ai-assistant::Remove the saved API key? The assistant stops working until you add a key again.',
                ),
            )
        ) {
            router.delete(urls.forgetKey, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={t('ai-assistant::AI Assistant')} />
            <PageHeader
                icon="sparkles"
                title={t('ai-assistant::AI Assistant')}
                description={t(
                    'ai-assistant::Writing help in the entry editor. Every suggestion is a preview: nothing is saved until an editor accepts it and saves the entry.',
                )}
            />

            <div className="grid gap-6">
                <div className="auto-fit-grid gap-4 [--grid-min:12rem]">
                    <StatCard
                        label={t(
                            'ai-assistant::{days, plural, one {Requests, last # day} other {Requests, last # days}}',
                            { days },
                        )}
                        value={formatNumber(totals.requests)}
                        icon="sparkles"
                    />
                    <StatCard
                        label={t('ai-assistant::Tokens in')}
                        value={formatNumber(totals.input_tokens)}
                    />
                    <StatCard
                        label={t('ai-assistant::Tokens out')}
                        value={formatNumber(totals.output_tokens)}
                    />
                </div>

                <form onSubmit={submit}>
                    <SectionCard
                        title={t('Settings')}
                        icon="settings"
                        description={t(
                            'ai-assistant::Provider: {provider}. The API key is stored encrypted.',
                            { provider: settings.provider },
                        )}
                        actions={
                            <StatusBadge
                                tone={settings.hasKey ? 'success' : 'warning'}
                            >
                                {settings.hasKey
                                    ? t('ai-assistant::Ready')
                                    : t('ai-assistant::No API key')}
                            </StatusBadge>
                        }
                        contentClassName="grid gap-5 md:grid-cols-2"
                        footer={
                            <Button type="submit" disabled={processing}>
                                {t('Save settings')}
                            </Button>
                        }
                    >
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="api_key">
                                {t('ai-assistant::API key')}
                            </Label>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="off"
                                    value={data.api_key}
                                    onChange={(event) =>
                                        setData('api_key', event.target.value)
                                    }
                                    placeholder={
                                        settings.maskedKey
                                            ? t(
                                                  'ai-assistant::Saved key {key}. Type a new one to replace it.',
                                                  { key: settings.maskedKey },
                                              )
                                            : API_KEY_PATTERN
                                    }
                                />
                                {settings.hasKey && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={forgetKey}
                                    >
                                        {t('ai-assistant::Remove key')}
                                    </Button>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'ai-assistant::Without a key the assistant sends nothing.',
                                )}
                            </p>
                            <InputError message={errors.api_key} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="model">
                                {t('ai-assistant::Model')}
                            </Label>
                            <Input
                                id="model"
                                value={data.model}
                                onChange={(event) =>
                                    setData('model', event.target.value)
                                }
                                placeholder={settings.defaultModel}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('ai-assistant::Default: {model}', {
                                    model: settings.defaultModel,
                                })}
                            </p>
                            <InputError message={errors.model} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="max_tokens">
                                {t('ai-assistant::Max tokens')}
                            </Label>
                            <Input
                                id="max_tokens"
                                type="number"
                                min={1}
                                max={settings.maxTokensCeiling}
                                value={data.max_tokens}
                                onChange={(event) =>
                                    setData(
                                        'max_tokens',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'ai-assistant::Most tokens one answer can use.',
                                )}
                            </p>
                            <InputError message={errors.max_tokens} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="rate_limit">
                                {t('ai-assistant::Requests per user')}
                            </Label>
                            <Input
                                id="rate_limit"
                                type="number"
                                min={1}
                                value={data.rate_limit}
                                onChange={(event) =>
                                    setData(
                                        'rate_limit',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'ai-assistant::{minutes, plural, one {Every minute.} other {Every # minutes.}}',
                                    { minutes: settings.rateLimitMinutes },
                                )}
                            </p>
                            <InputError message={errors.rate_limit} />
                        </div>
                    </SectionCard>
                </form>

                <SectionCard
                    title={t('ai-assistant::Usage')}
                    icon="users"
                    description={t(
                        'ai-assistant::{days, plural, one {Tokens per user and day (UTC), last # day.} other {Tokens per user and day (UTC), last # days.}}',
                        { days },
                    )}
                    contentClassName="grid gap-4 p-0 sm:p-0"
                >
                    <DataTable
                        rows={usage.data}
                        columns={usageColumns()}
                        rowKey={(row) => row.id}
                        caption={t('ai-assistant::AI usage per user and day')}
                        empty={
                            <EmptyState
                                icon="sparkles"
                                title={t('ai-assistant::No requests yet.')}
                                description={t(
                                    'ai-assistant::Usage shows here once editors use the assistant.',
                                )}
                            />
                        }
                    />
                    {usage.last_page > 1 && (
                        <div className="px-4 pb-4 sm:px-5">
                            <Pagination page={usage} />
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

AiAssistantIndex.layout = (props: Props) => ({
    breadcrumbs: [
        { title: t('ai-assistant::AI Assistant'), href: props.urls.index },
    ],
});

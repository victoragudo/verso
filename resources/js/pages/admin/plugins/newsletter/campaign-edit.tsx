import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import { PageHeader, SectionCard } from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';
import type { Block, PluginBlockDefinition } from '@/types';
import { QueueNotice } from '@/components/verso/newsletter/queue-notice';
import type { QueueNoticeData } from '@/components/verso/newsletter/queue-notice';

type Campaign = {
    id: number;
    subject: string;
    preheader: string | null;
    blocks: Block[];
    list_id: number | null;
    track_opens: boolean;
    track_clicks: boolean;
    recipients: number;
};

type Props = {
    queueNotice?: QueueNoticeData | null;
    campaign: Campaign | null;
    defaults: { track_opens: boolean; track_clicks: boolean };
    lists: { id: number; name: string }[];
    pluginBlocks: PluginBlockDefinition[];
    urls: {
        index: string;
        save: string;
        test: string | null;
        send: string | null;
        destroy: string | null;
    };
};

const EVERYONE = 'everyone';
const FORM_ID = 'campaign-form';

// The key gives a fresh form when "create" redirects to "edit", because
// Inertia keeps the same page component.
export default function CampaignEdit(props: Props) {
    return <CampaignEditor key={props.campaign?.id ?? 'new'} {...props} />;
}

// Writes a campaign with the block editor, then sends a test or the real thing.
function CampaignEditor({
    campaign,
    defaults,
    lists,
    pluginBlocks,
    urls,
    queueNotice = null,
}: Props) {
    const form = useForm({
        subject: campaign?.subject ?? '',
        preheader: campaign?.preheader ?? '',
        list_id: campaign?.list_id ? String(campaign.list_id) : EVERYONE,
        track_opens: campaign?.track_opens ?? defaults.track_opens,
        track_clicks: campaign?.track_clicks ?? defaults.track_clicks,
    });
    const { data, setData, errors, processing } = form;
    // Blocks live outside useForm because their attrs are free-form JSON.
    const [blocks, setBlocks] = useState<Block[]>(campaign?.blocks ?? []);
    const test = useForm({ email: '' });
    const blockError = (errors as Record<string, string | undefined>).blocks;

    form.transform((values) => ({
        ...values,
        blocks,
        list_id: values.list_id === EVERYONE ? null : Number(values.list_id),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (campaign) {
            form.put(urls.save, { preserveScroll: true });
        } else {
            form.post(urls.save);
        }
    };

    const [sending, setSending] = useState(false);

    const send = () => {
        if (
            !sending &&
            urls.send &&
            window.confirm(
                t(
                    'newsletter::{count, plural, one {Send “{subject}” to # subscriber now? Save your changes first.} other {Send “{subject}” to # subscribers now? Save your changes first.}}',
                    { subject: data.subject, count: campaign?.recipients ?? 0 },
                ),
            )
        ) {
            router.post(
                urls.send,
                {},
                {
                    onStart: () => setSending(true),
                    onFinish: () => setSending(false),
                },
            );
        }
    };

    const pageTitle = campaign
        ? t('newsletter::Edit campaign')
        : t('newsletter::New campaign');

    return (
        <>
            <Head title={pageTitle} />
            <PageHeader
                icon="newsletter"
                title={pageTitle}
                description={t(
                    'newsletter::Write with the same blocks as entries. Links and images must use full URLs.',
                )}
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={urls.index}>
                                {t('newsletter::Campaigns')}
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            form={FORM_ID}
                            disabled={processing}
                        >
                            {t('Save draft')}
                        </Button>
                    </>
                }
            />
            <QueueNotice notice={queueNotice} />

            <form
                id={FORM_ID}
                onSubmit={submit}
                className="grid items-start gap-6 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]"
            >
                <SectionCard contentClassName="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="subject">
                            {t('newsletter::Subject')}
                        </Label>
                        <Input
                            id="subject"
                            value={data.subject}
                            onChange={(event) =>
                                setData('subject', event.target.value)
                            }
                            placeholder={t(
                                'newsletter::Autumn in the Douro valley',
                            )}
                            required
                        />
                        <InputError message={errors.subject} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="preheader">
                            {t('newsletter::Preview text')}
                        </Label>
                        <Input
                            id="preheader"
                            value={data.preheader}
                            onChange={(event) =>
                                setData('preheader', event.target.value)
                            }
                            placeholder={t(
                                'newsletter::Shown next to the subject in the inbox',
                            )}
                        />
                        <InputError message={errors.preheader} />
                    </div>
                    <div className="grid gap-2">
                        <Label>{t('Content')}</Label>
                        <BlockEditor
                            value={blocks}
                            onChange={setBlocks}
                            pluginBlocks={pluginBlocks}
                        />
                        <InputError message={blockError} />
                    </div>
                </SectionCard>

                <aside className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4">
                    <SectionCard
                        title={t('newsletter::Recipients')}
                        icon="users"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="list_id">
                                {t('newsletter::Send to')}
                            </Label>
                            <Select
                                value={data.list_id}
                                onValueChange={(value) =>
                                    setData('list_id', value)
                                }
                            >
                                <SelectTrigger id="list_id" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={EVERYONE}>
                                        {t('newsletter::All subscribers')}
                                    </SelectItem>
                                    {lists.map((list) => (
                                        <SelectItem
                                            key={list.id}
                                            value={String(list.id)}
                                        >
                                            {list.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {campaign && (
                                <p className="text-xs text-muted-foreground tabular-nums">
                                    {t(
                                        'newsletter::{count, plural, one {# confirmed subscriber right now.} other {# confirmed subscribers right now.}}',
                                        { count: campaign.recipients },
                                    )}
                                </p>
                            )}
                            <InputError message={errors.list_id} />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('newsletter::Tracking')}
                        icon="seo"
                        description={t(
                            'newsletter::Off by default to respect privacy.',
                        )}
                    >
                        <div className="grid gap-2">
                            <label className="flex min-h-10 items-center gap-2.5 text-sm md:min-h-8">
                                <Checkbox
                                    checked={data.track_opens}
                                    onCheckedChange={(value) =>
                                        setData('track_opens', value === true)
                                    }
                                />
                                {t('newsletter::Count opens')}
                            </label>
                            <label className="flex min-h-10 items-center gap-2.5 text-sm md:min-h-8">
                                <Checkbox
                                    checked={data.track_clicks}
                                    onCheckedChange={(value) =>
                                        setData('track_clicks', value === true)
                                    }
                                />
                                {t('newsletter::Count clicks')}
                            </label>
                        </div>
                    </SectionCard>

                    {campaign && urls.test && (
                        <SectionCard
                            title={t('newsletter::Send')}
                            icon="newsletter"
                        >
                            <div className="grid gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="test-email">
                                        {t('newsletter::Test address')}
                                    </Label>
                                    <div className="flex flex-wrap gap-2">
                                        <Input
                                            id="test-email"
                                            type="email"
                                            value={test.data.email}
                                            onChange={(event) =>
                                                test.setData(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="you@example.com"
                                            autoComplete="email"
                                            spellCheck={false}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={
                                                test.processing ||
                                                test.data.email === ''
                                            }
                                            onClick={() =>
                                                urls.test &&
                                                test.post(urls.test, {
                                                    preserveScroll: true,
                                                })
                                            }
                                        >
                                            {t('newsletter::Test')}
                                        </Button>
                                    </div>
                                    <InputError message={test.errors.email} />
                                </div>
                                <Button
                                    type="button"
                                    onClick={send}
                                    disabled={sending}
                                >
                                    {sending && <Spinner aria-hidden="true" />}
                                    {t('newsletter::Send campaign')}
                                </Button>
                                {urls.destroy && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-destructive-foreground"
                                        onClick={() => {
                                            if (
                                                urls.destroy &&
                                                window.confirm(
                                                    t(
                                                        'newsletter::Delete this draft?',
                                                    ),
                                                )
                                            ) {
                                                router.delete(urls.destroy);
                                            }
                                        }}
                                    >
                                        {t('newsletter::Delete draft')}
                                    </Button>
                                )}
                            </div>
                        </SectionCard>
                    )}
                </aside>
            </form>
        </>
    );
}

CampaignEdit.layout = {
    breadcrumbs: [
        {
            title: msg('newsletter::Newsletter'),
            href: '/admin/plugins/newsletter/campaigns',
        },
    ],
};

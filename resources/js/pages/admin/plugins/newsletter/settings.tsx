import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CopyShortcode, PageHeader, SectionCard } from '@/components/verso/ui';
import { QueueNotice } from '@/components/verso/newsletter/queue-notice';
import type { QueueNoticeData } from '@/components/verso/newsletter/queue-notice';
import { msg, t } from '@/lib/i18n';

type Settings = {
    batch_size: number;
    batch_delay: number;
    from_address: string;
    from_name: string;
    track_opens: boolean;
    track_clicks: boolean;
};

type Props = {
    queueNotice?: QueueNoticeData | null;
    settings: Settings;
    defaults: { from_address: string; from_name: string };
    urls: { update: string; campaigns: string };
    signups?: { label: string; shortcode: string; blade: string }[];
};

// Sending options of the newsletter: batches, sender and tracking.
export default function NewsletterSettings({
    settings,
    defaults,
    urls,
    queueNotice = null,
    signups = [],
}: Props) {
    const form = useForm<Settings>(settings);
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('newsletter::Newsletter settings')} />
            <PageHeader
                icon="settings"
                title={t('newsletter::Newsletter settings')}
                description={t(
                    'newsletter::How campaigns are sent and what is measured.',
                )}
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.campaigns}>
                            {t('newsletter::Campaigns')}
                        </Link>
                    </Button>
                }
            />
            <QueueNotice notice={queueNotice} />
            <form onSubmit={submit} className="section-grid">
                <SectionCard
                    title={t('newsletter::Sending')}
                    description={t(
                        'newsletter::Batches keep you under the hourly limit of your mail provider.',
                    )}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="batch_size">
                                {t('newsletter::Emails per batch')}
                            </Label>
                            <Input
                                id="batch_size"
                                type="number"
                                min={1}
                                inputMode="numeric"
                                className="tabular-nums"
                                value={data.batch_size}
                                onChange={(event) =>
                                    setData(
                                        'batch_size',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.batch_size} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="batch_delay">
                                {t('newsletter::Seconds between batches')}
                            </Label>
                            <Input
                                id="batch_delay"
                                type="number"
                                min={0}
                                inputMode="numeric"
                                className="tabular-nums"
                                value={data.batch_delay}
                                onChange={(event) =>
                                    setData(
                                        'batch_delay',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.batch_delay} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="from_name">
                                {t('newsletter::Sender name')}
                            </Label>
                            <Input
                                id="from_name"
                                value={data.from_name}
                                placeholder={defaults.from_name}
                                onChange={(event) =>
                                    setData('from_name', event.target.value)
                                }
                            />
                            <InputError message={errors.from_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="from_address">
                                {t('newsletter::Sender email')}
                            </Label>
                            <Input
                                id="from_address"
                                type="email"
                                value={data.from_address}
                                placeholder={defaults.from_address}
                                autoComplete="off"
                                spellCheck={false}
                                onChange={(event) =>
                                    setData('from_address', event.target.value)
                                }
                            />
                            <InputError message={errors.from_address} />
                        </div>
                    </div>
                </SectionCard>

                {signups.length > 0 && (
                    <SectionCard
                        title={t('newsletter::Signup form')}
                        description={t(
                            'newsletter::Paste a shortcode in any text to show the signup form for a list.',
                        )}
                    >
                        <div className="grid gap-3">
                            {signups.map((signup) => (
                                <div
                                    key={signup.shortcode}
                                    className="grid gap-1"
                                >
                                    <span className="text-sm font-medium">
                                        {signup.label}
                                    </span>
                                    <CopyShortcode
                                        value={signup.shortcode}
                                        blade={signup.blade}
                                        label={t(
                                            'newsletter::Copy the signup shortcode for {list}',
                                            { list: signup.label },
                                        )}
                                    />
                                </div>
                            ))}
                        </div>
                    </SectionCard>
                )}

                <SectionCard
                    title={t('newsletter::Tracking')}
                    description={t(
                        'newsletter::Off by default to respect the privacy of your readers. These are the defaults for new campaigns.',
                    )}
                >
                    <div className="grid gap-3">
                        <label className="flex min-h-10 items-center gap-2.5 text-sm">
                            <Checkbox
                                checked={data.track_opens}
                                onCheckedChange={(value) =>
                                    setData('track_opens', value === true)
                                }
                            />
                            {t('newsletter::Count opens with a tiny image')}
                        </label>
                        <label className="flex min-h-10 items-center gap-2.5 text-sm">
                            <Checkbox
                                checked={data.track_clicks}
                                onCheckedChange={(value) =>
                                    setData('track_clicks', value === true)
                                }
                            />
                            {t('newsletter::Count clicks on links')}
                        </label>
                    </div>
                </SectionCard>

                <div>
                    <Button type="submit" disabled={processing}>
                        {t('Save settings')}
                    </Button>
                </div>
            </form>
        </>
    );
}

NewsletterSettings.layout = {
    breadcrumbs: [
        {
            title: msg('newsletter::Newsletter settings'),
            href: '/admin/plugins/newsletter/settings',
        },
    ],
};

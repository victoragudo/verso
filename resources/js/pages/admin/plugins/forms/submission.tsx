import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { STATUS_TONES, statusLabel } from '@/components/verso/forms/status';
import type {
    SubmissionRow,
    SubmissionStatus,
} from '@/components/verso/forms/types';
import {
    PageHeader,
    SectionCard,
    SiteDate,
    StatusBadge,
} from '@/components/verso/ui';
import { formatBytes, msg, t } from '@/lib/i18n';

type Answer = {
    name: string;
    label: string;
    type: string;
    value: string;
    file: { name: string; size: number; url: string } | null;
};

type Props = {
    submission: SubmissionRow & {
        fields: Answer[];
        page_url: string | null;
        spam_reason: string | null;
        user_agent: string | null;
    };
    urls: { index: string; status: string; destroy: string; erase: string };
};

const MOVES: { status: SubmissionStatus; label: string }[] = [
    { status: 'new', label: msg('forms::Mark as new') },
    { status: 'archived', label: msg('forms::Archive') },
    { status: 'spam', label: msg('forms::Mark as spam') },
];

const SPAM_REASONS: Record<string, string> = {
    honeypot: msg('forms::A hidden field only bots fill in was filled in.'),
    too_fast: msg('forms::The form was sent faster than a person can type.'),
    missing_timer: msg('forms::The form was sent without its time check.'),
    links: msg('forms::The message has many links.'),
    blocked_word: msg('forms::The message has a blocked word.'),
};

// One message: every answer, its status and the actions on it.
export default function SubmissionShow({ submission, urls }: Props) {
    const sender = submission.name || submission.email || t('forms::Someone');
    const visible = submission.fields.filter(
        (field) => field.type !== 'hidden',
    );
    const hidden = submission.fields.filter((field) => field.type === 'hidden');

    const move = (status: SubmissionStatus) =>
        router.put(urls.status, { status }, { preserveScroll: true });

    const remove = () => {
        if (
            window.confirm(
                t('forms::Delete this message from {sender}?', { sender }),
            )
        ) {
            router.delete(urls.destroy);
        }
    };

    return (
        <>
            <Head title={t('forms::Message from {sender}', { sender })} />
            <PageHeader
                icon="forms"
                title={t('forms::Message from {sender}', { sender })}
                description={submission.form.title}
                actions={
                    <>
                        {submission.email && (
                            <Button asChild>
                                <a href={`mailto:${submission.email}`}>
                                    {t('forms::Reply by email')}
                                </a>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={urls.index}>
                                {t('forms::Back to inbox')}
                            </Link>
                        </Button>
                    </>
                }
            />
            <div className="grid gap-6 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]">
                <SectionCard title={t('forms::Answers')}>
                    <dl className="grid gap-4">
                        {visible.map((field) => (
                            <div key={field.name} className="grid gap-1">
                                <dt className="text-sm font-medium text-muted-foreground">
                                    {field.label}
                                </dt>
                                <dd className="text-sm [overflow-wrap:anywhere] whitespace-pre-wrap text-foreground">
                                    {field.file ? (
                                        <a
                                            href={field.file.url}
                                            className="inline-flex items-center gap-1.5 text-info-text underline-offset-4 hover:underline"
                                        >
                                            <Download className="size-4" />
                                            {t('forms::{name} ({size})', {
                                                name: field.file.name,
                                                size: formatBytes(
                                                    field.file.size,
                                                ),
                                            })}
                                        </a>
                                    ) : (
                                        field.value || (
                                            <span className="text-muted-foreground">
                                                {t('forms::No answer')}
                                            </span>
                                        )
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </SectionCard>

                <div className="grid content-start gap-4">
                    <SectionCard title={t('Status')}>
                        <div className="grid gap-3">
                            <div>
                                <StatusBadge
                                    tone={STATUS_TONES[submission.status]}
                                >
                                    {statusLabel(submission.status)}
                                </StatusBadge>
                            </div>
                            {submission.status === 'spam' &&
                                submission.spam_reason && (
                                    <p className="text-sm text-muted-foreground">
                                        {SPAM_REASONS[submission.spam_reason]
                                            ? t(
                                                  SPAM_REASONS[
                                                      submission.spam_reason
                                                  ],
                                              )
                                            : submission.spam_reason}
                                    </p>
                                )}
                            <div className="flex flex-wrap gap-2">
                                {MOVES.filter(
                                    (item) => item.status !== submission.status,
                                ).map((item) => (
                                    <Button
                                        key={item.status}
                                        size="sm"
                                        variant="outline"
                                        onClick={() => move(item.status)}
                                    >
                                        {t(item.label)}
                                    </Button>
                                ))}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="text-danger-text"
                                    onClick={remove}
                                >
                                    {t('Delete')}
                                </Button>
                            </div>
                        </div>
                    </SectionCard>
                    <SectionCard title={t('forms::Details')}>
                        <dl className="grid gap-3 text-sm">
                            <div className="grid gap-0.5">
                                <dt className="text-muted-foreground">
                                    {t('forms::Sent')}
                                </dt>
                                <dd>
                                    <SiteDate iso={submission.created_at} />
                                </dd>
                            </div>
                            {submission.page_url && (
                                <div className="grid gap-0.5">
                                    <dt className="text-muted-foreground">
                                        {t('forms::Page')}
                                    </dt>
                                    <dd className="break-all">
                                        <a
                                            href={submission.page_url}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {submission.page_url}
                                        </a>
                                    </dd>
                                </div>
                            )}
                            {hidden.map((field) => (
                                <div key={field.name} className="grid gap-0.5">
                                    <dt className="text-muted-foreground">
                                        {field.label}
                                    </dt>
                                    <dd>{field.value}</dd>
                                </div>
                            ))}
                            {submission.user_agent && (
                                <div className="grid gap-0.5">
                                    <dt className="text-muted-foreground">
                                        {t('forms::Browser')}
                                    </dt>
                                    <dd className="text-xs break-words text-muted-foreground">
                                        {submission.user_agent}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

SubmissionShow.layout = {
    breadcrumbs: [
        { title: msg('forms::Forms'), href: '/admin/forms' },
        { title: msg('forms::Inbox'), href: '/admin/forms/submissions' },
    ],
};

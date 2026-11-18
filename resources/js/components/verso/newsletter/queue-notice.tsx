import { WarningIcon } from '@/components/verso/icons';
import { SiteDate } from '@/components/verso/ui';
import { t, Trans } from '@/lib/i18n';

export type QueueNoticeData = {
    title: string;
    message: string;
    lastSeen: string | null;
};

// A calm amber notice that tells the admin campaigns will not go out without a queue worker.
export function QueueNotice({ notice }: { notice: QueueNoticeData | null }) {
    if (!notice) {
        return null;
    }

    return (
        <div
            role="status"
            className="mb-6 flex gap-3 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-warning-text"
        >
            <WarningIcon className="mt-0.5 shrink-0" />
            <div className="grid gap-1">
                <p className="font-medium">{notice.title}</p>
                <p>{notice.message}</p>
                <p className="text-xs">
                    {notice.lastSeen ? (
                        <Trans
                            text={t(
                                'newsletter::Last worker heartbeat: <date></date>',
                            )}
                            components={{
                                date: () => <SiteDate iso={notice.lastSeen} />,
                            }}
                        />
                    ) : (
                        t('newsletter::Last worker heartbeat: never')
                    )}
                </p>
            </div>
        </div>
    );
}

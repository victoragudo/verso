import { Button } from '@/components/ui/button';
import { draftTime } from '@/lib/easy/page-drafts';
import { t } from '@/lib/i18n';
import { TOUCH_TARGET } from './touch';

type Props = {
    savedAt: string;
    onKeep: () => void;
    onDiscard: () => void;
};

// Asks what to do with unsaved changes from an earlier visit: keep editing them or throw them away.
export function DraftBanner({ savedAt, onKeep, onDiscard }: Props) {
    const time = draftTime(savedAt);

    return (
        <div
            role="region"
            aria-label={t('Unsaved changes')}
            className="flex shrink-0 flex-col gap-3 border-b border-warning-text/20 bg-warning-soft px-4 py-3 text-warning-text sm:flex-row sm:items-center sm:justify-between"
            data-test="draft-banner"
        >
            <p className="text-sm font-medium">
                {time
                    ? t('You have unsaved changes from {time}.', { time })
                    : t('You have unsaved changes from an earlier visit.')}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button type="button" className={TOUCH_TARGET} onClick={onKeep}>
                    {t('Keep editing them')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className={TOUCH_TARGET}
                    onClick={onDiscard}
                >
                    {t('Throw them away')}
                </Button>
            </div>
        </div>
    );
}

import { t } from '@/lib/i18n';
import { Button } from '@/components/ui/button';
import { VersoIcon } from '@/components/verso/icons';
import type { ContrastWarning } from '@/lib/easy/contrast';

type Props = {
    warnings: ContrastWarning[];
    onFix: (warning: ContrastWarning) => void;
};

// Plain warnings for colors that are hard to read, each with a one click fix. Screen readers hear changes politely.
export function ContrastWarnings({ warnings, onFix }: Props) {
    return (
        <div aria-live="polite" data-test="contrast-warnings">
            {warnings.length === 0 ? (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <span
                        aria-hidden="true"
                        className="size-2 rounded-full bg-success-text"
                    />
                    {t('All colors are easy to read.')}
                </p>
            ) : (
                <ul className="grid gap-2">
                    {warnings.map((warning) => (
                        <li
                            key={warning.key}
                            className="flex flex-wrap items-start gap-3 rounded-md border border-warning-text/20 bg-warning-soft p-3 text-sm text-warning-text"
                        >
                            <VersoIcon
                                name="warning"
                                size={18}
                                className="mt-px shrink-0"
                            />
                            <span className="min-w-0 flex-1 basis-48">
                                {warning.message}
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="min-h-11 pointer-fine:md:min-h-8"
                                onClick={() => onFix(warning)}
                            >
                                {t('Fix it')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

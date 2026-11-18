import { msg, t } from '@/lib/i18n';
import type { CheckResult, CheckStatus } from '@/lib/seo-analysis';
import { cn } from '@/lib/utils';

const STATUS_LABELS: Record<CheckStatus, string> = {
    problem: msg('Problem'),
    ok: msg('Could be better'),
    good: msg('Good'),
};

const STATUS_DOTS: Record<CheckStatus, string> = {
    problem: 'bg-danger-text',
    ok: 'bg-warning-text',
    good: 'bg-success-text',
};

// One list of check results, problems first, with a colored dot and plain words.
export function AnalysisList({
    checks,
    empty,
}: {
    checks: CheckResult[];
    empty: string;
}) {
    if (checks.length === 0) {
        return <p className="text-xs text-muted-foreground">{empty}</p>;
    }

    return (
        <ul className="grid gap-2" role="list">
            {checks.map((check) => (
                <li
                    key={check.id}
                    className="flex min-w-0 items-start gap-2 text-sm"
                    data-check={check.id}
                    data-status={check.status}
                >
                    <span
                        className={cn(
                            'mt-1.5 size-2 shrink-0 rounded-full',
                            STATUS_DOTS[check.status],
                        )}
                        aria-hidden="true"
                    />
                    <span className="min-w-0 break-words">
                        <span className="sr-only">
                            {t('{status}:', {
                                status: t(STATUS_LABELS[check.status]),
                            })}{' '}
                        </span>
                        <span className="font-medium text-foreground">
                            {t('{label}:', { label: check.label })}
                        </span>{' '}
                        <span className="text-muted-foreground">
                            {check.message}
                        </span>
                    </span>
                </li>
            ))}
        </ul>
    );
}

// "3 problems, 2 to improve" for a closed section header.
export function countSummary(checks: CheckResult[]): string {
    const problems = checks.filter(
        (check) => check.status === 'problem',
    ).length;
    const improve = checks.filter((check) => check.status === 'ok').length;

    if (problems === 0 && improve === 0) {
        return t('All good');
    }

    if (improve === 0) {
        return t('{problems, plural, one {# problem} other {# problems}}', {
            problems,
        });
    }

    if (problems === 0) {
        return t('{improve} to improve', { improve });
    }

    return t(
        '{problems, plural, one {# problem} other {# problems}}, {improve} to improve',
        { problems, improve },
    );
}

import { t } from '@/lib/i18n';
// Data the SEO admin pages get from the server.

export type SeoSeverity = 'critical' | 'warning' | 'notice';

export type SeoFix = {
    label: string;
    kind: 'link' | 'action';
    url: string | null;
};

export type SeoCheckRow = {
    key: string;
    label: string;
    description: string;
    severity: SeoSeverity;
    severityLabel: string;
    weight: number;
    affected: number;
    total: number;
    pending: boolean;
    summary: string;
    hasItems: boolean;
    fix: SeoFix | null;
    href: string;
    fixUrl: string | null;
};

export type SeoIssueItem = {
    key: string;
    title: string;
    detail: string;
    editUrl: string | null;
    viewUrl: string | null;
    type: string | null;
    typeLabel: string | null;
};

export type LinkCheckState = {
    status: 'idle' | 'running' | 'done';
    started_at: string | null;
    finished_at: string | null;
    updated_at: string | null;
    total: number;
    checked: number;
    running: boolean;
};

export type NotFoundSuggestion = {
    path: string;
    title: string;
    similarity: number;
};

export type NotFoundRow = {
    id: number;
    path: string;
    hits: number;
    referrer_host: string | null;
    first_seen_at: string | null;
    last_seen_at: string | null;
    redirect: string | null;
    suggestions: NotFoundSuggestion[];
};

export const SEVERITY_TONES = {
    critical: 'danger',
    warning: 'warning',
    notice: 'info',
} as const;

// "12 of 40 pages", or a short sentence for site wide checks.
export function affectedText(
    check: Pick<SeoCheckRow, 'affected' | 'total' | 'summary' | 'pending'>,
): string {
    if (check.pending || check.summary) {
        return check.summary;
    }

    if (check.affected === 0) {
        return t('Nothing to fix.');
    }

    return check.total > 1
        ? t('{affected, number} of {total, number}', {
              affected: check.affected,
              total: check.total,
          })
        : t('{affected, number} found', { affected: check.affected });
}

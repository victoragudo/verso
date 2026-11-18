import { t } from '@/lib/i18n';

// The launch checklist on the dashboard: rows, progress and the next step.

export type LaunchCheckItem = {
    key: string;
    label: string;
    description: string;
    href: string;
    action: string;
    method: 'get' | 'post';
    done: boolean;
};

export type LaunchChecklistData = {
    items: LaunchCheckItem[];
    done: number;
    total: number;
    dismissUrl: string;
};

// "5 of 8 done", or a cheer when everything is done.
export function progressLabel(done: number, total: number): string {
    if (total > 0 && done >= total) {
        return t('All done. Your site is ready.');
    }

    return t('{done, number} of {total, number} done', { done, total });
}

// The first step that still needs doing, or null.
export function nextStep(items: LaunchCheckItem[]): LaunchCheckItem | null {
    return items.find((item) => !item.done) ?? null;
}

export function isComplete(data: Pick<LaunchChecklistData, 'done' | 'total'>) {
    return data.total > 0 && data.done >= data.total;
}

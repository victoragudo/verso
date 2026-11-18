import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { msg, t } from '@/lib/i18n';

type Change = {
    id: string;
    type: string;
    change: 'added' | 'removed' | 'changed' | 'moved';
    text: string;
};

type Comparison = {
    before: string;
    after: string;
    title: { before: string; after: string };
    changes: Change[];
};

// Plain words and marks of each kind of change. The mark is a line style too, so it never relies on color alone.
const CHANGE_TEXT: Record<Change['change'], string> = {
    added: msg('Added'),
    removed: msg('Removed'),
    changed: msg('Changed'),
    moved: msg('Moved'),
};

const MARKS: Record<Change['change'], string> = {
    added: '3px solid #15803d',
    removed: '3px dashed #b91c1c',
    changed: '3px dotted #b45309',
    moved: '3px double #2563eb',
};

// The page of one side of the comparison, with the changed blocks outlined and labelled.
export function comparisonDocument(
    html: string,
    changes: Change[],
    side: 'before' | 'after',
): string {
    const shown = changes.filter((change) =>
        side === 'before'
            ? change.change !== 'added'
            : change.change !== 'removed',
    );
    const rules = shown
        .map(
            (change) =>
                `[data-verso-block="${CSS.escape(change.id)}"]{outline:${MARKS[change.change]};outline-offset:4px;position:relative}` +
                `[data-verso-block="${CSS.escape(change.id)}"]::before{content:"${t(CHANGE_TEXT[change.change]).replace(/"/g, '')}";position:absolute;inset-block-start:-1.4rem;inset-inline-end:0;font:600 11px/1.6 system-ui,sans-serif;background:#111;color:#fff;padding:0 6px;border-radius:4px}`,
        )
        .join('');

    return `<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font:16px/1.6 system-ui,sans-serif;margin:2rem 1.5rem;color:#1f2937}img{max-width:100%;height:auto}${rules}</style></head><body>${html}</body></html>`;
}

type Props = {
    url: string | null;
    onClose: () => void;
};

// Shows a revision and the saved entry side by side, with the blocks that were added, removed, changed or moved.
export function RevisionCompareDialog({ url, onClose }: Props) {
    const [data, setData] = useState<Comparison | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (url === null) {
            return;
        }

        let alive = true;
        setData(null);
        setFailed(false);
        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<Comparison>)
                    : Promise.reject(new Error(String(response.status))),
            )
            .then((comparison) => alive && setData(comparison))
            .catch(() => alive && setFailed(true));

        return () => {
            alive = false;
        };
    }, [url]);

    const counts = (data?.changes ?? []).reduce<Record<string, number>>(
        (total, change) => ({
            ...total,
            [change.change]: (total[change.change] ?? 0) + 1,
        }),
        {},
    );

    return (
        <Dialog open={url !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="flex max-h-[92vh] flex-col sm:max-w-[min(96vw,110rem)]">
                <DialogHeader>
                    <DialogTitle>
                        {t('Compare with the saved version')}
                    </DialogTitle>
                    <DialogDescription>
                        {data
                            ? t(
                                  '{added} added, {removed} removed, {changed} changed, {moved} moved.',
                                  {
                                      added: counts.added ?? 0,
                                      removed: counts.removed ?? 0,
                                      changed: counts.changed ?? 0,
                                      moved: counts.moved ?? 0,
                                  },
                              )
                            : t(
                                  'The revision on the left, the saved entry on the right.',
                              )}
                    </DialogDescription>
                </DialogHeader>
                {failed && (
                    <p role="alert" className="text-sm text-destructive">
                        {t('Could not load the comparison. Try again.')}
                    </p>
                )}
                {!data && !failed && <Spinner />}
                {data && (
                    <div
                        className="@container/compare grid min-h-0 flex-1 gap-4 overflow-auto"
                        data-test="revision-compare"
                    >
                        <div className="grid gap-4 @4xl/compare:grid-cols-2">
                            {(['before', 'after'] as const).map((side) => (
                                <section
                                    key={side}
                                    className="grid min-w-0 gap-2"
                                    aria-label={
                                        side === 'before'
                                            ? t('Revision')
                                            : t('Saved version')
                                    }
                                >
                                    <h3 className="text-sm font-semibold">
                                        {side === 'before'
                                            ? t('Revision: {title}', {
                                                  title: data.title.before,
                                              })
                                            : t('Saved: {title}', {
                                                  title: data.title.after,
                                              })}
                                    </h3>
                                    <iframe
                                        title={
                                            side === 'before'
                                                ? t('The revision')
                                                : t('The saved version')
                                        }
                                        sandbox=""
                                        srcDoc={comparisonDocument(
                                            data[side],
                                            data.changes,
                                            side,
                                        )}
                                        className="h-[60vh] w-full rounded-md border bg-white"
                                    />
                                </section>
                            ))}
                        </div>
                        {data.changes.length > 0 && (
                            <ul
                                className="grid gap-1 text-sm"
                                aria-label={t('Changes')}
                            >
                                {data.changes.map((change) => (
                                    <li
                                        key={`${change.change}-${change.id}`}
                                        className="flex gap-2"
                                    >
                                        <span className="w-20 shrink-0 font-medium">
                                            {t(CHANGE_TEXT[change.change])}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {change.type}
                                        </span>
                                        <span className="min-w-0 truncate">
                                            {change.text}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

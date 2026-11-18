import { useEffect, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { draftTime } from '@/lib/easy/page-drafts';
import {
    drawPage,
    loadVersions,
    PageEditorError,
} from '@/lib/easy/page-requests';
import type { PageVersion } from '@/lib/easy/page-requests';
import { formatList, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { TOUCH_TARGET } from './touch';

type Props = {
    open: boolean;
    versionsUrl: string;
    frameUrl: string;
    onRestore: (version: PageVersion) => void;
    onClose: () => void;
};

type Loaded =
    | { state: 'loading' }
    | { state: 'ready'; versions: PageVersion[] }
    | { state: 'failed'; message: string };

// The name of the preview frame, with the time of the version when it is known.
function previewTitle(version: PageVersion): string {
    const time = version.savedAt ? draftTime(version.savedAt) : '';

    return time
        ? t('Preview of the version from {time}', { time })
        : t('Preview of an earlier version');
}

// "Restore this page": the earlier versions of the page, a preview of the picked one and a Restore button.
// Restoring only changes the editor (one undo step); the page changes for visitors after Save.
export function RestoreDialog({
    open,
    versionsUrl,
    frameUrl,
    onRestore,
    onClose,
}: Props) {
    const dialog = useRef<HTMLDialogElement>(null);
    const id = useId();
    const [loaded, setLoaded] = useState<Loaded>({ state: 'loading' });
    const [picked, setPicked] = useState<number | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const versions = loaded.state === 'ready' ? loaded.versions : [];
    const version = versions.find((item) => item.id === picked) ?? null;

    useEffect(() => {
        const element = dialog.current;

        if (!element) {
            return;
        }

        if (open && !element.open) {
            element.showModal();
        } else if (!open && element.open) {
            element.close();
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        setLoaded({ state: 'loading' });
        setPicked(null);
        loadVersions(versionsUrl, controller.signal)
            .then((list) => {
                setLoaded({ state: 'ready', versions: list });
                setPicked(list[0]?.id ?? null);
            })
            .catch((error: unknown) => {
                if (error instanceof PageEditorError) {
                    setLoaded({ state: 'failed', message: error.message });
                }
            });

        return () => controller.abort();
    }, [open, versionsUrl]);

    useEffect(() => {
        if (!version) {
            setPreview(null);

            return;
        }

        const controller = new AbortController();
        setPreviewError(null);
        drawPage(frameUrl, version.blocks, controller.signal)
            .then(setPreview)
            .catch((error: unknown) => {
                if (error instanceof PageEditorError) {
                    setPreviewError(error.message);
                }
            });

        return () => controller.abort();
    }, [version, frameUrl]);

    return (
        <dialog
            ref={dialog}
            aria-labelledby={`${id}-title`}
            onClose={onClose}
            className="verso-dialog m-auto h-[min(52rem,calc(100dvh-2rem))] w-[min(90rem,calc(100%-2rem))] flex-col rounded-lg border bg-background p-0 text-foreground shadow-lg open:flex"
            data-test="restore-dialog"
        >
            <div className="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
                <div className="min-w-0">
                    <h2 id={`${id}-title`} className="text-lg font-semibold">
                        {t('Restore this page')}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Pick an earlier version to see it. Restoring puts it in the editor, and you can undo it. Visitors see it after you save.',
                        )}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    className={TOUCH_TARGET}
                    onClick={() => dialog.current?.close()}
                >
                    {t('Close')}
                </Button>
            </div>

            <div className="@container grid min-h-0 flex-1 grid-rows-[auto_minmax(16rem,1fr)] gap-0 md:grid-cols-[minmax(16rem,22rem)_minmax(0,1fr)] md:grid-rows-1">
                <div className="max-h-56 min-h-0 overflow-y-auto border-b p-3 md:max-h-none md:border-e md:border-b-0">
                    {loaded.state === 'loading' && (
                        <p
                            role="status"
                            className="p-2 text-sm text-muted-foreground"
                        >
                            {t('Looking for earlier versions…')}
                        </p>
                    )}
                    {loaded.state === 'failed' && (
                        <p
                            role="alert"
                            className="p-2 text-sm text-danger-text"
                        >
                            {loaded.message}
                        </p>
                    )}
                    {loaded.state === 'ready' && versions.length === 0 && (
                        <p
                            role="status"
                            className="p-2 text-sm text-muted-foreground"
                        >
                            {t(
                                'There are no earlier versions yet. Each time you save, the version before is kept here.',
                            )}
                        </p>
                    )}
                    {versions.length > 0 && (
                        <fieldset>
                            <legend className="sr-only">
                                {t('Earlier versions')}
                            </legend>
                            <ul className="grid gap-1.5">
                                {versions.map((item) => (
                                    <li key={item.id}>
                                        <label
                                            className={cn(
                                                'flex min-h-11 cursor-pointer items-start gap-3 rounded-md border px-3 py-2.5 text-sm has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50',
                                                picked === item.id
                                                    ? 'border-brand bg-accent'
                                                    : 'border-transparent hover:bg-accent',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name={`${id}-version`}
                                                value={item.id}
                                                checked={picked === item.id}
                                                onChange={() =>
                                                    setPicked(item.id)
                                                }
                                                className="mt-1 size-4 accent-[var(--color-brand)]"
                                            />
                                            <span className="grid min-w-0 gap-0.5">
                                                <span className="font-medium">
                                                    {(item.savedAt &&
                                                        draftTime(
                                                            item.savedAt,
                                                        )) ||
                                                        t('Earlier')}
                                                </span>
                                                <span className="truncate text-muted-foreground">
                                                    {formatList(
                                                        [
                                                            item.author,
                                                            item.summary,
                                                        ].filter(
                                                            (
                                                                part,
                                                            ): part is string =>
                                                                Boolean(part),
                                                        ),
                                                        'unit',
                                                    )}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        </fieldset>
                    )}
                </div>

                <div className="flex min-h-0 flex-col gap-2 bg-muted/40 p-3">
                    {previewError && (
                        <p role="alert" className="text-sm text-danger-text">
                            {previewError}
                        </p>
                    )}
                    {version && preview !== null ? (
                        <iframe
                            title={previewTitle(version)}
                            srcDoc={preview}
                            sandbox="allow-same-origin"
                            className="min-h-0 w-full flex-1 rounded-md border bg-white"
                            data-test="restore-preview"
                        />
                    ) : (
                        <div className="grid flex-1 place-items-center text-sm text-muted-foreground">
                            {version ? t('Drawing the preview…') : ''}
                        </div>
                    )}
                </div>
            </div>

            <div className="flex flex-col-reverse gap-2 border-t px-5 py-3 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    autoFocus
                    className={TOUCH_TARGET}
                    onClick={() => dialog.current?.close()}
                >
                    {t('Cancel')}
                </Button>
                <Button
                    type="button"
                    className={TOUCH_TARGET}
                    disabled={!version}
                    onClick={() => {
                        if (version) {
                            dialog.current?.close();
                            onRestore(version);
                        }
                    }}
                    data-test="restore-version"
                >
                    {t('Restore this version')}
                </Button>
            </div>
        </dialog>
    );
}

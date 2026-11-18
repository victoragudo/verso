import { t } from '@/lib/i18n';
import { useId, useState } from 'react';
import type { DragEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { TOUCH_TARGET } from './touch';

export type LogoInfo = { id: number | null; url: string; filename: string };

type Props = {
    logo: LogoInfo | null;
    busy: boolean;
    status: string;
    error?: string;
    accept: string;
    onFile: (file: File) => void;
    onRemove: () => void;
};

// Drop a logo here or pick one with the file button. Shows the logo and a short status line.
export function LogoDrop({
    logo,
    busy,
    status,
    error,
    accept,
    onFile,
    onRemove,
}: Props) {
    const id = useId();
    const [dragging, setDragging] = useState(false);

    const drop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        const file = event.dataTransfer.files[0];

        if (file) {
            onFile(file);
        }
    };

    return (
        <div className="@container grid gap-3">
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={drop}
                data-test="logo-drop"
                className={cn(
                    'flex flex-col items-center gap-3 rounded-lg border border-dashed p-4 text-center transition-colors @md:flex-row @md:text-start',
                    dragging && 'border-brand bg-brand-soft',
                )}
            >
                <span className="grid size-20 shrink-0 place-items-center overflow-hidden rounded-md border bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:12px_12px]">
                    {logo ? (
                        <img
                            src={logo.url}
                            alt={t('Your logo')}
                            className="max-h-full max-w-full object-contain"
                        />
                    ) : (
                        <span className="text-xs text-muted-foreground">
                            {t('No logo')}
                        </span>
                    )}
                </span>
                <span className="grid w-full min-w-0 flex-1 gap-1 @md:w-auto">
                    <label htmlFor={id} className="text-sm font-medium">
                        {logo ? t('Change your logo') : t('Add your logo')}
                    </label>
                    <span
                        id={`${id}-hint`}
                        className="text-xs text-muted-foreground"
                    >
                        {t(
                            'Drop a picture here, or choose one. PNG or JPG work best. We take colors from it.',
                        )}
                    </span>
                    <span className="mt-1 flex flex-wrap items-center justify-center gap-2 @md:justify-start">
                        <input
                            id={id}
                            type="file"
                            accept={accept}
                            disabled={busy}
                            aria-describedby={`${id}-hint ${id}-status`}
                            className="w-full min-w-0 text-sm text-muted-foreground file:me-3 file:min-h-11 file:cursor-pointer file:rounded-md file:border file:border-input file:bg-background file:px-3 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent pointer-fine:md:file:min-h-9"
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                event.target.value = '';

                                if (file) {
                                    onFile(file);
                                }
                            }}
                        />
                        {busy && <Spinner />}
                        {logo && (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={busy}
                                className={TOUCH_TARGET}
                                onClick={onRemove}
                            >
                                {t('Remove logo')}
                            </Button>
                        )}
                    </span>
                </span>
            </div>
            <p
                id={`${id}-status`}
                role="status"
                className="text-sm text-muted-foreground empty:hidden"
            >
                {status}
            </p>
            <InputError message={error} />
        </div>
    );
}

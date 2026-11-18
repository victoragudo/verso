import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import type { Block } from '@/types';
import { askAssistant } from '../ai-api';
import type { AltTextResult } from '../types';
import {
    BusyBar,
    HelperDialog,
    HelperError,
    useHelperTask,
} from './helper-parts';
import { imagesWithoutAlt, withAltTexts } from './page-text';
import type { ImageSpot } from './page-text';
import type { PageHelperProps } from './types';

type Props = {
    config: PageHelperProps;
    context: PageEditorContext;
    section: Block;
    onClose: () => void;
};

// A suggested description and whether the person keeps it.
type Described = ImageSpot & { alt: string; keep: boolean };

// "Describe images": a short description for each picture of a section that has none, accepted one by one.
export function ImageDescriber({ config, context, section, onClose }: Props) {
    const spots = imagesWithoutAlt(section).slice(0, config.maxImages);
    const { busy, error, run } = useHelperTask();
    const [described, setDescribed] = useState<Described[]>([]);
    const [done, setDone] = useState(false);
    const kept = described.filter((item) => item.keep && item.alt.trim());

    const describe = async () => {
        setDescribed([]);
        setDone(false);

        await run(async (signal) => {
            for (const spot of spots) {
                const result = await askAssistant<AltTextResult>(
                    config.endpoints.altText,
                    { media_id: spot.mediaId },
                    signal,
                );
                setDescribed((current) => [
                    ...current,
                    { ...spot, alt: result.alt, keep: true },
                ]);
            }
        });

        setDone(true);
    };

    const change = (key: string, update: Partial<Described>) =>
        setDescribed((current) =>
            current.map((item) =>
                item.key === key ? { ...item, ...update } : item,
            ),
        );

    const accept = () => {
        onClose();
        context.change(
            withAltTexts(
                context.blocks,
                kept.map((item) => ({ ...item, alt: item.alt.trim() })),
            ),
            t(
                'ai-assistant::{count, plural, one {# picture description added} other {# picture descriptions added}}',
                { count: kept.length },
            ),
        );
    };

    return (
        <HelperDialog
            title={t('ai-assistant::Describe images')}
            description={t(
                'ai-assistant::Short descriptions help people who cannot see the pictures, and search engines. Keep the ones you like.',
            )}
            busy={busy}
            status={
                done && described.length > 0
                    ? t(
                          'ai-assistant::{count, plural, one {# description is ready. Check it, then accept or discard.} other {# descriptions are ready. Check them, then accept or discard.}}',
                          { count: described.length },
                      )
                    : ''
            }
            onClose={onClose}
            footer={
                <>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11"
                        onClick={onClose}
                    >
                        {t('ai-assistant::Discard')}
                    </Button>
                    {described.length > 0 && !busy ? (
                        <Button
                            type="button"
                            className="h-11"
                            disabled={kept.length === 0}
                            onClick={accept}
                        >
                            {t(
                                'ai-assistant::{count, plural, one {Use # description} other {Use # descriptions}}',
                                { count: kept.length },
                            )}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className="h-11"
                            disabled={busy || spots.length === 0}
                            onClick={describe}
                        >
                            {t(
                                'ai-assistant::{count, plural, one {Describe # picture} other {Describe # pictures}}',
                                { count: spots.length },
                            )}
                        </Button>
                    )}
                </>
            }
        >
            {spots.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'ai-assistant::Every picture of this section has a description.',
                    )}
                </p>
            )}
            {busy && (
                <BusyBar
                    label={t(
                        'ai-assistant::Describing picture {current, number} of {total, number}…',
                        {
                            current: Math.min(
                                described.length + 1,
                                spots.length,
                            ),
                            total: spots.length,
                        },
                    )}
                    value={described.length}
                    max={spots.length}
                />
            )}
            <HelperError message={error} />
            {described.length > 0 && (
                <ul
                    className="flex flex-col gap-3"
                    aria-label={t('ai-assistant::Descriptions')}
                >
                    {described.map((item, index) => {
                        const id = `ai-alt-${item.key}`;

                        return (
                            <li
                                key={item.key}
                                className="@container rounded-lg border bg-card p-3"
                            >
                                <div className="flex flex-col gap-3 @md:flex-row">
                                    {item.src && (
                                        <img
                                            src={item.src}
                                            alt=""
                                            className="aspect-[4/3] w-full shrink-0 rounded-md bg-muted object-cover @md:w-32"
                                        />
                                    )}
                                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                                        <label
                                            htmlFor={id}
                                            className="text-xs font-medium text-muted-foreground"
                                        >
                                            {t(
                                                'ai-assistant::Description of picture {number, number}',
                                                { number: index + 1 },
                                            )}
                                        </label>
                                        <Textarea
                                            id={id}
                                            rows={2}
                                            value={item.alt}
                                            disabled={busy}
                                            onChange={(event) =>
                                                change(item.key, {
                                                    alt: event.target.value,
                                                })
                                            }
                                        />
                                        <label className="flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={item.keep}
                                                disabled={busy}
                                                onChange={(event) =>
                                                    change(item.key, {
                                                        keep: event.target
                                                            .checked,
                                                    })
                                                }
                                                className="size-4 accent-blue-600"
                                            />
                                            {t(
                                                'ai-assistant::Use this description',
                                            )}
                                        </label>
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </HelperDialog>
    );
}

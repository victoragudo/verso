import { Monitor, Smartphone, Tablet, TriangleAlert } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { OptionSelect } from '@/components/verso/ui';
import { useElementSize } from '@/hooks/use-element-size';
import { formatPercent, msg, t } from '@/lib/i18n';
import type { SitePreviewWidths } from '@/types';
import { DEFAULT_DESKTOP_MIN_WIDTH, previewFit } from './preview-scale';
import type { PreviewTarget } from './preview-target';

type Device = keyof SitePreviewWidths;

const DEVICES: { value: Device; label: string; Icon: typeof Monitor }[] = [
    { value: 'desktop', label: msg('Computer'), Icon: Monitor },
    { value: 'tablet', label: msg('Tablet'), Icon: Tablet },
    { value: 'phone', label: msg('Phone'), Icon: Smartphone },
];

// Space around the frame inside the pane, in pixels (p-3 on both sides).
const FRAME_GUTTER = 24;

// Scales under this show their percent next to the device switch.
const FULL_SCALE = 0.995;

type TargetPicker = {
    targets: PreviewTarget[];
    value: string;
    onChange: (value: string) => void;
    // False when the picked page does not use what is being edited.
    usesLayout: boolean | null;
    // A page that does use it, offered in the notice.
    suggestion: PreviewTarget | null;
};

type Props = {
    src: string;
    title: string;
    widths: SitePreviewWidths;
    desktopMinWidth?: number;
    target?: TargetPicker;
    testId?: string;
};

// The live preview: one toolbar row (page and screen size), then a frame that fills the pane.
// A computer page wider than the pane is shrunk to fit, and the toolbar says by how much.
export function PreviewPane({
    src,
    title,
    widths,
    desktopMinWidth = DEFAULT_DESKTOP_MIN_WIDTH,
    target,
    testId,
}: Props) {
    const id = useId();
    const [device, setDevice] = useState<Device>('desktop');
    const [host, size] = useElementSize<HTMLDivElement>();
    const paneWidth = Math.max(0, size.width - FRAME_GUTTER);
    const paneHeight = Math.max(0, size.height - FRAME_GUTTER);
    const { frameWidth, scale } = previewFit({
        paneWidth,
        deviceWidth: widths[device],
        desktopMinWidth,
    });
    const scaled = scale < FULL_SCALE;

    return (
        <section
            aria-label={t('Preview')}
            className="@container/preview flex min-h-0 min-w-0 flex-1 flex-col bg-muted/40"
            data-test="preview-pane"
        >
            <div
                role="toolbar"
                aria-label={t('Preview options')}
                className="flex min-h-12 shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b bg-card px-3 py-2"
            >
                {target && (
                    <div className="flex min-w-0 flex-1 items-center gap-2">
                        <Label
                            htmlFor={`${id}-target`}
                            className="shrink-0 text-xs text-muted-foreground"
                        >
                            {t('Preview on')}
                        </Label>
                        <OptionSelect
                            id={`${id}-target`}
                            size="sm"
                            value={target.value}
                            onValueChange={target.onChange}
                            options={target.targets}
                            searchPlaceholder={t('Search pages')}
                            className="w-full max-w-72 min-w-0"
                        />
                    </div>
                )}
                <div className="ms-auto flex shrink-0 items-center gap-2">
                    {scaled && (
                        <span
                            className="text-xs text-muted-foreground tabular-nums"
                            data-test="preview-scale"
                        >
                            {t('Shown at {percent}', {
                                percent: formatPercent(scale, 0),
                            })}
                        </span>
                    )}
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={device}
                        onValueChange={(value) =>
                            value && setDevice(value as Device)
                        }
                        aria-label={t('Preview size')}
                    >
                        {DEVICES.map(({ value, label, Icon }) => (
                            <ToggleGroupItem
                                key={value}
                                value={value}
                                aria-label={t(label)}
                                title={t(label)}
                                className="px-2.5"
                            >
                                <Icon aria-hidden="true" />
                                <span className="hidden @2xl/preview:inline">
                                    {t(label)}
                                </span>
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
            </div>
            {target && target.usesLayout === false && (
                <div
                    role="note"
                    className="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1 border-b bg-warning-soft px-3 py-2 text-sm text-warning-text"
                    data-test="preview-target-notice"
                >
                    <TriangleAlert
                        className="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <p className="min-w-0 flex-1">
                        {t(
                            'This page does not use this layout. Visitors never see it here; the preview shows it only so you can try it.',
                        )}
                    </p>
                    {target.suggestion && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="bg-card"
                            onClick={() =>
                                target.onChange(target.suggestion!.value)
                            }
                        >
                            {t('Show {page}', {
                                page: target.suggestion.label,
                            })}
                        </Button>
                    )}
                </div>
            )}
            <div
                ref={host}
                className="relative min-h-0 flex-1 overflow-hidden p-3"
            >
                <div
                    className="relative mx-auto h-full overflow-hidden rounded-md border bg-white shadow-sm"
                    style={{
                        width: Math.round(frameWidth * scale) + 2,
                        maxWidth: '100%',
                    }}
                >
                    <iframe
                        title={title}
                        src={src}
                        className="absolute top-0 left-0 block origin-top-left border-0 bg-white"
                        style={{
                            width: frameWidth,
                            height:
                                paneHeight > 0 ? paneHeight / scale : '100%',
                            transform: scaled ? `scale(${scale})` : undefined,
                        }}
                        data-test={testId}
                    />
                </div>
            </div>
        </section>
    );
}

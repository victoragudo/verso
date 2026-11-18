import { t } from '@/lib/i18n';
import { useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { SitePreviewWidths } from '@/types';
import { DEVICES, DeviceLabel } from './preview-devices';
import type { Device } from './preview-devices';

type Props = {
    src: string;
    widths: SitePreviewWidths;
};

// The real home page in a frame, at computer, tablet or phone width.
export function StylePreview({ src, widths }: Props) {
    const [device, setDevice] = useState<Device>('desktop');
    const width = widths[device];

    return (
        <section
            aria-label={t('Preview')}
            className="flex min-w-0 flex-col gap-3 rounded-lg border bg-muted/40 p-3 sm:p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold">
                    {t('Your site with these picks')}
                </h2>
                <ToggleGroup
                    type="single"
                    variant="outline"
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
                            className="min-h-11 px-3 pointer-fine:md:min-h-9"
                        >
                            <Icon aria-hidden="true" />
                            <span className="hidden sm:inline">{t(label)}</span>
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </div>
            <div className="flex flex-col items-center gap-2 overflow-x-auto">
                <DeviceLabel device={device} width={width} />
                <iframe
                    title={t(
                        'Preview of your home page with the colors, fonts and style you picked',
                    )}
                    src={src}
                    className="h-[75vh] min-h-[28rem] max-w-full rounded-md border bg-white shadow-xs transition-[width] duration-200 motion-reduce:transition-none"
                    style={{ width: width ? `${width}px` : '100%' }}
                    data-test="style-preview"
                />
            </div>
        </section>
    );
}

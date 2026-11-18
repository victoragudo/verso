import { Monitor, Smartphone, Tablet } from 'lucide-react';
import { msg, t } from '@/lib/i18n';
import type { SitePreviewWidths } from '@/types';

// The screen sizes of the previews in Easy mode: computer, tablet and phone. Labels are keys for t().

export type Device = keyof SitePreviewWidths;

export const DEVICES: { value: Device; label: string; Icon: typeof Monitor }[] =
    [
        { value: 'desktop', label: msg('Computer'), Icon: Monitor },
        { value: 'tablet', label: msg('Tablet'), Icon: Tablet },
        { value: 'phone', label: msg('Phone'), Icon: Smartphone },
    ];

// "Phone, 390 pixels wide" or "Computer, full width".
export function deviceLabel(device: Device, width: number | null): string {
    const label = DEVICES.find((item) => item.value === device)?.label;
    const name = label ? t(label) : '';

    return width
        ? t('{device}, {width, number} pixels wide', { device: name, width })
        : t('{device}, full width', { device: name });
}

// The visible label above a preview frame, so people know which screen they look at.
export function DeviceLabel({
    device,
    width,
}: {
    device: Device;
    width: number | null;
}) {
    return (
        <p
            className="shrink-0 rounded-full border bg-card px-3 py-1 text-xs font-medium text-muted-foreground"
            data-test="device-label"
        >
            {deviceLabel(device, width)}
        </p>
    );
}

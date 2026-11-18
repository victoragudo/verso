import { t } from '@/lib/i18n';
import { formatNumber, formatPercent } from './format';
import type { DeviceShare } from './types';

// Share of page views per screen size, one meter per device.
export function DeviceBreakdown({ devices }: { devices: DeviceShare[] }) {
    return (
        <ul className="flex flex-col gap-4">
            {devices.map((device) => (
                <li key={device.device}>
                    <div className="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
                        <span className="text-foreground">{device.label}</span>
                        <span className="text-muted-foreground tabular-nums">
                            {t(
                                'analytics::{share} · {views, plural, one {# view} other {# views}}',
                                {
                                    share: formatPercent(device.share),
                                    views: device.views,
                                },
                            )}
                        </span>
                    </div>
                    <div
                        role="meter"
                        aria-label={t(
                            'analytics::{device} share of page views',
                            { device: device.label },
                        )}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={Math.round(device.share * 100)}
                        className="h-2 overflow-hidden rounded-full bg-brand-soft"
                    >
                        <div
                            className="h-full rounded-full bg-[#2563eb] dark:bg-[#3b82f6]"
                            style={{ width: `${device.share * 100}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

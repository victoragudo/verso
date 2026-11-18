import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Analytics: a rising line over three small bars.
export function AnalyticsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M2.75 17.25h14.5" />
            <path d="M5 14.25v-2.5M10 14.25V10.5M15 14.25V8.5" />
            <path d="m3.5 8.25 4-3.5 3.25 2.25 5.75-4.25" />
            <path d="M13.75 2.75h2.75v2.75" />
        </IconBase>
    );
}

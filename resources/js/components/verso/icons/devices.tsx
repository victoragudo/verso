import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Devices: a monitor with a phone in front of it.
export function DevicesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M11.25 13.25H4.5a1.75 1.75 0 0 1-1.75-1.75v-6.25A1.75 1.75 0 0 1 4.5 3.5h10.25a1.75 1.75 0 0 1 1.75 1.75V7" />
            <path d="M6 16.5h5.25M8.25 13.25v3.25" />
            <rect x="13.25" y="9" width="4.5" height="8.25" rx="1.25" />
            <path d="M15.25 15.25h.5" />
        </IconBase>
    );
}

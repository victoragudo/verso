import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Customize: three sliders with knobs.
export function CustomizeIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M3.25 5.25h4.5M11.25 5.25h5.5" />
            <path d="M3.25 10h8.5M15.25 10h1.5" />
            <path d="M3.25 14.75h2.5M9.25 14.75h7.5" />
            <circle cx="9.5" cy="5.25" r="1.75" />
            <circle cx="13.5" cy="10" r="1.75" />
            <circle cx="7.5" cy="14.75" r="1.75" />
        </IconBase>
    );
}

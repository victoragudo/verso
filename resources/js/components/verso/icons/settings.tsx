import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Settings: two sliders.
export function SettingsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M3 6h7M14 6h3M3 14h3M10 14h7" />
            <circle cx="12" cy="6" r="2" />
            <circle cx="8" cy="14" r="2" />
        </IconBase>
    );
}

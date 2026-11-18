import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Person: one head and shoulders, for a personal page.
export function PersonIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="10" cy="6.75" r="3.25" />
            <path d="M4.25 16.75a5.75 5.75 0 0 1 11.5 0" />
        </IconBase>
    );
}

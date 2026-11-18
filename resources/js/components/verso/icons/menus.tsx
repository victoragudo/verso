import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Menus: a nested list of links.
export function MenusIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M7 5h10M9.5 10H17M9.5 15H17" />
            <circle cx="4" cy="5" r="1.25" />
            <path d="M4 6.25v6.5a2.25 2.25 0 0 0 2.25 2.25M4 10h2.25" />
        </IconBase>
    );
}

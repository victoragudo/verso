import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Help: a question mark in a circle.
export function HelpIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="10" cy="10" r="7.25" />
            <path d="M7.9 7.9a2.15 2.15 0 1 1 3.05 1.95c-.6.3-.95.8-.95 1.45v.45" />
            <path d="M10 13.9v.1" />
        </IconBase>
    );
}

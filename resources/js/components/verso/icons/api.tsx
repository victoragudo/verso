import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// API: curly braces around two dots.
export function ApiIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M7 3.75h-.75a1.5 1.5 0 0 0-1.5 1.5v2.5c0 1.1-.9 2.25-2 2.25 1.1 0 2 1.15 2 2.25v2.5a1.5 1.5 0 0 0 1.5 1.5H7" />
            <path d="M13 3.75h.75a1.5 1.5 0 0 1 1.5 1.5v2.5c0 1.1.9 2.25 2 2.25-1.1 0-2 1.15-2 2.25v2.5a1.5 1.5 0 0 1-1.5 1.5H13" />
            <circle
                cx="8.5"
                cy="10"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
            <circle
                cx="11.5"
                cy="10"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
        </IconBase>
    );
}

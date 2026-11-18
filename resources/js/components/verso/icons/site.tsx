import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Site: a browser window.
export function SiteIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="3.75" width="14.5" height="12.5" rx="2" />
            <path d="M2.75 7.25h14.5" />
            <circle
                cx="5.25"
                cy="5.5"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
            <circle
                cx="7.5"
                cy="5.5"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
        </IconBase>
    );
}

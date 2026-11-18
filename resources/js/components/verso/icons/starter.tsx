import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Starter: a page with a banner and two blocks, plus a small spark for "ready made".
export function StarterIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="3.75" width="11.5" height="12.5" rx="1.5" />
            <path d="M5.25 7h6.5M5.25 10.25h2.5M5.25 13h2.5M10.25 10.25h1.5v2.75h-1.5z" />
            <path d="M16.25 2.75v3M14.75 4.25h3" />
        </IconBase>
    );
}

import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Code: two angle brackets around a slash, for custom CSS and JavaScript.
export function CodeIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="m6.25 5.75-4 4.25 4 4.25" />
            <path d="m13.75 5.75 4 4.25-4 4.25" />
            <path d="m11.25 3.75-2.5 12.5" />
        </IconBase>
    );
}

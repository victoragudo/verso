import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Extensions: an open package box.
export function ExtensionsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M3.25 6.25 10 2.75l6.75 3.5v7.5L10 17.25l-6.75-3.5z" />
            <path d="M3.25 6.25 10 9.75l6.75-3.5" />
            <path d="M10 9.75v7.5" />
            <path d="m6.6 4.5 6.75 3.5" />
        </IconBase>
    );
}

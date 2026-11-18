import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Warning: a rounded triangle with an exclamation mark.
export function WarningIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M8.7 3.6a1.5 1.5 0 0 1 2.6 0l5.6 9.9a1.5 1.5 0 0 1-1.3 2.25H4.4a1.5 1.5 0 0 1-1.3-2.25z" />
            <path d="M10 7.75v3.25" />
            <path d="M10 13.4v.1" />
        </IconBase>
    );
}

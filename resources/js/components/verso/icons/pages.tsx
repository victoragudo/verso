import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Pages: a sheet with a folded corner.
export function PagesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M5.75 2.75h5.5l4 4v8.5a2 2 0 0 1-2 2h-7.5a2 2 0 0 1-2-2V4.75a2 2 0 0 1 2-2z" />
            <path d="M11 3v2.75a1 1 0 0 0 1 1h3" />
            <path d="M7 11h6M7 14h4" />
        </IconBase>
    );
}

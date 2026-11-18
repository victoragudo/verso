import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Media: a framed picture with hills and a sun.
export function MediaIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="3.75" width="14.5" height="12.5" rx="2" />
            <circle cx="7.25" cy="8" r="1.5" />
            <path d="m3.25 14.25 3.6-3.1a1 1 0 0 1 1.3 0l2.35 2 2.85-3.05a1 1 0 0 1 1.45-.02l2.45 2.52" />
        </IconBase>
    );
}

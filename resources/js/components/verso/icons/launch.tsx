import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Launch: a small rocket going up, for the launch checklist and "Go live".
export function LaunchIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M10 2.75c2.1 1.6 3.25 4.1 3.25 6.9v3.6h-6.5v-3.6c0-2.8 1.15-5.3 3.25-6.9z" />
            <circle cx="10" cy="8.25" r="1.25" />
            <path d="M6.75 10.75l-2 2v2.5l2-1M13.25 10.75l2 2v2.5l-2-1" />
            <path d="M8.75 15.5v1.75M11.25 15.5v1.75" />
        </IconBase>
    );
}

import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Updates: a turning arrow around an arrow that points up.
export function UpdatesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M16.25 10a6.25 6.25 0 1 1-1.83-4.42" />
            <path d="M14.75 2.75v3h-3" />
            <path d="M10 13.25v-6" />
            <path d="m7.75 9.5 2.25-2.25 2.25 2.25" />
        </IconBase>
    );
}

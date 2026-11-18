import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Clock: a round face with two hands.
export function ClockIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="10" cy="10" r="7.25" />
            <path d="M10 6v4l2.75 1.75" />
        </IconBase>
    );
}

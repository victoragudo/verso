import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Comments: a speech bubble with three dots.
export function CommentsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M4.75 3.75h10.5a2 2 0 0 1 2 2v6.5a2 2 0 0 1-2 2H9.5l-3.75 2.75v-2.75h-1a2 2 0 0 1-2-2v-6.5a2 2 0 0 1 2-2z" />
            <circle cx="7" cy="9" r="0.9" fill="currentColor" stroke="none" />
            <circle cx="10" cy="9" r="0.9" fill="currentColor" stroke="none" />
            <circle cx="13" cy="9" r="0.9" fill="currentColor" stroke="none" />
        </IconBase>
    );
}

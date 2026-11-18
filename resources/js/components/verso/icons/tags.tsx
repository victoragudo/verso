import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Tags: a label with a hole.
export function TagsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M3 4.5C3 3.67 3.67 3 4.5 3h4.88c.4 0 .78.16 1.06.44l6.06 6.06a1.5 1.5 0 0 1 0 2.12l-4.88 4.88a1.5 1.5 0 0 1-2.12 0L3.44 10.44A1.5 1.5 0 0 1 3 9.38z" />
            <circle cx="7" cy="7" r="1.25" />
        </IconBase>
    );
}

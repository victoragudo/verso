import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Search: a simple lens.
export function SearchIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="8.75" cy="8.75" r="5.75" />
            <path d="m13 13 4.25 4.25" />
        </IconBase>
    );
}

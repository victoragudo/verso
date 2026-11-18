import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Fallback: a small ring for unknown keys.
export function DotIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="10" cy="10" r="3.25" />
        </IconBase>
    );
}

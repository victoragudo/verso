import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Verification: a shield with a check mark.
export function ShieldIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M10 2.75 4.25 5v4.5c0 3.6 2.4 6.4 5.75 7.75 3.35-1.35 5.75-4.15 5.75-7.75V5z" />
            <path d="m7.5 10 1.75 1.75 3.25-3.5" />
        </IconBase>
    );
}

import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Product: a closed box seen from the corner.
export function ProductIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="m10 2.75 6.25 3.25v8L10 17.25 3.75 14V6z" />
            <path d="M3.75 6 10 9.25 16.25 6M10 9.25v8" />
            <path d="m6.9 4.35 6.25 3.3" />
        </IconBase>
    );
}

import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Orders: a receipt with a torn bottom edge and lines of text.
export function OrdersIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M5 2.75h10a.75.75 0 0 1 .75.75v13.25l-2.2-1.25-1.85 1.25-1.7-1.25-1.7 1.25-1.85-1.25-2.2 1.25V3.5A.75.75 0 0 1 5 2.75z" />
            <path d="M7.5 6.75h5M7.5 9.75h5M7.5 12.75h2.5" />
        </IconBase>
    );
}

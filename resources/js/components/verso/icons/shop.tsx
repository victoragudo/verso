import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Shop: a shopping bag with two handles.
export function ShopIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M4.6 6.75h10.8l-.7 9.1a1.5 1.5 0 0 1-1.5 1.4H6.8a1.5 1.5 0 0 1-1.5-1.4z" />
            <path d="M7.25 8.75v-3a2.75 2.75 0 0 1 5.5 0v3" />
        </IconBase>
    );
}

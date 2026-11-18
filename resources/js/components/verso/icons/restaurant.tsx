import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Restaurant: a fork and a knife side by side.
export function RestaurantIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M6 2.75v4.5a1.75 1.75 0 0 0 3.5 0v-4.5M7.75 2.75v14.5" />
            <path d="M14.25 17.25V2.75c-1.75.75-2.5 2.75-2.5 5.25v3.25h2.5" />
        </IconBase>
    );
}

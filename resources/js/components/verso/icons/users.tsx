import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Users: two people side by side.
export function UsersIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="7.5" cy="7" r="2.75" />
            <path d="M2.75 16.25a4.75 4.75 0 0 1 9.5 0" />
            <path d="M12.75 4.5a2.75 2.75 0 0 1 0 5M14.5 12.1a4.75 4.75 0 0 1 2.75 4.15" />
        </IconBase>
    );
}

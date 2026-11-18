import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Dashboard: four tiles of different sizes.
export function DashboardIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="3" y="3" width="6" height="8" rx="1.5" />
            <rect x="11" y="3" width="6" height="5" rx="1.5" />
            <rect x="11" y="10" width="6" height="7" rx="1.5" />
            <rect x="3" y="13" width="6" height="4" rx="1.5" />
        </IconBase>
    );
}

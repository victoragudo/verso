import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Referrers: an arrow coming in from another page.
export function ReferrersIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M10.75 3.25h4.25a2 2 0 0 1 2 2v9.5a2 2 0 0 1-2 2h-4.25" />
            <path d="M2.75 10h9.5" />
            <path d="m9 6.75 3.25 3.25L9 13.25" />
        </IconBase>
    );
}

import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Entries: two stacked sheets.
export function EntriesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M7 3h6.75a2 2 0 0 1 2 2v9" />
            <rect x="4.25" y="5.5" width="9" height="11.75" rx="2" />
            <path d="M7 9.5h3.5M7 12.5h3.5" />
        </IconBase>
    );
}

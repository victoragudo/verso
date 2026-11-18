import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Patterns: three blocks and a plus, a ready-made group of blocks.
export function PatternsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="2.75" width="6" height="6" rx="1.5" />
            <rect x="11.25" y="2.75" width="6" height="6" rx="1.5" />
            <rect x="2.75" y="11.25" width="6" height="6" rx="1.5" />
            <path d="M14.25 11.25v6M11.25 14.25h6" />
        </IconBase>
    );
}

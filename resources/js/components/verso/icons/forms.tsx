import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Forms: a card with two input lines and a small button.
export function FormsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="3.25" y="2.75" width="13.5" height="14.5" rx="2" />
            <path d="M6.5 6.5h7" />
            <rect x="6.5" y="9" width="7" height="2.5" rx="0.75" />
            <path d="M10.5 14.25h3" />
        </IconBase>
    );
}

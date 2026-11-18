import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Site Editor: a page frame with its header and footer bands picked out.
export function SiteEditorIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="3" y="3.5" width="14" height="13" rx="2" />
            <path d="M3 7.5h14M3 12.5h14" />
            <path d="M5.75 5.5h3M11.25 14.5h3" />
        </IconBase>
    );
}

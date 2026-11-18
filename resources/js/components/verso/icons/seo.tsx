import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// SEO: a lens over a rising line.
export function SeoIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="8.5" cy="8.5" r="5.75" />
            <path d="m12.75 12.75 4.5 4.5" />
            <path d="m5.75 10.25 1.75-1.75 1.25 1.25 2.5-2.75" />
        </IconBase>
    );
}

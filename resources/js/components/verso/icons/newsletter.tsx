import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Newsletter: an envelope with a small send line.
export function NewsletterIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="4.75" width="14.5" height="10.5" rx="2" />
            <path d="m3.5 6 6.5 4.75L16.5 6" />
        </IconBase>
    );
}

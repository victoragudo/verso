import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Sparkles: one large four point star and a small one, for AI help.
export function SparklesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M8.5 3.25c.4 2.9 1.85 4.35 4.75 4.75-2.9.4-4.35 1.85-4.75 4.75-.4-2.9-1.85-4.35-4.75-4.75 2.9-.4 4.35-1.85 4.75-4.75Z" />
            <path d="M14.75 11.75c.2 1.45.9 2.15 2.25 2.25-1.35.1-2.05.8-2.25 2.25-.2-1.45-.9-2.15-2.25-2.25 1.35-.1 2.05-.8 2.25-2.25Z" />
            <path d="M4.25 14.25v2.5M3 15.5h2.5" />
        </IconBase>
    );
}

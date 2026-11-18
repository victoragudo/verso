import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Themes: a fan of color swatches.
export function ThemesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="2.75" width="6" height="14.5" rx="2" />
            <circle
                cx="5.75"
                cy="13.75"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
            <path d="m8.75 6.5 2.6-2.6a1.5 1.5 0 0 1 2.12 0l2.13 2.13a1.5 1.5 0 0 1 0 2.12L8.75 15" />
            <path d="M8.75 17.25h6.5a2 2 0 0 0 2-2V12.5a2 2 0 0 0-2-2h-1.25" />
        </IconBase>
    );
}

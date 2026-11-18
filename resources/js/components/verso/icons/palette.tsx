import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Palette: a painter's palette with three paint dots and a thumb hole.
export function PaletteIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M10 2.75c-4 0-7.25 3-7.25 6.9 0 3.9 3.1 6.6 6.4 6.6 1.3 0 1.85-.8 1.85-1.6 0-.95-.75-1.3-.75-2.15 0-.9.7-1.5 1.6-1.5h1.8c2.1 0 3.6-1.55 3.6-3.4 0-3-3.15-4.85-7.25-4.85z" />
            <circle cx="6.5" cy="9" r="1" />
            <circle cx="9" cy="6" r="1" />
            <circle cx="12.75" cy="6.75" r="1" />
        </IconBase>
    );
}

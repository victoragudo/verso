import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Plugins: a puzzle piece.
export function PluginsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M4.25 5.75h2.5a1.75 1.75 0 1 1 3.5 0h2.5a1 1 0 0 1 1 1v2.5a1.75 1.75 0 1 1 0 3.5v2.5a1 1 0 0 1-1 1h-8.5a1 1 0 0 1-1-1v-8.5a1 1 0 0 1 1-1z" />
        </IconBase>
    );
}

import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Importer: an arrow going into a tray.
export function ImporterIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M10 2.75v9" />
            <path d="M6.75 8.5 10 11.75l3.25-3.25" />
            <path d="M2.75 12.25v2a2 2 0 0 0 2 2h10.5a2 2 0 0 0 2-2v-2" />
        </IconBase>
    );
}

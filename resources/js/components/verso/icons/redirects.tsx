import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Redirects: a path that turns to a new place.
export function RedirectsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M2.75 15.25H5.5a3 3 0 0 0 3-3v-4.5a3 3 0 0 1 3-3h5" />
            <path d="m14.25 2.25 2.5 2.5-2.5 2.5" />
        </IconBase>
    );
}

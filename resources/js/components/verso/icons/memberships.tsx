import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Memberships: a member card with a person and a star.
export function MembershipsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="4.25" width="14.5" height="11.5" rx="2" />
            <circle cx="7.5" cy="9" r="1.75" />
            <path d="M4.75 13.25a2.75 2.75 0 0 1 5.5 0" />
            <path d="m14 7.5.55 1.1 1.2.18-.87.85.2 1.2L14 10.27l-1.08.56.2-1.2-.87-.85 1.2-.18z" />
        </IconBase>
    );
}

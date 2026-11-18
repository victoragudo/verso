import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Custom fields: a form card with labelled input rows.
export function FieldsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="3" y="3.5" width="14" height="13" rx="2" />
            <path d="M6 7.5h3M6 12.5h3" />
            <rect x="11" y="6.25" width="3.5" height="2.5" rx="0.75" />
            <rect x="11" y="11.25" width="3.5" height="2.5" rx="0.75" />
        </IconBase>
    );
}

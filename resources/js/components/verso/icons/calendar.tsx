import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Calendar: a month page with a marked day.
export function CalendarIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="2.75" y="4" width="14.5" height="13.25" rx="2" />
            <path d="M2.75 8h14.5M6.5 2.5v3M13.5 2.5v3" />
            <circle
                cx="7"
                cy="11.75"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
            <circle
                cx="10"
                cy="11.75"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
            <circle
                cx="13"
                cy="11.75"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
            <circle
                cx="7"
                cy="14.25"
                r="0.9"
                fill="currentColor"
                stroke="none"
            />
        </IconBase>
    );
}

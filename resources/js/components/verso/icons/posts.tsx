import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Posts: a folded news sheet.
export function PostsIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M4 4.75C4 3.78 4.78 3 5.75 3h6.5c.97 0 1.75.78 1.75 1.75V15.5c0 .83.67 1.5 1.5 1.5H5.75C4.78 17 4 16.22 4 15.25z" />
            <path d="M14 8h1.25c.69 0 1.25.56 1.25 1.25v6.25a1.5 1.5 0 0 1-1.5 1.5" />
            <path d="M7 6.5h4M7 9.5h4M7 12.5h2.5" />
        </IconBase>
    );
}

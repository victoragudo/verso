import { IconBase } from './icon-base';
import type { IconProps } from './icon-base';

// Categories: a folder with a divider.
export function CategoriesIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M2.75 5.25a2 2 0 0 1 2-2h2.88c.4 0 .78.16 1.06.44l1.37 1.37c.28.28.66.44 1.06.44h4.13a2 2 0 0 1 2 2v7.25a2 2 0 0 1-2 2H4.75a2 2 0 0 1-2-2z" />
            <path d="M2.75 8.5h14.5" />
        </IconBase>
    );
}

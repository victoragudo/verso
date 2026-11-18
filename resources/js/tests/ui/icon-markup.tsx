import { render } from '@testing-library/react';
import { VersoIcon } from '@/components/verso/icons';

// The inner SVG markup of a Verso icon, to check which icon a component drew.
export function iconMarkup(name: string): string {
    const { container, unmount } = render(<VersoIcon name={name} />);
    const markup = container.querySelector('svg')?.innerHTML ?? '';

    unmount();

    return markup;
}

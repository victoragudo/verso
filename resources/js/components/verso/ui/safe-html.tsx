import { useLayoutEffect, useRef } from 'react';
import type { ComponentProps } from 'react';

// Elements and attributes that Markdown output (release notes, help text) can hold.
export const MARKDOWN_ELEMENTS = [
    'p',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'ul',
    'ol',
    'li',
    'a',
    'code',
    'pre',
    'em',
    'strong',
    'blockquote',
    'hr',
    'br',
    'img',
    'del',
    'table',
    'thead',
    'tbody',
    'tr',
    'th',
    'td',
];
export const MARKDOWN_ATTRIBUTES = ['href', 'title', 'src', 'alt', 'start'];

type SanitizerOptions = {
    sanitizer: { elements: string[]; attributes: string[] };
};

type ElementWithSetHtml = HTMLElement & {
    setHTML: (html: string, options?: SanitizerOptions) => void;
};

function hasSetHtml(element: HTMLElement): element is ElementWithSetHtml {
    return 'setHTML' in element;
}

type Props = Omit<
    ComponentProps<'div'>,
    'children' | 'dangerouslySetInnerHTML'
> & {
    // HTML that the server already sanitized. The server stays the source of truth.
    html: string;
    elements?: string[];
    attributes?: string[];
};

// Shows server sanitized HTML. Where the browser has the Sanitizer API,
// setHTML() checks it again with a small allowlist (defence in depth);
// elsewhere the server output is used as it is.
export function SafeHtml({
    html,
    elements = MARKDOWN_ELEMENTS,
    attributes = MARKDOWN_ATTRIBUTES,
    ...props
}: Props) {
    const ref = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        if (hasSetHtml(element)) {
            element.setHTML(html, { sanitizer: { elements, attributes } });
        } else {
            element.innerHTML = html;
        }
    }, [html, elements, attributes]);

    return <div ref={ref} {...props} />;
}

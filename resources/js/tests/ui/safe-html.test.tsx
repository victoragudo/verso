import { render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SafeHtml } from '@/components/verso/ui';

const notes = '<h2>Verso 2.4</h2><p>Faster media uploads.</p>';

describe('SafeHtml', () => {
    afterEach(() => {
        delete (Element.prototype as unknown as Record<string, unknown>)
            .setHTML;
    });

    it('checks the HTML again with the Sanitizer API when the browser has it', () => {
        const setHTML = vi.fn(function (this: Element, html: string) {
            this.innerHTML = html.replace(/<script.*?<\/script>/g, '');
        });
        Object.defineProperty(Element.prototype, 'setHTML', {
            value: setHTML,
            configurable: true,
        });

        const { container } = render(
            <SafeHtml html={`${notes}<script>steal()</script>`} />,
        );

        expect(setHTML).toHaveBeenCalledWith(
            `${notes}<script>steal()</script>`,
            expect.objectContaining({
                sanitizer: expect.objectContaining({
                    elements: expect.arrayContaining(['p', 'h2', 'a']),
                    attributes: expect.arrayContaining(['href']),
                }),
            }),
        );
        expect(container.querySelector('script')).toBeNull();
        expect(container).toHaveTextContent('Faster media uploads.');
    });

    it('uses the server sanitized HTML as it is in other browsers', () => {
        const { container } = render(
            <SafeHtml className="release-notes" html={notes} />,
        );

        expect(container.querySelector('.release-notes h2')).toHaveTextContent(
            'Verso 2.4',
        );
    });
});

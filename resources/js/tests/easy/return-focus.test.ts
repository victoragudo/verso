import { afterEach, describe, expect, it } from 'vitest';
import { markFocus, returnFocus } from '@/lib/easy/return-focus';
import { pageHelper } from '@/pages/admin/plugins/ai-assistant/page-helper/store';
import { editorContext } from '../ai-assistant/page-helper-fixtures';

afterEach(() => {
    pageHelper.reset();
    document.body.innerHTML = '';
});

// A same origin frame with the toolbar button of the bakery page.
function frameWithButton(): {
    frame: HTMLIFrameElement;
    button: HTMLButtonElement;
} {
    const frame = document.createElement('iframe');
    document.body.append(frame);
    const doc = frame.contentDocument!;
    doc.body.innerHTML =
        '<section><button type="button" data-action="duplicate">Duplicate</button></section>';

    return { frame, button: doc.querySelector('button')! };
}

describe('focus after dialogs', () => {
    it('remembers a button inside the page editor frame and focuses it again', () => {
        const { frame, button } = frameWithButton();
        frame.focus();
        button.focus();

        const mark = markFocus();
        document.body.focus();

        expect(mark).toEqual({ element: frame, inner: button });
        expect(returnFocus(mark)).toBe(true);
        expect(document.activeElement).toBe(frame);
        expect(frame.contentDocument?.activeElement).toBe(button);
    });

    it('focuses a plain button again and gives up when it is gone', () => {
        document.body.innerHTML = '<button type="button">AI helper</button>';
        const button = document.querySelector('button')!;
        button.focus();
        const mark = markFocus();

        expect(returnFocus(mark)).toBe(true);
        expect(document.activeElement).toBe(button);

        button.remove();
        expect(returnFocus(mark)).toBe(false);
        expect(returnFocus(null)).toBe(false);
        expect(markFocus()).toEqual({ element: null, inner: null });
    });

    it('gives focus back after the last AI helper dialog closes', () => {
        document.body.innerHTML =
            '<button type="button">AI helper</button><button type="button">Translate</button>';
        const [opener, inDialog] = document.querySelectorAll('button');
        opener.focus();

        pageHelper.open({ kind: 'menu', context: editorContext() });
        inDialog.focus();
        pageHelper.open({ kind: 'translate', context: editorContext() });
        expect(pageHelper.returnFocus()).toBe(false);

        pageHelper.close();
        expect(pageHelper.returnFocus()).toBe(true);
        expect(document.activeElement).toBe(opener);
        expect(pageHelper.returnFocus()).toBe(false);
    });
});

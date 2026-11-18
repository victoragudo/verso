import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    ADD_CLASS,
    attachBridge,
    BAR_CLASS,
    cleanHtml,
    CORE_SECTION_ACTIONS,
    EDIT_STYLES,
    FIX_COLORS_ACTION,
    IMAGE_BUTTON_CLASS,
    STYLE_ID,
} from '@/lib/easy/frame-bridge';
import type { FrameBridge, FrameBridgeOptions } from '@/lib/easy/frame-bridge';

// The bakery page as the theme draws it in edit mode.
const PAGE = `
<header><a href="/">Harbor Lane Bakery</a></header>
<main class="prose">
  <section data-verso-block="b_hero0001" data-verso-type="section" class="verso-section">
    <h2 data-verso-block="b_title001" data-verso-type="heading">Fresh bread on <em>Harbour</em> Lane</h2>
    <p data-verso-block="b_text0001" data-verso-type="paragraph">Baked at five, gone by noon.</p>
    <div data-verso-block="b_button01" data-verso-type="button" class="verso-buttons"><a class="verso-button" href="/menu">See the menu</a></div>
  </section>
  <section data-verso-block="b_serv0001" data-verso-type="section" class="verso-section" data-verso-hidden>
    <ul data-verso-block="b_list0001" data-verso-type="list"><li>Sourdough</li><li>Rye</li></ul>
    <blockquote data-verso-block="b_quote001" data-verso-type="quote"><p>Best buns in town.</p><cite>Laura Gómez</cite></blockquote>
    <figure data-verso-block="b_image001" data-verso-type="image" class="verso-image"><img src="/storage/loaves.jpg" alt="Loaves"><figcaption>Morning loaves</figcaption></figure>
  </section>
  <hr data-verso-block="b_line0001" data-verso-type="separator">
  <figure data-verso-block="b_gallery1" data-verso-type="gallery" class="verso-gallery">
    <figure class="verso-gallery__item"><img src="/storage/terrace.jpg" alt="Terrace"></figure>
    <figure class="verso-gallery__item"><img src="/storage/oven.jpg" alt="Oven"></figure>
  </figure>
</main>`;

let bridge: FrameBridge | null = null;

function attach(overrides: Partial<FrameBridgeOptions> = {}) {
    const options: FrameBridgeOptions = {
        sections: [
            { id: 'b_hero0001', name: 'Section 1: Fresh bread', hidden: false },
            { id: 'b_serv0001', name: 'Section 2: What we bake', hidden: true },
            { id: 'b_line0001', name: 'Section 3', hidden: false },
            {
                id: 'b_gallery1',
                name: 'Section 4',
                hidden: false,
                skip: ['duplicate'],
            },
        ],
        actions: CORE_SECTION_ACTIONS,
        onText: vi.fn(),
        onImage: vi.fn(),
        onSectionAction: vi.fn(),
        onAddSection: vi.fn(),
        onEditingChange: vi.fn(),
        ...overrides,
    };
    bridge = attachBridge(document, options);

    return options;
}

const section = (id: string) =>
    document.querySelector<HTMLElement>(`[data-verso-section="${id}"]`)!;
const bar = (id: string) =>
    section(id).querySelector<HTMLElement>(`:scope > .${BAR_CLASS}`)!;
const action = (id: string, name: string) =>
    bar(id).querySelector<HTMLButtonElement>(`button[data-action="${name}"]`)!;
const key = (target: Element, init: KeyboardEventInit) =>
    target.dispatchEvent(
        new KeyboardEvent('keydown', {
            bubbles: true,
            cancelable: true,
            ...init,
        }),
    );

beforeEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = PAGE;
});

afterEach(() => {
    bridge?.detach();
    bridge = null;
});

describe('frame bridge', () => {
    it('keeps the phone toolbar hidden while a tap only focuses a text', () => {
        const phone = EDIT_STYLES.slice(
            EDIT_STYLES.indexOf('@media (max-width: 640px) {'),
        );
        const shown = phone.slice(0, phone.indexOf('}\n}'));

        expect(shown).toContain('display: flex');
        expect(shown).not.toContain(':focus-within');
        expect(shown).toContain(':has(:focus-visible)');
    });

    it('adds the edit styles once', () => {
        attach();
        bridge?.detach();
        attach();

        expect(document.querySelectorAll(`#${STYLE_ID}`)).toHaveLength(1);
    });

    it('gives every section a labelled toolbar and makes it focusable', () => {
        attach();

        expect(section('b_hero0001').tabIndex).toBe(0);
        expect(bar('b_hero0001').getAttribute('role')).toBe('toolbar');
        expect(bar('b_hero0001').getAttribute('aria-label')).toBe(
            'Tools for Section 1: Fresh bread',
        );
        expect(
            [...bar('b_hero0001').querySelectorAll('button')].map(
                (item) => item.textContent,
            ),
        ).toEqual(['Move up', 'Move down', 'Duplicate', 'Hide', 'Delete']);
        expect(
            action('b_hero0001', 'move-up').getAttribute('aria-disabled'),
        ).toBe('true');
        expect(
            action('b_gallery1', 'move-down').getAttribute('aria-disabled'),
        ).toBe('true');
        expect(
            action('b_serv0001', 'move-up').hasAttribute('aria-disabled'),
        ).toBe(false);
        expect(action('b_gallery1', 'duplicate')).toBeNull();
    });

    it('labels hidden sections and offers to show them', () => {
        attach();

        expect(action('b_serv0001', 'toggle-hidden').textContent).toBe('Show');
        expect(section('b_serv0001').textContent).toContain(
            'Hidden on your site',
        );
    });

    it('wraps elements that cannot hold a toolbar', () => {
        attach();
        const line = document.querySelector('hr')!;

        expect(line.parentElement?.hasAttribute('data-verso-wrap')).toBe(true);
        expect(line.parentElement?.getAttribute('data-verso-section')).toBe(
            'b_line0001',
        );
    });

    it('runs section actions and ignores disabled ones', () => {
        const options = attach();

        action('b_serv0001', 'move-up').click();
        action('b_hero0001', 'move-up').click();
        action('b_hero0001', 'delete').click();

        expect(options.onSectionAction).toHaveBeenCalledTimes(2);
        expect(options.onSectionAction).toHaveBeenNthCalledWith(
            1,
            'b_serv0001',
            'move-up',
        );
        expect(options.onSectionAction).toHaveBeenNthCalledWith(
            2,
            'b_hero0001',
            'delete',
        );
    });

    it('adds "Add section" buttons between sections and at the end', () => {
        const options = attach();
        const rows = [
            ...document.querySelectorAll<HTMLElement>(`.${ADD_CLASS}`),
        ];

        expect(rows.map((row) => row.dataset.index)).toEqual([
            '0',
            '1',
            '2',
            '3',
            '4',
        ]);
        expect(
            rows[0].querySelector('button')?.getAttribute('aria-label'),
        ).toBe('Add a section at the top');
        rows[2].querySelector('button')!.click();
        expect(options.onAddSection).toHaveBeenCalledWith(2);
    });

    it('offers to replace pictures of image blocks and galleries', () => {
        const options = attach();
        const buttons = [
            ...document.querySelectorAll<HTMLButtonElement>(
                `.${IMAGE_BUTTON_CLASS}`,
            ),
        ];

        expect(
            buttons.map(
                (item) => item.getAttribute('aria-label') ?? item.textContent,
            ),
        ).toEqual(['Replace image', 'Replace picture 1', 'Replace picture 2']);
        buttons[0].click();
        buttons[2].click();
        expect(options.onImage).toHaveBeenNthCalledWith(1, {
            blockId: 'b_image001',
        });
        expect(options.onImage).toHaveBeenNthCalledWith(2, {
            blockId: 'b_gallery1',
            index: 1,
        });
    });

    it('edits a heading on click and saves it with Enter', () => {
        const options = attach();
        const heading = document.querySelector<HTMLElement>('h2')!;

        heading.click();
        expect(heading.getAttribute('contenteditable')).toBe('true');
        expect(document.activeElement).toBe(heading);
        expect(bridge?.isEditing()).toBe(true);
        expect(options.onEditingChange).toHaveBeenLastCalledWith(true);

        heading.innerHTML = 'Fresh bread on <em>Harbour</em> Lane, daily<br>';
        key(heading, { key: 'Enter' });

        expect(heading.hasAttribute('contenteditable')).toBe(false);
        expect(bridge?.isEditing()).toBe(false);
        expect(options.onText).toHaveBeenCalledWith(
            'b_title001',
            'content',
            'Fresh bread on <em>Harbour</em> Lane, daily',
        );
        expect(options.onEditingChange).toHaveBeenLastCalledWith(false);
    });

    it('waits while an input method composes text', () => {
        const options = attach();
        const heading = document.querySelector<HTMLElement>('h2')!;

        heading.click();
        heading.textContent = 'パン屋';
        key(heading, { key: 'Enter', isComposing: true });

        expect(bridge?.isEditing()).toBe(true);
        expect(options.onText).not.toHaveBeenCalled();
    });

    it('cancels the edit with Escape', () => {
        const options = attach();
        const paragraph = document.querySelector<HTMLElement>(
            '[data-verso-type="paragraph"]',
        )!;

        paragraph.click();
        paragraph.textContent = 'Closed on Mondays.';
        key(paragraph, { key: 'Escape' });

        expect(paragraph.textContent).toBe('Baked at five, gone by noon.');
        expect(options.onText).not.toHaveBeenCalled();
        expect(document.activeElement).toBe(paragraph);
    });

    it('saves a paragraph as a paragraph when the text loses focus', () => {
        const options = attach();
        const paragraph = document.querySelector<HTMLElement>(
            '[data-verso-type="paragraph"]',
        )!;

        paragraph.click();
        paragraph.innerHTML = 'Baked at <strong>five</strong>.';
        paragraph.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));

        expect(options.onText).toHaveBeenCalledWith(
            'b_text0001',
            'content',
            '<p>Baked at <strong>five</strong>.</p>',
        );
    });

    it('does not report text that did not change', () => {
        const options = attach();
        const heading = document.querySelector<HTMLElement>('h2')!;

        heading.click();
        key(heading, { key: 'Enter' });

        expect(options.onText).not.toHaveBeenCalled();
    });

    it('starts editing from the keyboard with Enter', () => {
        attach();
        const heading = document.querySelector<HTMLElement>('h2')!;
        heading.focus();
        key(heading, { key: 'Enter' });

        expect(heading.getAttribute('contenteditable')).toBe('true');
    });

    it('edits button labels as plain text and keeps links from opening', () => {
        const options = attach();
        const link =
            document.querySelector<HTMLAnchorElement>('.verso-button')!;
        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });

        link.dispatchEvent(click);
        expect(click.defaultPrevented).toBe(true);
        expect(link.getAttribute('contenteditable')).toMatch(
            /^(plaintext-only|true)$/,
        );

        link.textContent = '  Order a cake ';
        key(link, { key: 'Enter' });
        expect(options.onText).toHaveBeenCalledWith(
            'b_button01',
            'label',
            'Order a cake',
        );

        const other = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });
        document.querySelector('header a')!.dispatchEvent(other);
        expect(other.defaultPrevented).toBe(true);
    });

    it('keeps Enter for new list items and saves the list', () => {
        const options = attach();
        const list = document.querySelector<HTMLElement>('ul')!;

        list.click();
        const enter = key(list, { key: 'Enter' });
        expect(enter).toBe(true);

        list.innerHTML = '<li>Sourdough</li><li>Rye</li><li>Spelt</li>';
        list.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
        expect(options.onText).toHaveBeenCalledWith(
            'b_list0001',
            'content',
            '<li>Sourdough</li><li>Rye</li><li>Spelt</li>',
        );
    });

    it('edits quotes, citations and captions', () => {
        const options = attach();
        const quote = document.querySelector<HTMLElement>('blockquote p')!;
        const cite = document.querySelector<HTMLElement>('cite')!;
        const caption = document.querySelector<HTMLElement>('figcaption')!;

        quote.click();
        quote.textContent = 'Best buns on the coast.';
        cite.click();
        cite.textContent = 'Tom Becker';
        caption.click();
        caption.textContent = 'Loaves at six';
        key(caption, { key: 'Enter' });

        expect(options.onText).toHaveBeenNthCalledWith(
            1,
            'b_quote001',
            'content',
            '<p>Best buns on the coast.</p>',
        );
        expect(options.onText).toHaveBeenNthCalledWith(
            2,
            'b_quote001',
            'citation',
            'Tom Becker',
        );
        expect(options.onText).toHaveBeenNthCalledWith(
            3,
            'b_image001',
            'caption',
            'Loaves at six',
        );
    });

    it('pastes plain text only', () => {
        attach();
        const heading = document.querySelector<HTMLElement>('h2')!;
        heading.click();
        const paste = new Event('paste', {
            bubbles: true,
            cancelable: true,
        }) as ClipboardEvent;
        Object.defineProperty(paste, 'clipboardData', {
            value: { getData: () => 'Rye\n and   spelt' },
        });
        heading.dispatchEvent(paste);

        expect(paste.defaultPrevented).toBe(true);
        expect(heading.innerHTML).toContain('Rye and spelt');
        expect(heading.innerHTML).not.toContain('\n');
    });

    it('moves between toolbar buttons with arrow keys', () => {
        attach();
        const buttons = [
            ...bar('b_serv0001').querySelectorAll<HTMLButtonElement>('button'),
        ];

        expect(buttons.map((item) => item.tabIndex)).toEqual([
            0, -1, -1, -1, -1,
        ]);
        buttons[0].focus();
        key(buttons[0], { key: 'ArrowRight' });
        expect(document.activeElement).toBe(buttons[1]);
        key(buttons[1], { key: 'End' });
        expect(document.activeElement).toBe(buttons[4]);
        key(buttons[4], { key: 'ArrowRight' });
        expect(document.activeElement).toBe(buttons[0]);
        expect(buttons[0].tabIndex).toBe(0);
    });

    it('focuses what the editor asks for after drawing', () => {
        attach({
            focus: {
                kind: 'action',
                sectionId: 'b_serv0001',
                action: 'move-down',
            },
        });
        expect(document.activeElement).toBe(action('b_serv0001', 'move-down'));
        expect(section('b_serv0001')).toHaveClass('verso-easy-active');

        bridge?.detach();
        document.body.innerHTML = PAGE;
        attach({ focus: { kind: 'add', index: 1 } });
        expect(document.activeElement).toBe(
            document.querySelector(`.${ADD_CLASS}[data-index="1"] button`),
        );
        expect(document.querySelector('.verso-easy-active')).toBeNull();

        bridge?.detach();
        document.body.innerHTML = PAGE;
        attach({ focus: { kind: 'section', sectionId: 'b_hero0001' } });
        expect(document.activeElement).toBe(section('b_hero0001'));
        expect(section('b_hero0001')).toHaveClass('verso-easy-active');
    });

    it('stops listening after detach and saves an open edit', () => {
        const options = attach();
        const heading = document.querySelector<HTMLElement>('h2')!;
        heading.click();
        heading.textContent = 'Closed for the holidays';
        bridge?.detach();
        bridge = null;

        expect(options.onText).toHaveBeenCalledWith(
            'b_title001',
            'content',
            'Closed for the holidays',
        );
        document
            .querySelector<HTMLElement>('[data-verso-type="paragraph"]')!
            .click();
        expect(document.querySelector('[contenteditable]')).toBeNull();
    });

    it('cleans what browsers leave behind', () => {
        expect(cleanHtml('&nbsp; Rye bread<br><br> ')).toBe('Rye bread');
    });

    it('puts "Add your first section" where the content of an empty page goes', () => {
        document.body.innerHTML =
            '<header>Harbor Lane Bakery</header><main><h1>Opening soon</h1><div data-verso-empty></div></main><footer>Harbour Lane 4</footer>';
        const options = attach({ sections: [] });
        const first = document.querySelector<HTMLButtonElement>(
            `[data-verso-empty] .${ADD_CLASS}[data-first] button`,
        )!;

        expect(first).toHaveTextContent('+ Add your first section');
        first.click();
        expect(options.onAddSection).toHaveBeenCalledWith(0);
    });

    it('falls back to the main area when the theme marks no content area', () => {
        document.body.innerHTML = '<main><h1>Opening soon</h1></main>';
        attach({ sections: [] });

        expect(
            document.querySelector(`main > .${ADD_CLASS}[data-first]`),
        ).not.toBeNull();
    });

    it('keeps the toolbar in the flow on phones so it never covers "Add section"', () => {
        attach();
        const css = document.getElementById(STYLE_ID)!.textContent ?? '';

        expect(css).toMatch(
            /@media \(max-width: 640px\) \{\s*\.verso-easy-bar \{ position: static; display: none;/,
        );
    });

    it('gives a broken block a toolbar so it can be deleted', () => {
        document.body.innerHTML =
            '<main><div data-verso-block="b_weather1" data-verso-type="harbour-weather" data-verso-broken role="note">This part could not be shown.</div></main>';
        const options = attach({
            sections: [{ id: 'b_weather1', name: 'Section 1', hidden: false }],
        });

        action('b_weather1', 'delete').click();
        expect(options.onSectionAction).toHaveBeenCalledWith(
            'b_weather1',
            'delete',
        );
    });

    describe('on touch screens', () => {
        const tap = (target: Element) =>
            target.dispatchEvent(
                new MouseEvent('click', {
                    bubbles: true,
                    cancelable: true,
                    clientX: 12,
                    clientY: 12,
                }),
            );

        it('selects a tapped section, which shows its toolbar, and tells the editor', () => {
            const options = attach({ onSelect: vi.fn() });
            const list = document.querySelector('ul')!;

            tap(list);
            expect(section('b_serv0001')).toHaveClass('verso-easy-active');
            expect(options.onSelect).toHaveBeenLastCalledWith('b_serv0001');

            tap(document.querySelector('header')!);
            expect(document.querySelector('.verso-easy-active')).toBeNull();
            expect(options.onSelect).toHaveBeenLastCalledWith(null);
        });

        it('keeps the toolbar open while its buttons are tapped', () => {
            const options = attach({ onSelect: vi.fn() });

            tap(document.querySelector('ul')!);
            tap(action('b_serv0001', 'duplicate'));

            expect(section('b_serv0001')).toHaveClass('verso-easy-active');
            expect(options.onSectionAction).toHaveBeenCalledWith(
                'b_serv0001',
                'duplicate',
            );
            expect(options.onSelect).toHaveBeenCalledTimes(1);
        });

        it('selects the section again after the page is drawn again', () => {
            attach({ selected: 'b_hero0001' });

            expect(section('b_hero0001')).toHaveClass('verso-easy-active');
            expect(
                document.querySelectorAll('.verso-easy-active'),
            ).toHaveLength(1);
        });

        it('starts editing a tapped text and replaces a picture with one tap', () => {
            const options = attach();
            const heading = document.querySelector('h2')!;

            tap(heading);
            expect(heading).toHaveAttribute('contenteditable', 'true');
            expect(section('b_hero0001')).toHaveClass('verso-easy-active');

            tap(document.querySelector(`.${IMAGE_BUTTON_CLASS}`)!);
            expect(options.onImage).toHaveBeenCalledWith({
                blockId: 'b_image001',
            });
        });

        it('shows the toolbar of the selected section without hover', () => {
            attach();
            const css = document.getElementById(STYLE_ID)!.textContent ?? '';

            expect(css).toContain(
                '[data-verso-section].verso-easy-active > .verso-easy-bar { opacity: 1; pointer-events: auto; }',
            );
            expect(css).toMatch(
                /@media \(hover: hover\) \{ \[data-verso-section\]:hover > \.verso-easy-bar/,
            );
        });
    });

    describe('color warnings', () => {
        const warning =
            'Some text in this section is hard to read on its background.';

        it('labels the section in words and puts "Fix colors" first in its toolbar', () => {
            const options = attach({
                sections: [
                    {
                        id: 'b_hero0001',
                        name: 'Section 1: Fresh bread',
                        hidden: false,
                        warning,
                    },
                    {
                        id: 'b_serv0001',
                        name: 'Section 2: What we bake',
                        hidden: true,
                        warning,
                    },
                ],
            });

            expect(section('b_hero0001')).toHaveAttribute(
                'aria-description',
                warning,
            );
            expect(section('b_hero0001').textContent).toContain(
                'Colors hard to read',
            );
            expect(section('b_serv0001').textContent).toContain(
                'Hidden on your site',
            );
            expect(bar('b_hero0001').querySelector('button')).toBe(
                action('b_hero0001', FIX_COLORS_ACTION),
            );
            expect(action('b_hero0001', FIX_COLORS_ACTION)).toHaveTextContent(
                'Fix colors',
            );

            action('b_hero0001', FIX_COLORS_ACTION).click();
            expect(options.onSectionAction).toHaveBeenCalledWith(
                'b_hero0001',
                FIX_COLORS_ACTION,
            );
        });

        it('adds no warning to readable sections', () => {
            attach();

            expect(section('b_hero0001')).not.toHaveAttribute(
                'aria-description',
            );
            expect(action('b_hero0001', FIX_COLORS_ACTION)).toBeNull();
            expect(section('b_hero0001').textContent).not.toContain(
                'Colors hard to read',
            );
        });
    });

    it('gives labelled section boxes a group role, unless their tag has a role', () => {
        attach();

        expect(section('b_hero0001')).not.toHaveAttribute('role');
        expect(section('b_line0001')).toHaveAttribute('role', 'group');
    });

    it('focuses the replace button of a picture after it was replaced', () => {
        attach({ focus: { kind: 'image', blockId: 'b_gallery1', index: 1 } });

        expect(document.activeElement).toHaveAttribute(
            'aria-label',
            'Replace picture 2',
        );
    });
});

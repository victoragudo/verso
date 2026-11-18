import { currentDir, currentLocale, msg, t } from '@/lib/i18n';
import type { ImageTarget, TextField } from './page-blocks';

// Makes the page in the editor frame editable: click to edit text, buttons to replace pictures,
// a toolbar on every section and "Add section" buttons between them.
// The frame is same origin (srcdoc), so the editor works on its document directly.

export type FrameSection = {
    id: string;
    name: string;
    hidden: boolean;
    // Names of actions this section does not offer.
    skip?: string[];
    // A plain warning about the section, like colors that are hard to read. It adds a "Fix colors" button.
    warning?: string;
};

export type FrameSectionAction = {
    name: string;
    label: string;
    // Shown as the label when the section is hidden, like "Show" for "Hide".
    hiddenLabel?: string;
    // The action makes no sense for the first or the last section.
    disabledAt?: 'first' | 'last';
};

// What should get focus after the frame is drawn again, so keyboard people keep their place.
export type FrameFocus =
    | { kind: 'action'; sectionId: string; action: string }
    | { kind: 'section'; sectionId: string }
    | { kind: 'add'; index: number }
    | { kind: 'image'; blockId: string; index?: number };

export type FrameBridgeOptions = {
    sections: FrameSection[];
    actions: FrameSectionAction[];
    focus?: FrameFocus | null;
    // The section picked by a tap or click, kept when the frame is drawn again.
    selected?: string | null;
    onSelect?: (sectionId: string | null) => void;
    onText: (blockId: string, field: TextField, value: string) => void;
    onImage: (target: ImageTarget) => void;
    onSectionAction: (sectionId: string, action: string) => void;
    onAddSection: (index: number) => void;
    onEditingChange?: (editing: boolean) => void;
};

export type FrameBridge = {
    detach: () => void;
    isEditing: () => boolean;
};

// The core section actions, in toolbar order. Labels are English keys: pass them through t() before use.
export const CORE_SECTION_ACTIONS: FrameSectionAction[] = [
    { name: 'move-up', label: msg('Move up'), disabledAt: 'first' },
    { name: 'move-down', label: msg('Move down'), disabledAt: 'last' },
    { name: 'duplicate', label: msg('Duplicate') },
    { name: 'toggle-hidden', label: msg('Hide'), hiddenLabel: msg('Show') },
    { name: 'delete', label: msg('Delete') },
];

// The core section actions with labels in the admin language.
export function coreSectionActions(): FrameSectionAction[] {
    return CORE_SECTION_ACTIONS.map((action) => ({
        ...action,
        label: t(action.label),
        hiddenLabel: action.hiddenLabel ? t(action.hiddenLabel) : undefined,
    }));
}

// Editor tools in the frame speak the admin language, while the page keeps the site language.
function adminLanguage<T extends HTMLElement>(el: T): T {
    el.lang = currentLocale();
    el.dir = currentDir();

    return el;
}

// The toolbar action of a section with a color warning.
export const FIX_COLORS_ACTION = 'fix-colors';

export const STYLE_ID = 'verso-easy-edit-style';
export const BAR_CLASS = 'verso-easy-bar';
export const ADD_CLASS = 'verso-easy-add';
export const IMAGE_BUTTON_CLASS = 'verso-easy-image';
const EDITING_CLASS = 'verso-easy-editing';
const ACTIVE_CLASS = 'verso-easy-active';
const BADGE_CLASS = 'verso-easy-badge';
const BADGES_CLASS = 'verso-easy-badges';
const WRAP_ATTR = 'data-verso-wrap';
const SECTION_ATTR = 'data-verso-section';
const TEXT_ATTR = 'data-verso-text';

// Tags that already have a role of their own, so a labelled section host needs no "group" role.
const LANDMARK_TAGS = new Set([
    'SECTION',
    'ARTICLE',
    'ASIDE',
    'NAV',
    'FORM',
    'FIGURE',
    'HEADER',
    'FOOTER',
    'MAIN',
]);

// Elements that cannot hold the toolbar inside them, so the editor wraps them in a box.
const NO_CHILDREN = new Set([
    'HR',
    'IMG',
    'IFRAME',
    'VIDEO',
    'AUDIO',
    'CANVAS',
    'INPUT',
    'TEXTAREA',
    'SELECT',
    'EMBED',
    'OBJECT',
    'TABLE',
    'UL',
    'OL',
    'DL',
    'PICTURE',
    'SVG',
    'P',
    'PRE',
]);

// How each text part is found and read back. "single" parts end editing with Enter.
type TextPart = {
    el: HTMLElement;
    blockId: string;
    field: TextField;
    single: boolean;
    plain: boolean;
    read: (el: HTMLElement) => string;
};

const inner = (el: HTMLElement) => cleanHtml(el.innerHTML);

// Removes what browsers leave behind while editing: a last <br> and spaces at the ends.
export function cleanHtml(html: string): string {
    return html
        .replace(/(<br\s*\/?>\s*)+$/i, '')
        .replace(/^(\s|&nbsp;)+|(\s|&nbsp;)+$/g, '')
        .trim();
}

// The text parts of one rendered block.
export function textParts(block: HTMLElement): TextPart[] {
    const blockId = block.dataset.versoBlock ?? '';
    const type = block.dataset.versoType ?? '';

    switch (type) {
        case 'heading':
            return [
                {
                    el: block,
                    blockId,
                    field: 'content',
                    single: true,
                    plain: false,
                    read: inner,
                },
            ];
        case 'paragraph':
            return [
                {
                    el: block,
                    blockId,
                    field: 'content',
                    single: true,
                    plain: false,
                    read: (el) => `<p>${inner(el)}</p>`,
                },
            ];
        case 'list':
            return [
                {
                    el: block,
                    blockId,
                    field: 'content',
                    single: false,
                    plain: false,
                    read: inner,
                },
            ];
        case 'quote': {
            const paragraphs = [
                ...block.querySelectorAll<HTMLElement>(':scope > p'),
            ];
            const readAll = () =>
                paragraphs.map((p) => `<p>${inner(p)}</p>`).join('');
            const cite = block.querySelector<HTMLElement>(':scope > cite');

            return [
                ...paragraphs.map((p) => ({
                    el: p,
                    blockId,
                    field: 'content' as const,
                    single: false,
                    plain: false,
                    read: readAll,
                })),
                ...(cite
                    ? [
                          {
                              el: cite,
                              blockId,
                              field: 'citation' as const,
                              single: true,
                              plain: false,
                              read: inner,
                          },
                      ]
                    : []),
            ];
        }
        case 'button': {
            const link = block.querySelector<HTMLElement>('a');

            return link
                ? [
                      {
                          el: link,
                          blockId,
                          field: 'label',
                          single: true,
                          plain: true,
                          read: (el) => (el.textContent ?? '').trim(),
                      },
                  ]
                : [];
        }
        case 'image': {
            const caption = block.querySelector<HTMLElement>(
                ':scope > figcaption',
            );

            return caption
                ? [
                      {
                          el: caption,
                          blockId,
                          field: 'caption',
                          single: true,
                          plain: false,
                          read: inner,
                      },
                  ]
                : [];
        }
        default:
            return [];
    }
}

// Styles of the edit mode, scoped to the editor classes so the theme looks the same.
// On phones the toolbar takes room in the flow, so a tap that only focuses a text must not show it:
// the text would move away between the press and the release and the tap would miss it.
export const EDIT_STYLES = `
[${SECTION_ATTR}] { position: relative; }
[${SECTION_ATTR}]:focus { outline: none; }
[${SECTION_ATTR}]:focus-visible, [${SECTION_ATTR}].${ACTIVE_CLASS} { outline: 2px solid #2563eb; outline-offset: 2px; }
@media (hover: hover) { [${SECTION_ATTR}]:hover { outline: 2px dashed #93c5fd; outline-offset: 2px; } }
[data-verso-hidden] > :not(.${BAR_CLASS}):not(.${BADGES_CLASS}) { opacity: 0.45; }
[${TEXT_ATTR}] { cursor: text; border-radius: 2px; }
@media (hover: hover) { [${TEXT_ATTR}]:hover { box-shadow: 0 0 0 2px #bfdbfe; } }
[${TEXT_ATTR}]:focus-visible, .${EDITING_CLASS} { outline: 2px solid #2563eb !important; outline-offset: 3px; }
[data-verso-type="image"], .verso-gallery__item { position: relative; }
.${BAR_CLASS}, .${ADD_CLASS}, .${IMAGE_BUTTON_CLASS}, .${BADGES_CLASS} {
  font: 500 15px/1.2 Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  letter-spacing: normal; text-transform: none; text-align: start; color-scheme: light;
}
.${BAR_CLASS} {
  position: absolute; inset-block-start: -26px; inset-inline-end: 8px; z-index: 2147483000;
  display: flex; flex-wrap: wrap; gap: 4px; max-inline-size: calc(100% - 16px);
  padding: 4px; border: 1px solid #3f3f46; border-radius: 10px; background: #18181b; color: #fafafa;
  box-shadow: 0 4px 16px rgb(0 0 0 / 0.25);
  opacity: 0; pointer-events: none;
}
[${SECTION_ATTR}]:focus-within > .${BAR_CLASS}, [${SECTION_ATTR}].${ACTIVE_CLASS} > .${BAR_CLASS} { opacity: 1; pointer-events: auto; }
@media (hover: hover) { [${SECTION_ATTR}]:hover > .${BAR_CLASS} { opacity: 1; pointer-events: auto; } }
@media (prefers-reduced-motion: no-preference) { .${BAR_CLASS} { transition: opacity 120ms ease-out; } }
.${BAR_CLASS} button, .${ADD_CLASS} button, .${IMAGE_BUTTON_CLASS} {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  min-block-size: 44px; min-inline-size: 44px; margin: 0; padding: 0 14px;
  border: 1px solid transparent; border-radius: 8px; cursor: pointer;
  font: 500 15px/1.2 Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
  letter-spacing: normal; text-transform: none; box-shadow: none;
}
.${BAR_CLASS} button { background: transparent; color: inherit; }
.${BAR_CLASS} button[aria-disabled="true"] { opacity: 0.4; cursor: default; }
.${BAR_CLASS} button[data-action="delete"] { color: #fca5a5; }
.${BAR_CLASS} button[data-action="${FIX_COLORS_ACTION}"] { background: #fef3c7; color: #78350f; }
@media (hover: hover) { .${BAR_CLASS} button:not([aria-disabled="true"]):hover { background: #3f3f46; } }
.${BAR_CLASS} button:focus-visible, .${ADD_CLASS} button:focus-visible, .${IMAGE_BUTTON_CLASS}:focus-visible { outline: 3px solid #60a5fa; outline-offset: 2px; }
.${ADD_CLASS} { display: flex; align-items: center; gap: 12px; margin: 8px 0; padding: 0; clear: both; }
.${ADD_CLASS}::before, .${ADD_CLASS}::after { content: ""; flex: 1; border-block-start: 2px dashed #a1a1aa; }
.${ADD_CLASS} button { background: #ffffff; color: #1d4ed8; border-color: #93c5fd; box-shadow: 0 1px 2px rgb(0 0 0 / 0.08); }
@media (hover: hover) { .${ADD_CLASS} button:hover { background: #eff6ff; } }
.${IMAGE_BUTTON_CLASS} {
  position: absolute; inset-block-start: 8px; inset-inline-start: 8px; z-index: 2147482000;
  background: #ffffff; color: #18181b; border-color: #d4d4d8; box-shadow: 0 2px 8px rgb(0 0 0 / 0.2);
}
@media (hover: hover) { .${IMAGE_BUTTON_CLASS}:hover { background: #f4f4f5; } }
.${BADGES_CLASS} {
  position: absolute; inset-block-start: -14px; inset-inline-start: 8px; z-index: 2147482500;
  display: flex; flex-wrap: wrap; gap: 4px; max-inline-size: calc(100% - 16px); pointer-events: none;
}
.${BADGE_CLASS} { padding: 6px 10px; border-radius: 999px; background: #fef3c7; color: #78350f; font-size: 13px; }
.${BADGE_CLASS}[data-kind="contrast"] { background: #fee2e2; color: #7f1d1d; }
@media (max-width: 640px) {
  .${BAR_CLASS} { position: static; display: none; max-inline-size: none; margin: 0 0 8px; opacity: 1; pointer-events: auto; }
  [${SECTION_ATTR}]:focus-visible > .${BAR_CLASS}, [${SECTION_ATTR}]:has(:focus-visible) > .${BAR_CLASS}, [${SECTION_ATTR}].${ACTIVE_CLASS} > .${BAR_CLASS} { display: flex; }
}
@media (max-width: 640px) and (hover: hover) { [${SECTION_ATTR}]:hover > .${BAR_CLASS} { display: flex; } }
.${ADD_CLASS}[data-first] { margin: 32px 16px; }
.${ADD_CLASS}[data-first] button { min-block-size: 56px; padding: 0 24px; font-size: 17px; }
@media (forced-colors: active) {
  .${BAR_CLASS}, .${BAR_CLASS} button, .${ADD_CLASS} button, .${IMAGE_BUTTON_CLASS}, .${BADGE_CLASS} { border: 1px solid ButtonText; }
  [${SECTION_ATTR}]:focus-visible, .${EDITING_CLASS} { outline-color: Highlight; }
}
`;

// Attaches the editor to a frame document. Call detach() before the frame is drawn again.
export function attachBridge(
    doc: Document,
    options: FrameBridgeOptions,
): FrameBridge {
    const cleanups: (() => void)[] = [];
    const listen = <K extends keyof DocumentEventMap>(
        target: Document | HTMLElement,
        type: K,
        handler: (event: DocumentEventMap[K]) => void,
        capture = false,
    ) => {
        target.addEventListener(type, handler as EventListener, capture);
        cleanups.push(() =>
            target.removeEventListener(type, handler as EventListener, capture),
        );
    };
    let editing: { part: TextPart; original: string } | null = null;

    injectStyles(doc);

    const sectionEls = new Map<string, HTMLElement>();
    const marked = [...doc.querySelectorAll<HTMLElement>('[data-verso-block]')];
    const byId = new Map(marked.map((el) => [el.dataset.versoBlock ?? '', el]));

    options.sections.forEach((section, index) => {
        const found = byId.get(section.id);

        if (!found) {
            return;
        }

        const host = sectionHost(doc, found);
        sectionEls.set(section.id, host);
        host.setAttribute(SECTION_ATTR, section.id);
        host.tabIndex = 0;

        if (!host.hasAttribute('aria-label')) {
            host.setAttribute('aria-label', section.name);
        }

        if (
            !host.hasAttribute('role') &&
            !LANDMARK_TAGS.has(host.tagName.toUpperCase())
        ) {
            host.setAttribute('role', 'group');
        }

        if (section.hidden) {
            host.setAttribute('data-verso-hidden', '');
        }

        if (section.warning) {
            host.setAttribute('aria-description', section.warning);
        }

        if (section.hidden || section.warning) {
            host.prepend(badges(doc, section));
        }

        host.prepend(toolbar(doc, section, index, options));
        host.before(addButton(doc, index, options.onAddSection));

        if (index === options.sections.length - 1) {
            host.after(addButton(doc, index + 1, options.onAddSection));
        }
    });

    if (options.sections.length === 0) {
        const place =
            doc.querySelector<HTMLElement>('[data-verso-empty]') ??
            doc.querySelector<HTMLElement>('main') ??
            doc.body;
        place.append(firstSectionButton(doc, options.onAddSection));
    }

    for (const el of marked) {
        for (const part of textParts(el)) {
            part.el.setAttribute(TEXT_ATTR, part.field);
            part.el.tabIndex = 0;
            part.el.setAttribute('aria-description', t('Press Enter to edit'));
        }

        if (el.dataset.versoType === 'image' && el.querySelector('img')) {
            el.append(
                imageButton(
                    doc,
                    { blockId: el.dataset.versoBlock ?? '' },
                    options.onImage,
                ),
            );
        }

        if (el.dataset.versoType === 'gallery') {
            el.querySelectorAll<HTMLElement>('.verso-gallery__item').forEach(
                (item, index) => {
                    item.append(
                        imageButton(
                            doc,
                            { blockId: el.dataset.versoBlock ?? '', index },
                            options.onImage,
                        ),
                    );
                },
            );
        }
    }

    const partOf = (target: EventTarget | null): TextPart | null => {
        const el =
            target instanceof (doc.defaultView?.HTMLElement ?? HTMLElement)
                ? (target as HTMLElement).closest<HTMLElement>(`[${TEXT_ATTR}]`)
                : null;
        const block = el?.closest<HTMLElement>('[data-verso-block]');

        return el && block
            ? (textParts(block).find((part) => part.el === el) ?? null)
            : null;
    };

    const finish = (commit: boolean) => {
        if (!editing) {
            return;
        }

        const { part, original } = editing;
        editing = null;

        if (!commit) {
            part.el.innerHTML = original;
        }

        part.el.removeAttribute('contenteditable');
        part.el.classList.remove(EDITING_CLASS);
        options.onEditingChange?.(false);

        if (commit && part.el.innerHTML !== original) {
            options.onText(part.blockId, part.field, part.read(part.el));
        }
    };

    const start = (part: TextPart, point?: { x: number; y: number }) => {
        if (editing?.part.el === part.el) {
            return;
        }

        finish(true);
        editing = { part, original: part.el.innerHTML };
        setEditable(doc, part.el, part.plain);
        part.el.classList.add(EDITING_CLASS);
        part.el.focus();
        placeCaret(doc, part.el, point);
        options.onEditingChange?.(true);
    };

    listen(
        doc,
        'click',
        (event) => {
            const target = event.target as HTMLElement | null;

            if (
                target?.closest(
                    `.${BAR_CLASS}, .${ADD_CLASS}, .${IMAGE_BUTTON_CLASS}`,
                )
            ) {
                return;
            }

            const part = partOf(target);

            if (part) {
                event.preventDefault();
                start(part, { x: event.clientX, y: event.clientY });
            } else if (target?.closest('a[href]')) {
                event.preventDefault();
            }

            select(target?.closest<HTMLElement>(`[${SECTION_ATTR}]`) ?? null);
        },
        true,
    );

    listen(doc, 'submit', (event) => event.preventDefault(), true);

    listen(doc, 'keydown', (event) => {
        const composing = event.isComposing || event.keyCode === 229;

        if (editing) {
            if (event.key === 'Escape') {
                event.preventDefault();
                const el = editing.part.el;
                finish(false);
                el.focus();
            } else if (
                event.key === 'Enter' &&
                !event.shiftKey &&
                editing.part.single
            ) {
                event.preventDefault();

                if (!composing) {
                    const el = editing.part.el;
                    finish(true);
                    el.focus();
                }
            }

            return;
        }

        const part = partOf(event.target);

        if (part && event.key === 'Enter' && !composing) {
            event.preventDefault();
            start(part);
        }
    });

    listen(doc, 'focusout', (event) => {
        if (editing && event.target === editing.part.el) {
            finish(true);
        }
    });

    listen(doc, 'paste', (event) => {
        if (!editing) {
            return;
        }

        event.preventDefault();
        insertPlainText(doc, event.clipboardData?.getData('text/plain') ?? '');
    });

    const select = (section: HTMLElement | null) => {
        activate(doc, section);
        options.onSelect?.(section?.getAttribute(SECTION_ATTR) ?? null);
    };

    if (options.selected) {
        activate(doc, sectionEls.get(options.selected) ?? null);
    }

    const focusedSection = focusRequested(
        doc,
        options.focus ?? null,
        sectionEls,
    );

    // A section that gets focus after an action keeps its toolbar, so the next tap on it lands.
    if (focusedSection) {
        activate(doc, focusedSection);
    }

    return {
        detach() {
            finish(true);
            cleanups.forEach((cleanup) => cleanup());
        },
        isEditing: () => editing !== null,
    };
}

function injectStyles(doc: Document): void {
    if (doc.getElementById(STYLE_ID)) {
        return;
    }

    const style = doc.createElement('style');
    style.id = STYLE_ID;
    style.textContent = EDIT_STYLES;
    doc.head.append(style);
}

// The element that holds the toolbar: the section itself, or a box around elements that cannot hold children.
function sectionHost(doc: Document, el: HTMLElement): HTMLElement {
    if (!NO_CHILDREN.has(el.tagName.toUpperCase())) {
        return el;
    }

    const wrap = doc.createElement('div');
    wrap.setAttribute(WRAP_ATTR, '');
    el.before(wrap);
    wrap.append(el);

    return wrap;
}

function button(
    doc: Document,
    label: string,
    onClick: () => void,
): HTMLButtonElement {
    const el = doc.createElement('button');
    el.type = 'button';
    el.textContent = label;
    el.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (el.getAttribute('aria-disabled') !== 'true') {
            onClick();
        }
    });

    return el;
}

function toolbar(
    doc: Document,
    section: FrameSection,
    index: number,
    options: FrameBridgeOptions,
): HTMLElement {
    const bar = adminLanguage(doc.createElement('div'));
    bar.className = BAR_CLASS;
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute(
        'aria-label',
        t('Tools for {section}', { section: section.name }),
    );
    bar.setAttribute('focusgroup', 'toolbar wrap');
    const last = options.sections.length - 1;

    if (section.warning) {
        const fix = button(doc, t('Fix colors'), () =>
            options.onSectionAction(section.id, FIX_COLORS_ACTION),
        );
        fix.dataset.action = FIX_COLORS_ACTION;
        bar.append(fix);
    }

    for (const action of options.actions) {
        if (section.skip?.includes(action.name)) {
            continue;
        }

        const label =
            section.hidden && action.hiddenLabel
                ? action.hiddenLabel
                : action.label;
        const item = button(doc, label, () =>
            options.onSectionAction(section.id, action.name),
        );
        item.dataset.action = action.name;
        const disabled =
            (action.disabledAt === 'first' && index === 0) ||
            (action.disabledAt === 'last' && index === last);

        if (disabled) {
            item.setAttribute('aria-disabled', 'true');
        }

        bar.append(item);
    }

    rovingFocus(doc, bar);

    return bar;
}

// One tab stop for the toolbar and arrow keys between its buttons, where the browser has no focusgroup.
function rovingFocus(doc: Document, bar: HTMLElement): void {
    const view = doc.defaultView;

    if (view && 'focusGroup' in view.HTMLElement.prototype) {
        return;
    }

    const items = () => [...bar.querySelectorAll<HTMLButtonElement>('button')];
    items().forEach((item, index) => (item.tabIndex = index === 0 ? 0 : -1));

    bar.addEventListener('focusin', (event) => {
        items().forEach(
            (item) => (item.tabIndex = item === event.target ? 0 : -1),
        );
    });

    bar.addEventListener('keydown', (event) => {
        const list = items();
        const current = list.indexOf(event.target as HTMLButtonElement);
        const rtl = view?.getComputedStyle(bar).direction === 'rtl';
        const steps: Record<string, number> = {
            ArrowRight: rtl ? -1 : 1,
            ArrowLeft: rtl ? 1 : -1,
        };
        let next: number | null = null;

        if (event.key in steps && current >= 0) {
            next = (current + steps[event.key] + list.length) % list.length;
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = list.length - 1;
        }

        if (next !== null) {
            event.preventDefault();
            list[next].focus();
        }
    });
}

function addButton(
    doc: Document,
    index: number,
    onAdd: (index: number) => void,
): HTMLElement {
    const row = adminLanguage(doc.createElement('div'));
    row.className = ADD_CLASS;
    row.dataset.index = String(index);
    const add = button(doc, `+ ${t('Add section')}`, () => onAdd(index));
    add.setAttribute(
        'aria-label',
        index === 0
            ? t('Add a section at the top')
            : t('Add a section after section {number}', { number: index }),
    );
    row.append(add);

    return row;
}

// The big "Add your first section" button of an empty page, where the content would be.
function firstSectionButton(
    doc: Document,
    onAdd: (index: number) => void,
): HTMLElement {
    const row = addButton(doc, 0, onAdd);
    row.dataset.first = '';
    const add = row.querySelector('button');

    if (add) {
        add.textContent = `+ ${t('Add your first section')}`;
        add.removeAttribute('aria-label');
    }

    return row;
}

function imageButton(
    doc: Document,
    target: ImageTarget,
    onImage: (target: ImageTarget) => void,
): HTMLButtonElement {
    const el = adminLanguage(
        button(doc, t('Replace image'), () => onImage(target)),
    );
    el.className = IMAGE_BUTTON_CLASS;

    if (target.index !== undefined) {
        el.setAttribute(
            'aria-label',
            t('Replace picture {number}', { number: target.index + 1 }),
        );
    }

    return el;
}

// Small labels on top of a section: "Hidden on your site" and "Colors hard to read" (screen readers hear
// the warning as the section description). Words, not only colors.
function badges(doc: Document, section: FrameSection): HTMLElement {
    const wrap = adminLanguage(doc.createElement('span'));
    wrap.className = BADGES_CLASS;
    const add = (text: string, kind: string) => {
        const el = doc.createElement('span');
        el.className = BADGE_CLASS;
        el.dataset.kind = kind;
        el.textContent = text;
        wrap.append(el);

        return el;
    };

    if (section.hidden) {
        add(t('Hidden on your site'), 'hidden');
    }

    if (section.warning) {
        add(t('Colors hard to read'), 'contrast').setAttribute(
            'aria-hidden',
            'true',
        );
    }

    return wrap;
}

function activate(doc: Document, section: HTMLElement | null): void {
    doc.querySelectorAll(`.${ACTIVE_CLASS}`).forEach(
        (el) => el !== section && el.classList.remove(ACTIVE_CLASS),
    );
    section?.classList.add(ACTIVE_CLASS);
}

function setEditable(doc: Document, el: HTMLElement, plain: boolean): void {
    el.setAttribute(
        'contenteditable',
        plain && supportsPlainText(doc) ? 'plaintext-only' : 'true',
    );
}

// True when the browser knows contenteditable="plaintext-only". Others would make the text read only.
function supportsPlainText(doc: Document): boolean {
    const probe = doc.createElement('span');

    try {
        probe.contentEditable = 'plaintext-only';

        return probe.contentEditable === 'plaintext-only';
    } catch {
        return false;
    }
}

// Puts the caret where the person clicked, or at the end of the text.
function placeCaret(
    doc: Document,
    el: HTMLElement,
    point?: { x: number; y: number },
): void {
    const selection = doc.getSelection();

    if (!selection) {
        return;
    }

    const caretDoc = doc as Document & {
        caretPositionFromPoint?: (
            x: number,
            y: number,
        ) => { offsetNode: Node; offset: number } | null;
        caretRangeFromPoint?: (x: number, y: number) => Range | null;
    };
    let range: Range | null = null;

    if (point && caretDoc.caretPositionFromPoint) {
        const position = caretDoc.caretPositionFromPoint(point.x, point.y);

        if (position && el.contains(position.offsetNode)) {
            range = doc.createRange();
            range.setStart(position.offsetNode, position.offset);
        }
    } else if (point && caretDoc.caretRangeFromPoint) {
        const found = caretDoc.caretRangeFromPoint(point.x, point.y);
        range = found && el.contains(found.startContainer) ? found : null;
    }

    if (!range) {
        range = doc.createRange();
        range.selectNodeContents(el);
        range.collapse(false);
    }

    selection.removeAllRanges();
    selection.addRange(range);
}

function insertPlainText(doc: Document, text: string): void {
    const selection = doc.getSelection();

    if (!selection || selection.rangeCount === 0 || text === '') {
        return;
    }

    const range = selection.getRangeAt(0);
    range.deleteContents();
    const node = doc.createTextNode(text.replace(/\s+/g, ' '));
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
}

function focusRequested(
    doc: Document,
    focus: FrameFocus | null,
    sections: Map<string, HTMLElement>,
): HTMLElement | null {
    if (!focus) {
        return null;
    }

    let target: HTMLElement | null = null;

    if (focus.kind === 'add') {
        target = doc.querySelector<HTMLElement>(
            `.${ADD_CLASS}[data-index="${focus.index}"] button`,
        );
    } else if (focus.kind === 'image') {
        const block = [
            ...doc.querySelectorAll<HTMLElement>('[data-verso-block]'),
        ].find((el) => el.dataset.versoBlock === focus.blockId);
        target =
            block?.querySelectorAll<HTMLElement>(`.${IMAGE_BUTTON_CLASS}`)[
                focus.index ?? 0
            ] ?? null;
    } else {
        const section = sections.get(focus.sectionId) ?? null;
        target =
            focus.kind === 'action'
                ? (section?.querySelector<HTMLElement>(
                      `:scope > .${BAR_CLASS} button[data-action="${focus.action}"]`,
                  ) ?? section)
                : section;
        target?.focus();

        return section;
    }

    target?.focus();

    return null;
}

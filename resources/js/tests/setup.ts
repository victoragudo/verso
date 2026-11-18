import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';
import { setMessages } from '@/lib/i18n';

// Browser APIs that jsdom does not have but Radix and ProseMirror call.
class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
}

const emptyRect = {
    x: 0,
    y: 0,
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    width: 0,
    height: 0,
    toJSON: () => ({}),
};

// Adds a browser API to an object only when jsdom does not have it (missing or undefined).
function stub(target: object, name: string, value: unknown): void {
    if ((target as Record<string, unknown>)[name] === undefined) {
        Object.defineProperty(target, name, {
            value,
            configurable: true,
            writable: true,
        });
    }
}

const emptyRectList = {
    length: 0,
    item: () => null,
    [Symbol.iterator]: function* iterator() {},
};

stub(globalThis, 'ResizeObserver', ResizeObserverStub);
stub(window, 'matchMedia', (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
}));
stub(Element.prototype, 'scrollIntoView', () => {});
stub(Element.prototype, 'hasPointerCapture', () => false);
stub(Element.prototype, 'releasePointerCapture', () => {});
stub(Element.prototype, 'setPointerCapture', () => {});
stub(Range.prototype, 'getClientRects', () => emptyRectList);
stub(Range.prototype, 'getBoundingClientRect', () => emptyRect);
stub(document, 'elementFromPoint', () => null);

// jsdom says yes to every CSS.supports() check. Tests use the Radix select, as
// browsers without the customizable select do; select.test.tsx covers the native one.
const jsdomSupports = CSS.supports.bind(CSS);
CSS.supports = ((property: string, value?: string) =>
    property === 'appearance' && value === 'base-select'
        ? false
        : value === undefined
          ? jsdomSupports(property)
          : jsdomSupports(property, value)) as typeof CSS.supports;

afterEach(() => {
    cleanup();
    // Every test starts in English with no loaded strings.
    setMessages('en', {}, 'ltr');
    vi.unstubAllGlobals();
});

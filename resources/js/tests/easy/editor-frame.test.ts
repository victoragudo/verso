import { describe, expect, it } from 'vitest';
import { frameShortcut } from '@/components/verso/easy/editor-frame';

const press = (key: string, extra: Partial<KeyboardEvent> = {}) => ({
    key,
    metaKey: false,
    ctrlKey: true,
    shiftKey: false,
    ...extra,
});

describe('keys pressed inside the editor frame', () => {
    it('undoes, redoes and saves with Ctrl or Cmd', () => {
        expect(frameShortcut(press('z'), false)).toBe('undo');
        expect(frameShortcut(press('Z', { shiftKey: true }), false)).toBe(
            'redo',
        );
        expect(frameShortcut(press('y'), false)).toBe('redo');
        expect(frameShortcut(press('s'), false)).toBe('save');
        expect(
            frameShortcut(press('z', { ctrlKey: false, metaKey: true }), false),
        ).toBe('undo');
    });

    it('leaves typing undo to the browser while a text is edited, but still saves', () => {
        expect(frameShortcut(press('z'), true)).toBeNull();
        expect(frameShortcut(press('y'), true)).toBeNull();
        expect(frameShortcut(press('s'), true)).toBe('save');
    });

    it('ignores keys without Ctrl or Cmd', () => {
        expect(frameShortcut(press('z', { ctrlKey: false }), false)).toBeNull();
        expect(frameShortcut(press('b'), false)).toBeNull();
    });
});

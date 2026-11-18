import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { EditorManifest } from '@/builder';
import {
    BLOCKS_MIME,
    blocksFromClipboard,
    blocksToClipboard,
    inTextField,
} from '@/builder/clipboard';
import {
    recordChange,
    redo,
    startHistory,
    typingKey,
    undo,
} from '@/builder/history';
import { resetBuilder } from '@/builder/setup';
import { BlockEditor } from '@/components/verso/editor/block-editor';
import type { Block } from '@/types';

const options = { limit: 3, mergeMs: 800 };

function text(id: string, content: string): Block {
    return { id, type: 'paragraph', attrs: {}, content, children: [] };
}

const manifest: EditorManifest = {
    version: 1,
    categories: [],
    styleProperties: [],
    breakpoints: [],
    features: [],
    allowedBlocks: null,
    limits: { colors: null, fonts: null, spacing: null },
};

beforeEach(() => {
    resetBuilder();
});

describe('history', () => {
    it('groups quick typing in one block into one step', () => {
        const first = [text('b_1', '<p>F</p>')];
        let history = startHistory(first);
        history = recordChange(
            history,
            [text('b_1', '<p>Fr</p>')],
            100,
            options,
        );
        history = recordChange(
            history,
            [text('b_1', '<p>Fre</p>')],
            300,
            options,
        );
        history = recordChange(
            history,
            [text('b_1', '<p>Fresh</p>')],
            2000,
            options,
        );

        expect(history.past.map((value) => value[0].content)).toEqual([
            '<p>F</p>',
            '<p>Fre</p>',
        ]);
    });

    it('keeps structure changes as their own steps and limits the steps', () => {
        let history = startHistory([]);
        ['a', 'b', 'c', 'd', 'e'].forEach((id, index) => {
            history = recordChange(
                history,
                [...history.value, text(id, '<p></p>')],
                index * 10,
                options,
            );
        });

        expect(history.past).toHaveLength(3);
        expect(history.past[0].map((block) => block.id)).toEqual(['a', 'b']);
    });

    it('undoes and redoes, and a new change clears redo', () => {
        let history = startHistory([text('b_1', '<p>Rye</p>')]);
        history = recordChange(
            history,
            [text('b_1', '<p>Rye</p>'), text('b_2', '<p>Spelt</p>')],
            10,
            options,
        );
        history = undo(history);

        expect(history.value).toHaveLength(1);
        history = redo(history);
        expect(history.value).toHaveLength(2);
        history = undo(history);
        history = recordChange(
            history,
            [text('b_9', '<p>Oat</p>')],
            20,
            options,
        );
        expect(history.future).toEqual([]);
        expect(undo(startHistory([]))).toEqual(startHistory([]));
    });

    it('finds the typed block in nested lists', () => {
        const before: Block[] = [
            {
                id: 'b_s',
                type: 'section',
                attrs: {},
                content: '',
                children: [text('b_in', '<p>A</p>')],
            },
        ];
        const after: Block[] = [
            { ...before[0], children: [text('b_in', '<p>AB</p>')] },
        ];

        expect(typingKey(before, after)).toBe('b_in');
        expect(typingKey(before, [])).toBeNull();
        expect(
            typingKey(before, [{ ...before[0], attrs: { tag: 'nav' } }]),
        ).toBeNull();
    });
});

describe('clipboard', () => {
    it('copies blocks as JSON and pastes them with new ids', () => {
        const json = blocksToClipboard([
            {
                id: 'b_s',
                type: 'section',
                attrs: { tag: 'aside' },
                content: '',
                children: [text('b_p', '<p>Gluten free</p>')],
            },
        ]);
        const pasted = blocksFromClipboard(json)!;

        expect(JSON.parse(json)).toMatchObject({ verso: 'blocks', version: 1 });
        expect(pasted[0]).toMatchObject({
            type: 'section',
            attrs: { tag: 'aside' },
        });
        expect(pasted[0].id).not.toBe('b_s');
        expect(pasted[0].children?.[0].id).not.toBe('b_p');
        expect(pasted[0].children?.[0].content).toBe('<p>Gluten free</p>');
    });

    it('ignores text that is not copied blocks', () => {
        expect(blocksFromClipboard('Just some words')).toBeNull();
        expect(
            blocksFromClipboard('{"verso":"blocks","blocks":[{"type":3}]}'),
        ).toBeNull();
        expect(blocksFromClipboard('{"blocks":[]}')).toBeNull();
    });

    it('knows text fields', () => {
        const input = document.createElement('input');
        const button = document.createElement('button');

        expect(inTextField(input)).toBe(true);
        expect(inTextField(button)).toBe(false);
        expect(inTextField(null)).toBe(false);
    });
});

describe('BlockEditor history and clipboard', () => {
    let latest: Block[] = [];

    function Harness() {
        const [blocks, setBlocks] = useState<Block[]>([
            {
                id: 'b_sep',
                type: 'separator',
                attrs: {},
                content: '',
                children: [],
            },
            {
                id: 'b_btn',
                type: 'button',
                attrs: { label: 'Order', url: '/order', style: 'primary' },
                content: '',
                children: [],
            },
        ]);
        latest = blocks;

        return (
            <BlockEditor
                value={blocks}
                onChange={setBlocks}
                editor={manifest}
            />
        );
    }

    it('undoes and redoes a block move from the toolbar and the keyboard', async () => {
        render(<Harness />);
        const undoButton = screen.getByRole('button', { name: 'Undo' });

        expect(undoButton).toBeDisabled();
        await userEvent.click(
            screen.getByRole('button', { name: 'Button block options' }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', { name: /Move up/ }),
        );
        expect(latest.map((block) => block.id)).toEqual(['b_btn', 'b_sep']);

        await userEvent.click(undoButton);
        expect(latest.map((block) => block.id)).toEqual(['b_sep', 'b_btn']);
        fireEvent.keyDown(screen.getByRole('button', { name: 'Redo' }), {
            key: 'z',
            ctrlKey: true,
            metaKey: false,
            shiftKey: true,
        });
        expect(latest.map((block) => block.id)).toEqual(['b_btn', 'b_sep']);
    });

    it('copies the selected block and pastes it as a new block', () => {
        render(<Harness />);
        const handle = screen.getByRole('button', {
            name: 'Drag to move Button block',
        });
        fireEvent.pointerDown(handle);
        const store = new Map<string, string>();
        const clipboardData = {
            setData: (type: string, value: string) => store.set(type, value),
            getData: (type: string) => store.get(type) ?? '',
        };
        fireEvent.copy(handle, { clipboardData });

        expect(JSON.parse(store.get(BLOCKS_MIME)!).blocks[0].id).toBe('b_btn');
        fireEvent.paste(handle, { clipboardData });
        expect(latest.map((block) => block.type)).toEqual([
            'separator',
            'button',
            'button',
        ]);
        expect(latest[2].id).not.toBe('b_btn');
    });

    it('copies a block from its menu with the system clipboard', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        const readText = vi
            .fn()
            .mockResolvedValue(
                blocksToClipboard([text('b_x', '<p>From another page</p>')]),
            );
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText, readText },
            configurable: true,
        });
        render(<Harness />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Separator block options' }),
        );
        await userEvent.click(
            screen.getByRole('menuitem', { name: /Copy block/ }),
        );

        expect(JSON.parse(writeText.mock.calls[0][0]).blocks[0].id).toBe(
            'b_sep',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Separator block options' }),
        );
        await act(async () => {
            await userEvent.click(
                screen.getByRole('menuitem', {
                    name: /Paste after this block/,
                }),
            );
        });
        expect(latest.map((block) => block.type)).toEqual([
            'separator',
            'paragraph',
            'button',
        ]);
    });
});

import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/react';
import { useRef } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { OverflowToolbar } from '@/components/verso/editor/overflow-toolbar';
import { visibleGroupKeys } from '@/components/verso/editor/toolbar-overflow';

const groups = (hideOrders: number[]) =>
    hideOrders.map((hideOrder, index) => ({
        key: `group-${index}`,
        width: 100,
        hideOrder,
    }));

describe('visibleGroupKeys', () => {
    it('shows every group when they fit', () => {
        expect(
            visibleGroupKeys({
                available: 400,
                groups: groups([0, 1, 2]),
                moreWidth: 36,
                gap: 4,
            }),
        ).toEqual(['group-0', 'group-1', 'group-2']);
    });

    it('keeps room for the More button and hides the last groups first', () => {
        expect(
            visibleGroupKeys({
                available: 250,
                groups: groups([0, 1, 2]),
                moreWidth: 36,
                gap: 4,
            }),
        ).toEqual(['group-0', 'group-1']);
        expect(
            visibleGroupKeys({
                available: 200,
                groups: groups([0, 1, 2]),
                moreWidth: 36,
                gap: 4,
            }),
        ).toEqual(['group-0']);
    });

    it('hides groups by their order, so important tools stay in the row', () => {
        expect(
            visibleGroupKeys({
                available: 250,
                groups: groups([1, 2, 0]),
                moreWidth: 36,
                gap: 4,
            }),
        ).toEqual(['group-0', 'group-2']);
    });

    it('can move every group to the More menu', () => {
        expect(
            visibleGroupKeys({
                available: 60,
                groups: groups([0, 1]),
                moreWidth: 36,
                gap: 4,
            }),
        ).toEqual([]);
    });
});

function Toolbar() {
    const ref = useRef<HTMLDivElement>(null);

    return (
        <OverflowToolbar
            toolbarRef={ref}
            label="Editor"
            groups={['Undo', 'Bold', 'Insert image'].map((name) => ({
                key: name,
                label: name === 'Insert image' ? 'Blocks' : undefined,
                node: (
                    <>
                        <span role="separator" />
                        <button type="button">{name}</button>
                    </>
                ),
            }))}
        />
    );
}

describe('OverflowToolbar', () => {
    afterEach(() => vi.restoreAllMocks());

    it('moves the groups that do not fit into the More menu instead of clipping them', () => {
        vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(
            250,
        );
        vi.spyOn(
            HTMLElement.prototype,
            'getBoundingClientRect',
        ).mockReturnValue({
            width: 100,
            height: 32,
        } as DOMRect);

        render(<Toolbar />);

        const toolbar = screen.getByRole('toolbar', { name: 'Editor' });
        expect(toolbar).toHaveAttribute('focusgroup', 'toolbar wrap');
        expect(
            screen.getByRole('button', { name: 'Undo' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Bold' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Insert image' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'More tools' }),
        ).toBeInTheDocument();
    });

    it('shows no More button when everything fits', () => {
        vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(
            800,
        );
        vi.spyOn(
            HTMLElement.prototype,
            'getBoundingClientRect',
        ).mockReturnValue({
            width: 100,
            height: 32,
        } as DOMRect);

        render(<Toolbar />);

        expect(
            screen.getByRole('button', { name: 'Insert image' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'More tools' }),
        ).not.toBeInTheDocument();
    });

    it('shows hidden groups as titled sections without bar separators', async () => {
        vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(
            250,
        );
        vi.spyOn(
            HTMLElement.prototype,
            'getBoundingClientRect',
        ).mockReturnValue({
            width: 100,
            height: 32,
        } as DOMRect);

        render(<Toolbar />);
        await userEvent.click(
            screen.getByRole('button', { name: 'More tools' }),
        );

        const section = await screen.findByRole('region', { name: 'Blocks' });
        expect(section).toHaveTextContent('Blocks');
        expect(
            screen.getByRole('button', { name: 'Insert image' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('group', { name: 'More tools' })).toHaveClass(
            '[&_[role=separator]]:hidden',
        );
    });
});

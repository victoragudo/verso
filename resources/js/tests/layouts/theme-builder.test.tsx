import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { conditionSummary } from '@/components/verso/layouts/conditions';
import { ConditionsEditor } from '@/components/verso/layouts/conditions-editor';
import { LayoutSettingsFields } from '@/components/verso/layouts/layout-settings';
import type { LayoutConditionDefinition } from '@/components/verso/layouts/types';
import { pickOption } from '@/tests/pick-option';

const definitions: LayoutConditionDefinition[] = [
    { key: 'everywhere', label: 'Everywhere', options: null },
    {
        key: 'content_type',
        label: 'Content type',
        options: [
            { value: 'post', label: 'Posts' },
            { value: 'page', label: 'Pages' },
        ],
    },
    {
        key: 'term',
        label: 'Category or tag',
        options: [{ value: '7', label: 'Recipes (Categories)' }],
    },
];

describe('conditionSummary', () => {
    it('says where a layout shows in one line', () => {
        expect(
            conditionSummary(
                [
                    { type: 'content_type', value: 'post', exclude: false },
                    { type: 'term', value: '7', exclude: true },
                ],
                definitions,
            ),
        ).toBe(
            'Content type: Posts, except Category or tag: Recipes (Categories)',
        );
        expect(conditionSummary([], definitions)).toBe(
            'Nowhere yet. Add a rule.',
        );
    });
});

describe('ConditionsEditor', () => {
    it('adds, changes and removes rules', async () => {
        const onChange = vi.fn();
        const { rerender } = render(
            <ConditionsEditor
                rules={[]}
                definitions={definitions}
                onChange={onChange}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Add a rule' }));
        expect(onChange).toHaveBeenLastCalledWith([
            { type: 'everywhere', value: '', exclude: false },
        ]);

        rerender(
            <ConditionsEditor
                rules={[{ type: 'everywhere', value: '', exclude: false }]}
                definitions={definitions}
                onChange={onChange}
            />,
        );
        await pickOption('Show on', 'Content type');
        expect(onChange).toHaveBeenLastCalledWith([
            { type: 'content_type', value: 'post', exclude: false },
        ]);

        fireEvent.click(screen.getByLabelText('Except here'));
        expect(onChange).toHaveBeenLastCalledWith([
            { type: 'everywhere', value: '', exclude: true },
        ]);

        fireEvent.click(screen.getByRole('button', { name: 'Remove rule 1' }));
        expect(onChange).toHaveBeenLastCalledWith([]);
    });
});

describe('LayoutSettingsFields', () => {
    it('shows only the popup options that apply to its kind and trigger', () => {
        const onChange = vi.fn();
        render(
            <LayoutSettingsFields
                kind="popup"
                value={{
                    type: 'modal',
                    trigger: 'delay',
                    delay: 5,
                    frequency: 'days',
                    days: 7,
                    devices: ['computer', 'phone'],
                }}
                onChange={onChange}
            />,
        );

        expect(screen.getByLabelText('Seconds')).toHaveValue(5);
        expect(
            screen.queryByLabelText('Percent of the page'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Accept button text'),
        ).not.toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Tablet'));
        expect(onChange).toHaveBeenLastCalledWith(
            expect.objectContaining({
                devices: ['computer', 'phone', 'tablet'],
            }),
        );
    });

    it('offers the header scroll options', () => {
        const onChange = vi.fn();
        render(
            <LayoutSettingsFields
                kind="header"
                value={{ sticky: false, scrollEffect: 'none' }}
                onChange={onChange}
            />,
        );

        expect(
            screen.queryByLabelText('After how many pixels'),
        ).not.toBeInTheDocument();
        fireEvent.click(
            screen.getByLabelText('Stay at the top while scrolling'),
        );
        expect(onChange).toHaveBeenLastCalledWith({
            sticky: true,
            scrollEffect: 'none',
        });
    });
});

import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { FieldBuilderList } from '@/components/verso/fields/field-builder';
import type { BuilderChoices } from '@/components/verso/fields/field-builder';
import { LocationEditor } from '@/components/verso/fields/location-editor';
import type {
    FieldDefinition,
    LocationRule,
} from '@/components/verso/fields/types';
import FieldGroupEdit from '@/pages/admin/fields/edit';
import FieldGroupsIndex from '@/pages/admin/fields/index';
import type { FieldGroupRow } from '@/pages/admin/fields/index';

// The shared mock has no useForm, so this file adds a small one kept in React state.
vi.mock('@inertiajs/react', async () => {
    const base = (await import('@/tests/mocks/inertia')).inertiaMock;
    const react = await import('react');

    return {
        ...base,
        useForm: <T extends Record<string, unknown>>(initial: T) => {
            const [data, setState] = react.useState(initial);
            const setData = (
                key: keyof T | ((current: T) => T),
                value?: unknown,
            ) =>
                setState((current) =>
                    typeof key === 'function'
                        ? key(current)
                        : { ...current, [key]: value },
                );

            return {
                data,
                setData,
                errors: {},
                processing: false,
                post: vi.fn(),
                put: vi.fn(),
                reset: vi.fn(),
                transform: vi.fn(),
            };
        },
    };
});

const choices: BuilderChoices = {
    fieldTypes: [
        { value: 'text', label: 'Text' },
        { value: 'select', label: 'Select' },
        { value: 'repeater', label: 'Repeater' },
        { value: 'group', label: 'Group' },
        { value: 'entry_relation', label: 'Entry relation' },
    ],
    contentTypes: [
        { value: 'post', label: 'Post' },
        { value: 'page', label: 'Page' },
    ],
    taxonomies: [{ value: 'category', label: 'Category' }],
    roles: [{ value: 'editor', label: 'Editor' }],
};

const subtitle: FieldDefinition = {
    name: 'subtitle',
    label: 'Subtitle',
    type: 'text',
    required: false,
};
const steps: FieldDefinition = {
    name: 'steps',
    label: 'Steps',
    type: 'repeater',
    required: false,
    fields: [
        {
            name: 'instruction',
            label: 'Instruction',
            type: 'text',
            required: true,
        },
    ],
};

function BuilderHarness({
    initial,
    onFields,
    maxDepth = 3,
}: {
    initial: FieldDefinition[];
    onFields: (fields: FieldDefinition[]) => void;
    maxDepth?: number;
}) {
    const [fields, setFields] = useState(initial);

    return (
        <FieldBuilderList
            fields={fields}
            onChange={(next) => {
                setFields(next);
                onFields(next);
            }}
            choices={choices}
            maxDepth={maxDepth}
        />
    );
}

describe('FieldBuilderList', () => {
    it('adds a field of the picked type and names it from its label', async () => {
        const user = userEvent.setup();
        const onFields = vi.fn();
        render(<BuilderHarness initial={[subtitle]} onFields={onFields} />);

        await user.click(screen.getByRole('button', { name: 'Add field' }));
        expect(onFields).toHaveBeenLastCalledWith([
            subtitle,
            expect.objectContaining({ name: 'field_2', type: 'text' }),
        ]);

        const label = screen.getByLabelText('Label');
        await user.clear(label);
        await user.type(label, 'Reading time');

        expect(onFields).toHaveBeenLastCalledWith([
            subtitle,
            expect.objectContaining({
                label: 'Reading time',
                name: 'reading_time',
            }),
        ]);
    });

    it('moves and removes fields', async () => {
        const user = userEvent.setup();
        const onFields = vi.fn();
        render(
            <BuilderHarness initial={[subtitle, steps]} onFields={onFields} />,
        );

        await user.click(screen.getByRole('button', { name: 'Move Steps up' }));
        expect(onFields).toHaveBeenLastCalledWith([steps, subtitle]);

        await user.click(
            screen.getByRole('button', { name: 'Remove Subtitle' }),
        );
        expect(onFields).toHaveBeenLastCalledWith([steps]);
    });

    it('edits nested fields of a repeater', async () => {
        const user = userEvent.setup();
        const onFields = vi.fn();
        render(<BuilderHarness initial={[steps]} onFields={onFields} />);

        await user.click(
            screen.getByRole('button', { name: /Steps/, expanded: false }),
        );
        expect(screen.getByText('Fields of each row')).toBeInTheDocument();

        const addButtons = screen.getAllByRole('button', { name: 'Add field' });
        await user.click(addButtons[0]);

        const last = onFields.mock.lastCall?.[0] as FieldDefinition[];
        expect(last[0].fields?.map((field) => field.name)).toEqual([
            'instruction',
            'field_2',
        ]);
    });

    it('hides nesting types at the deepest level', async () => {
        const user = userEvent.setup();
        render(
            <BuilderHarness
                initial={[steps]}
                onFields={vi.fn()}
                maxDepth={2}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: /Steps/, expanded: false }),
        );
        const nestedType = screen.getAllByRole('combobox', {
            name: 'Type of the new field',
        })[0];
        await user.click(nestedType);

        expect(
            screen.queryByRole('option', { name: 'Repeater' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('option', { name: 'Text' }),
        ).toBeInTheDocument();
    });

    it('edits select options one per line', async () => {
        const user = userEvent.setup();
        const onFields = vi.fn();
        const season: FieldDefinition = {
            name: 'season',
            label: 'Season',
            type: 'select',
            required: false,
            options: [{ value: 'spring', label: 'Spring' }],
        };
        render(<BuilderHarness initial={[season]} onFields={onFields} />);

        await user.click(
            screen.getByRole('button', { name: /Season/, expanded: false }),
        );
        const options = screen.getByLabelText('Options');
        await user.clear(options);
        await user.type(options, 'spring : Spring{Enter}autumn : Autumn');

        const last = onFields.mock.lastCall?.[0] as FieldDefinition[];
        expect(last[0].options).toEqual([
            { value: 'spring', label: 'Spring' },
            { value: 'autumn', label: 'Autumn' },
        ]);
    });
});

describe('LocationEditor', () => {
    function LocationHarness({
        onRules,
    }: {
        onRules: (rules: LocationRule[][]) => void;
    }) {
        const [rules, setRules] = useState<LocationRule[][]>([
            [{ param: 'content_type', operator: '==', value: 'post' }],
        ]);

        return (
            <LocationEditor
                value={rules}
                onChange={(next) => {
                    setRules(next);
                    onRules(next);
                }}
                choices={{
                    locationParams: [
                        { value: 'content_type', label: 'Content type' },
                        { value: 'page_template', label: 'Page template' },
                    ],
                    contentTypes: choices.contentTypes,
                    roles: choices.roles,
                    terms: [],
                }}
            />
        );
    }

    it('adds and rules and or rule sets, and removes empty sets', async () => {
        const user = userEvent.setup();
        const onRules = vi.fn();
        render(<LocationHarness onRules={onRules} />);

        await user.click(screen.getByRole('button', { name: 'And' }));
        expect(onRules.mock.lastCall?.[0][0]).toHaveLength(2);

        await user.click(
            screen.getByRole('button', { name: 'Add an "or" rule set' }),
        );
        expect(onRules.mock.lastCall?.[0]).toHaveLength(2);
        expect(screen.getByText('or')).toBeInTheDocument();

        const secondSet = screen.getByRole('group', { name: 'Rule set 2' });
        await user.click(
            within(secondSet).getByRole('button', {
                name: 'Remove rule 1 of set 2',
            }),
        );
        expect(onRules.mock.lastCall?.[0]).toHaveLength(1);
    });
});

describe('field group pages', () => {
    const rows: FieldGroupRow[] = [
        {
            id: 3,
            key: 'book_review',
            title: 'Book review',
            description: '',
            source: 'database',
            position: 'main',
            active: true,
            fields: 4,
            location: 'Content type is Post',
        },
        {
            id: null,
            key: 'shop_extras',
            title: 'Shop extras',
            description: '',
            source: 'code',
            position: 'sidebar',
            active: false,
            fields: 2,
            location: 'Content type is Product',
        },
    ];

    it('lists groups with their source and status', () => {
        render(
            <FieldGroupsIndex
                groups={rows}
                problems={{ 'broken.json': 'The file is not valid JSON.' }}
                importMaxKb={512}
            />,
        );

        expect(screen.getAllByText('Book review').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Plugin code').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Off').length).toBeGreaterThan(0);
        expect(screen.getByText('broken.json')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /New field group/ }),
        ).toBeInTheDocument();
    });

    it('shows an empty state without groups', () => {
        render(
            <FieldGroupsIndex groups={[]} problems={{}} importMaxKb={512} />,
        );

        expect(screen.getByText('No field groups yet.')).toBeInTheDocument();
    });

    it('fills the key from the title of a new group', async () => {
        const user = userEvent.setup();
        render(
            <FieldGroupEdit
                group={null}
                fieldTypes={choices.fieldTypes}
                positions={[
                    { value: 'main', label: 'Main column' },
                    { value: 'sidebar', label: 'Sidebar' },
                ]}
                locationParams={[
                    { value: 'content_type', label: 'Content type' },
                ]}
                contentTypes={choices.contentTypes}
                taxonomies={choices.taxonomies}
                roles={choices.roles}
                terms={[]}
                maxDepth={4}
            />,
        );

        await user.type(screen.getByLabelText('Title'), 'Event details');

        expect(screen.getByLabelText('Key')).toHaveValue('event_details');
        expect(
            screen.getByRole('button', { name: 'Create group' }),
        ).toBeInTheDocument();
    });
});

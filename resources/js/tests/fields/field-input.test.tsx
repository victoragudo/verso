import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { FieldGroupsPanel } from '@/components/verso/fields/field-groups-panel';
import { FieldList } from '@/components/verso/fields/field-input';
import {
    itemsById,
    mediaById,
    searchItems,
} from '@/components/verso/fields/lookup';
import type {
    FieldDefinition,
    FieldGroupDefinition,
    FieldValues,
} from '@/components/verso/fields/types';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

vi.mock('@/components/verso/fields/lookup', () => ({
    searchItems: vi.fn(),
    itemsById: vi.fn(),
    mediaById: vi.fn(),
}));

vi.mock('@/components/verso/media/media-api', () => ({
    fetchLibrary: vi.fn().mockResolvedValue({
        data: [],
        meta: { current_page: 1, last_page: 1, total: 0 },
    }),
    uploadMedia: vi.fn(),
    acceptAttribute: () => 'image/*',
}));

const reviewFields: FieldDefinition[] = [
    {
        name: 'book_title',
        label: 'Book title',
        type: 'text',
        required: true,
        instructions: 'As printed on the cover.',
    },
    { name: 'pages', label: 'Pages', type: 'number', required: false },
    { name: 'summary', label: 'Summary', type: 'textarea', required: false },
    {
        name: 'has_audio',
        label: 'Has an audio book',
        type: 'toggle',
        required: false,
    },
    {
        name: 'narrator',
        label: 'Narrator',
        type: 'text',
        required: false,
        condition: { field: 'has_audio', operator: '==', value: true },
    },
    {
        name: 'format',
        label: 'Format',
        type: 'radio',
        required: false,
        options: [
            { value: 'hardcover', label: 'Hardcover' },
            { value: 'paperback', label: 'Paperback' },
        ],
    },
    {
        name: 'moods',
        label: 'Moods',
        type: 'checkbox',
        required: false,
        options: [
            { value: 'calm', label: 'Calm' },
            { value: 'dark', label: 'Dark' },
            { value: 'funny', label: 'Funny' },
        ],
    },
    { name: 'accent', label: 'Accent', type: 'color', required: false },
];

// Keeps the values in state, like the entry editor does.
function Harness({
    fields,
    initial = {},
    errors = {},
    onValues,
}: {
    fields: FieldDefinition[];
    initial?: FieldValues;
    errors?: Record<string, string>;
    onValues?: (values: FieldValues) => void;
}) {
    const [values, setValues] = useState<FieldValues>(initial);

    return (
        <FieldList
            fields={fields}
            values={values}
            onChange={(next) => {
                setValues(next);
                onValues?.(next);
            }}
            errors={errors}
            path="meta.fields"
        />
    );
}

beforeEach(() => {
    vi.mocked(searchItems).mockReset();
    vi.mocked(itemsById).mockReset().mockResolvedValue([]);
    vi.mocked(mediaById).mockReset().mockResolvedValue([]);
});

describe('FieldList', () => {
    it('renders labelled inputs for each type with instructions', () => {
        render(<Harness fields={reviewFields} />);

        expect(screen.getByLabelText(/Book title/)).toHaveAttribute(
            'type',
            'text',
        );
        expect(screen.getByLabelText('Pages')).toHaveAttribute(
            'type',
            'number',
        );
        expect(
            screen.getByText('As printed on the cover.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: 'Paperback' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', { name: 'Funny' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Accent picker')).toBeInTheDocument();
    });

    it('sends typed values as the user edits', async () => {
        const user = userEvent.setup();
        const onValues = vi.fn();
        render(<Harness fields={reviewFields} onValues={onValues} />);

        await user.type(screen.getByLabelText('Pages'), '312');
        expect(onValues).toHaveBeenLastCalledWith(
            expect.objectContaining({ pages: 312 }),
        );

        await user.click(screen.getByRole('radio', { name: 'Hardcover' }));
        expect(onValues).toHaveBeenLastCalledWith(
            expect.objectContaining({ format: 'hardcover' }),
        );

        await user.click(screen.getByRole('checkbox', { name: 'Funny' }));
        await user.click(screen.getByRole('checkbox', { name: 'Calm' }));
        expect(onValues).toHaveBeenLastCalledWith(
            expect.objectContaining({ moods: ['calm', 'funny'] }),
        );
    });

    it('shows a field only when its condition passes', async () => {
        const user = userEvent.setup();
        render(<Harness fields={reviewFields} />);

        expect(screen.queryByLabelText('Narrator')).not.toBeInTheDocument();

        await user.click(
            screen.getByRole('checkbox', { name: 'Has an audio book' }),
        );
        expect(screen.getByLabelText('Narrator')).toBeInTheDocument();

        await user.click(
            screen.getByRole('checkbox', { name: 'Has an audio book' }),
        );
        expect(screen.queryByLabelText('Narrator')).not.toBeInTheDocument();
    });

    it('shows server errors under the matching input', () => {
        render(
            <Harness
                fields={reviewFields}
                errors={{
                    'meta.fields.book_title':
                        'The Book title field is required.',
                }}
            />,
        );

        expect(
            screen.getByText('The Book title field is required.'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText(/Book title/)).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });
});

describe('repeater fields', () => {
    const quotes: FieldDefinition = {
        name: 'quotes',
        label: 'Quotes',
        type: 'repeater',
        required: false,
        max_rows: 2,
        fields: [
            { name: 'text', label: 'Quote', type: 'text', required: true },
            { name: 'page', label: 'Page', type: 'number', required: false },
        ],
    };

    it('adds, moves and removes rows', async () => {
        const user = userEvent.setup();
        const onValues = vi.fn();
        render(
            <Harness
                fields={[quotes]}
                initial={{
                    quotes: [{ text: 'To live is to be other.', page: 12 }],
                }}
                onValues={onValues}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Add row' }));
        expect(onValues).toHaveBeenLastCalledWith({
            quotes: [
                { text: 'To live is to be other.', page: 12 },
                { text: null, page: null },
            ],
        });
        expect(
            screen.queryByRole('button', { name: 'Add row' }),
        ).not.toBeInTheDocument();

        const second = screen.getByRole('group', { name: 'Quotes row 2' });
        await user.type(within(second).getByLabelText(/Quote/), 'Saudade');
        await user.click(screen.getByRole('button', { name: 'Move row 2 up' }));
        expect(onValues).toHaveBeenLastCalledWith({
            quotes: [
                { text: 'Saudade', page: null },
                { text: 'To live is to be other.', page: 12 },
            ],
        });

        await user.click(screen.getByRole('button', { name: 'Remove row 1' }));
        expect(onValues).toHaveBeenLastCalledWith({
            quotes: [{ text: 'To live is to be other.', page: 12 }],
        });
    });

    it('shows errors of nested rows by their path', () => {
        render(
            <Harness
                fields={[quotes]}
                initial={{ quotes: [{ text: '' }] }}
                errors={{
                    'meta.fields.quotes.0.text': 'The Quote field is required.',
                }}
            />,
        );

        expect(
            screen.getByText('The Quote field is required.'),
        ).toBeInTheDocument();
    });

    it('edits fields inside a group', async () => {
        const user = userEvent.setup();
        const onValues = vi.fn();
        const venue: FieldDefinition = {
            name: 'venue',
            label: 'Venue',
            type: 'group',
            required: false,
            fields: [
                { name: 'city', label: 'City', type: 'text', required: false },
            ],
        };
        render(<Harness fields={[venue]} onValues={onValues} />);

        await user.type(screen.getByLabelText('City'), 'Évora');
        expect(onValues).toHaveBeenLastCalledWith({ venue: { city: 'Évora' } });
    });
});

describe('relation fields', () => {
    it('searches on the server and adds picked items', async () => {
        vi.mocked(searchItems).mockResolvedValue([
            { value: '41', label: 'Three days in Porto', hint: 'Published' },
        ]);
        vi.mocked(itemsById).mockResolvedValue([
            { value: '7', label: 'Lisbon by tram' },
        ]);
        const user = userEvent.setup();
        const onValues = vi.fn();
        const related: FieldDefinition = {
            name: 'see_also',
            label: 'See also',
            type: 'entry_relation',
            required: false,
            multiple: true,
            content_types: ['post'],
        };
        render(
            <Harness
                fields={[related]}
                initial={{ see_also: [7] }}
                onValues={onValues}
            />,
        );

        expect(await screen.findByText('Lisbon by tram')).toBeInTheDocument();
        expect(itemsById).toHaveBeenCalledWith(
            'entry',
            [7],
            expect.objectContaining({ types: ['post'] }),
        );

        await user.click(screen.getByRole('combobox', { name: 'See also' }));
        await user.keyboard('porto');
        await waitFor(() =>
            expect(searchItems).toHaveBeenCalledWith(
                'entry',
                'porto',
                expect.anything(),
                expect.anything(),
            ),
        );
        await user.click(
            await screen.findByRole('option', { name: /Three days in Porto/ }),
        );

        expect(onValues).toHaveBeenLastCalledWith({ see_also: [7, 41] });

        await user.click(
            screen.getByRole('button', { name: 'Remove Lisbon by tram' }),
        );
        expect(onValues).toHaveBeenLastCalledWith({ see_also: [41] });
    });
});

describe('media fields', () => {
    it('loads picked images and removes them', async () => {
        vi.mocked(mediaById).mockResolvedValue([
            {
                id: 5,
                url: '/storage/alfama.jpg',
                filename: 'alfama.jpg',
                mime_type: 'image/jpeg',
                size: 1000,
                width: 800,
                height: 600,
                alt: 'Alfama roofs',
                caption: null,
                path: '2026/11/alfama.jpg',
                type: 'image',
                thumbnail_url: '/img/alfama.jpg?w=200',
                uploaded_by: null,
                created_at: null,
                can: { update: false, delete: false },
            },
        ]);
        const user = userEvent.setup();
        const onValues = vi.fn();
        const hero: FieldDefinition = {
            name: 'hero',
            label: 'Hero',
            type: 'image',
            required: false,
        };
        render(
            <Harness
                fields={[hero]}
                initial={{ hero: 5 }}
                onValues={onValues}
            />,
        );

        expect(await screen.findByAltText('Alfama roofs')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Choose image' }),
        ).not.toBeInTheDocument();

        await user.click(
            screen.getByRole('button', { name: 'Remove alfama.jpg' }),
        );
        expect(onValues).toHaveBeenLastCalledWith({ hero: null });
        expect(
            screen.getByRole('button', { name: 'Choose image' }),
        ).toBeInTheDocument();
    });
});

describe('FieldGroupsPanel', () => {
    const groups: FieldGroupDefinition[] = [
        {
            key: 'review',
            title: 'Book review',
            description: 'Facts about the book.',
            position: 'main',
            order: 0,
            active: true,
            location: [],
            fields: [reviewFields[0]],
        },
        {
            key: 'shop',
            title: 'Where to buy',
            description: '',
            position: 'sidebar',
            order: 0,
            active: true,
            location: [],
            fields: [reviewFields[1]],
        },
    ];

    it('renders only the groups of its position as section cards', () => {
        render(
            <FieldGroupsPanel
                groups={groups}
                position="sidebar"
                values={{}}
                onChange={vi.fn()}
                errors={{}}
            />,
        );

        expect(screen.getByText('Where to buy')).toBeInTheDocument();
        expect(screen.queryByText('Book review')).not.toBeInTheDocument();
        expect(screen.getByLabelText('Pages')).toBeInTheDocument();
    });
});

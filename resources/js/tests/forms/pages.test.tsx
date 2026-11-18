import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { FormField, FormSettings } from '@/components/verso/forms/types';
import FormBuilder from '@/pages/admin/plugins/forms/edit';
import FormsIndex from '@/pages/admin/plugins/forms/index';
import SubmissionShow from '@/pages/admin/plugins/forms/submission';
import SubmissionsIndex from '@/pages/admin/plugins/forms/submissions';
import { router } from '@/tests/mocks/inertia';

const formMocks = vi.hoisted(() => ({ put: vi.fn(), post: vi.fn() }));

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
                post: (url: string) => formMocks.post(url, data),
                put: (url: string) => formMocks.put(url, data),
                reset: vi.fn(),
            };
        },
    };
});

afterEach(() => {
    vi.clearAllMocks();
});

const settings: FormSettings = {
    submit_label: 'Send',
    success_message: 'Thank you!',
    redirect_url: '',
    notify_emails: ['orders@padaria-lumiar.pt'],
    notify_subject: 'New message from {form_title}',
    notify_body: '{all_fields}',
    confirmation_enabled: false,
    confirmation_subject: 'We got your message',
    confirmation_body: 'Thanks',
};

const nameField: FormField = {
    id: 'f_name',
    type: 'text',
    name: 'name',
    label: 'Your name',
    placeholder: '',
    help: '',
    required: true,
    width: 'half',
    options: [],
    min_length: null,
    max_length: null,
    accept: [],
    max_kb: null,
    value: '',
};

const fieldTypes = [
    { value: 'text', label: 'Text' },
    { value: 'email', label: 'Email' },
    { value: 'select', label: 'Dropdown' },
    { value: 'file', label: 'File upload' },
];

const limits = {
    max_fields: 40,
    max_options: 50,
    upload_types: ['pdf', 'png'],
    upload_max_kb: 10240,
    upload_default_kb: 2048,
    textarea_max: 5000,
};

function renderBuilder(saved = true) {
    render(
        <FormBuilder
            form={
                saved
                    ? {
                          id: 7,
                          title: 'Contact the bakery',
                          slug: 'bakery-contact',
                          fields: [nameField],
                          settings,
                      }
                    : null
            }
            defaults={settings}
            fieldTypes={fieldTypes}
            limits={limits}
            embed={saved ? '<x-forms-form slug="bakery-contact" />' : null}
            shortcode={saved ? '[form slug="bakery-contact"]' : null}
            bladeSnippet={
                saved
                    ? '<x-verso-embed name="form" :attrs="[\'slug\' => \'bakery-contact\']" />'
                    : null
            }
            usedIn={
                saved
                    ? [
                          {
                              id: 31,
                              title: 'Order a wedding cake',
                              type: 'page',
                              status: 'published',
                              edit_url: '/admin/entries/page/31/edit',
                              url: '/order-a-wedding-cake',
                          },
                      ]
                    : []
            }
            urls={{
                index: '/admin/forms',
                save: saved ? '/admin/forms/7' : '/admin/forms',
                destroy: saved ? '/admin/forms/7' : null,
                submissions: saved ? '/admin/forms/submissions?form=7' : null,
            }}
        />,
    );
}

describe('form builder page', () => {
    it('shows the saved fields, the embed code and the email placeholders', () => {
        renderBuilder();

        expect(screen.getByLabelText('Title')).toHaveValue(
            'Contact the bakery',
        );
        expect(screen.getByLabelText('Label')).toHaveValue('Your name');
        expect(
            screen.getByText('<x-forms-form slug="bakery-contact" />'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/\{all_fields\}.*\{name\}/),
        ).toBeInTheDocument();
    });

    it('shows the shortcode with a copy button, the Blade snippet and where the form is used', () => {
        renderBuilder();

        expect(screen.getByLabelText('Shortcode')).toHaveValue(
            '[form slug="bakery-contact"]',
        );
        expect(
            screen.getByRole('button', {
                name: 'Copy the shortcode of this form',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('Use it in a theme view')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Order a wedding cake' }),
        ).toHaveAttribute('href', '/admin/entries/page/31/edit');
    });

    it('fills the slug from the title on a new form', async () => {
        renderBuilder(false);

        await userEvent.type(
            screen.getByLabelText('Title'),
            'Catering request',
        );

        expect(screen.getByLabelText('Slug')).toHaveValue('catering-request');
        expect(
            screen.getByText('This form has no fields yet.'),
        ).toBeInTheDocument();
    });

    it('adds a field with a free name and saves with put', async () => {
        renderBuilder();

        await userEvent.click(
            screen.getByRole('button', { name: 'Add field' }),
        );
        expect(screen.getAllByLabelText('Label')).toHaveLength(2);

        fireEvent.submit(
            screen
                .getByRole('button', { name: 'Save form' })
                .closest('form') as HTMLFormElement,
        );

        const [url, data] = formMocks.put.mock.lastCall ?? [];
        expect(url).toBe('/admin/forms/7');
        expect(data.fields.map((field: FormField) => field.name)).toEqual([
            'name',
            'your_name',
        ]);
    });

    it('renames the field from the label while the name follows the label', async () => {
        renderBuilder();
        await userEvent.click(
            screen.getByRole('button', { name: 'Add field' }),
        );

        const label = screen.getAllByLabelText('Label')[1];
        await userEvent.clear(label);
        await userEvent.type(label, 'Full name');

        expect(screen.getAllByLabelText('Name')[1]).toHaveValue('full_name');
        expect(screen.getAllByLabelText('Name')[0]).toHaveValue('name');
    });

    it('moves and removes fields', async () => {
        renderBuilder();
        await userEvent.click(
            screen.getByRole('button', { name: 'Add field' }),
        );

        await userEvent.click(
            screen.getAllByRole('button', { name: 'Move Your name down' })[0],
        );
        fireEvent.submit(
            screen
                .getByRole('button', { name: 'Save form' })
                .closest('form') as HTMLFormElement,
        );
        expect(
            formMocks.put.mock.lastCall?.[1].fields.map(
                (field: FormField) => field.id,
            )[1],
        ).toBe('f_name');

        await userEvent.click(
            screen.getAllByRole('button', { name: 'Remove Your name' })[0],
        );
        expect(screen.getAllByLabelText('Label')).toHaveLength(1);
    });

    it('shows the confirmation email fields only when the copy is on', async () => {
        renderBuilder();

        expect(screen.queryByLabelText('Copy subject')).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('checkbox', {
                name: 'Send a copy to the person who wrote',
            }),
        );
        expect(screen.getByLabelText('Copy subject')).toHaveValue(
            'We got your message',
        );
    });
});

describe('forms list page', () => {
    it('lists forms with new message badges and an empty state', () => {
        const urls = {
            index: '/admin/forms',
            create: '/admin/forms/create',
            inbox: '/admin/forms/submissions',
            settings: null,
        };
        const { unmount } = render(
            <FormsIndex
                forms={[
                    {
                        id: 7,
                        title: 'Contact the bakery',
                        slug: 'bakery-contact',
                        shortcode: '[form slug="bakery-contact"]',
                        fields_count: 5,
                        submissions_count: 12,
                        new_count: 3,
                        updated_at: '2026-11-10T09:00:00+00:00',
                        edit_url: '/admin/forms/7/edit',
                        destroy_url: '/admin/forms/7',
                        submissions_url: '/admin/forms/submissions?form=7',
                    },
                ]}
                urls={urls}
            />,
        );

        expect(
            screen.getAllByText('Contact the bakery')[0],
        ).toBeInTheDocument();
        expect(screen.getAllByText('3 new')[0]).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Settings' }),
        ).not.toBeInTheDocument();
        unmount();

        render(<FormsIndex forms={[]} urls={urls} />);
        expect(screen.getByText('No forms yet.')).toBeInTheDocument();
    });
});

const row = {
    id: 41,
    status: 'new' as const,
    email: 'ines@example.pt',
    name: 'Inês Carvalho',
    preview: 'We marry on 14 June.',
    form: { id: 7, title: 'Contact the bakery' },
    created_at: '2026-11-16T10:00:00+00:00',
    url: '/admin/forms/submissions/41',
};

describe('inbox page', () => {
    function renderInbox(exportUrl: string | null = null) {
        render(
            <SubmissionsIndex
                submissions={
                    {
                        data: [row],
                        current_page: 1,
                        last_page: 1,
                        per_page: 25,
                        total: 1,
                        from: 1,
                        to: 1,
                        links: [],
                    } as never
                }
                filters={{ status: 'inbox', search: '', form: null, since: '' }}
                counts={{ inbox: 1, new: 1, read: 0, archived: 0, spam: 4 }}
                forms={[{ value: '7', label: 'Contact the bakery' }]}
                urls={{
                    index: '/admin/forms/submissions',
                    forms: '/admin/forms',
                    bulk: '/admin/forms/submissions/bulk',
                    erase: '/admin/forms/submissions/erase',
                    export: exportUrl,
                }}
                listView={{
                    key: 'forms.submissions',
                    columns: null,
                    density: null,
                }}
            />,
        );
    }

    it('shows messages, status tabs with counts and no export without a form', () => {
        renderInbox();

        expect(screen.getAllByText('Inês Carvalho')[0]).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Spam/ })).toHaveAttribute(
            'href',
            '/admin/forms/submissions?status=spam',
        );
        expect(
            screen.queryByRole('link', { name: /Export CSV/ }),
        ).not.toBeInTheDocument();
    });

    it('offers the CSV export once a form is chosen', () => {
        renderInbox('/admin/forms/submissions/export?form=7');

        expect(
            screen.getByRole('link', { name: /Export CSV/ }),
        ).toHaveAttribute('href', '/admin/forms/submissions/export?form=7');
    });

    it('runs bulk actions on the chosen messages', async () => {
        renderInbox();

        await userEvent.click(
            screen.getAllByRole('checkbox', {
                name: 'Choose the message from Inês Carvalho',
            })[0],
        );
        const actions = screen.getByRole('region', {
            name: 'Actions for the chosen messages',
        });
        await userEvent.click(
            within(actions).getByRole('button', { name: 'Archive' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/forms/submissions/bulk',
            { ids: [41], action: 'archived' },
            expect.anything(),
        );
    });

    it('searches when the search form is sent', async () => {
        renderInbox();

        await userEvent.type(screen.getByLabelText('Search'), 'wedding{Enter}');

        expect(router.get).toHaveBeenCalledWith(
            '/admin/forms/submissions?search=wedding',
            {},
            { preserveState: true },
        );
    });
});

describe('message page', () => {
    it('shows answers, the file link and status actions', async () => {
        render(
            <SubmissionShow
                submission={{
                    ...row,
                    status: 'spam',
                    fields: [
                        {
                            name: 'name',
                            label: 'Your name',
                            type: 'text',
                            value: 'Inês Carvalho',
                            file: null,
                        },
                        {
                            name: 'sketch',
                            label: 'Cake sketch',
                            type: 'file',
                            value: 'cake.pdf',
                            file: {
                                name: 'cake.pdf',
                                size: 245760,
                                url: '/admin/forms/submissions/41/files/sketch',
                            },
                        },
                        {
                            name: 'campaign',
                            label: 'Campaign',
                            type: 'hidden',
                            value: 'spring-weddings',
                            file: null,
                        },
                    ],
                    page_url: 'https://padaria.example/contact',
                    spam_reason: 'links',
                    user_agent: 'Mozilla/5.0',
                }}
                urls={{
                    index: '/admin/forms/submissions?form=7',
                    status: '/admin/forms/submissions/41/status',
                    destroy: '/admin/forms/submissions/41',
                    erase: '/admin/forms/submissions/erase',
                }}
            />,
        );

        expect(
            screen.getByRole('link', { name: /cake\.pdf \(240 kB\)/i }),
        ).toHaveAttribute('href', '/admin/forms/submissions/41/files/sketch');
        expect(
            screen.getByText('The message has many links.'),
        ).toBeInTheDocument();
        expect(screen.getByText('spring-weddings')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Reply by email' }),
        ).toHaveAttribute('href', 'mailto:ines@example.pt');

        await userEvent.click(screen.getByRole('button', { name: 'Archive' }));
        expect(router.put).toHaveBeenCalledWith(
            '/admin/forms/submissions/41/status',
            { status: 'archived' },
            { preserveScroll: true },
        );
    });
});

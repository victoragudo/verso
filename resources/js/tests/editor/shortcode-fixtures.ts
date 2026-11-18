import type { PluginBlockDefinition } from '@/types';

// The Form plugin block as the server describes it, with the slug alias.
export const formDefinition: PluginBlockDefinition = {
    name: 'form',
    label: 'Form',
    description: 'A contact form.',
    children: false,
    shortcode: true,
    aliases: { slug: 'form' },
    fields: [
        {
            name: 'form',
            label: 'Form',
            type: 'select',
            default: 'contact',
            options: [
                { value: 'contact', label: 'Contact the bakery' },
                { value: 'catering', label: 'Catering request' },
            ],
            help: '',
            required: true,
            min: null,
            max: null,
        },
        {
            name: 'title',
            label: 'Heading',
            type: 'text',
            default: null,
            options: [],
            help: '',
            required: false,
            min: null,
            max: 120,
        },
        {
            name: 'compact',
            label: 'Compact',
            type: 'toggle',
            default: false,
            options: [],
            help: '',
            required: false,
            min: null,
            max: null,
        },
    ],
};

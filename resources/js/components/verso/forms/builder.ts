import { msg, sourceText, t } from '@/lib/i18n';
import type { FormField, FormFieldType } from './types';

// Field types that offer a list of choices.
export const OPTION_TYPES: FormFieldType[] = ['select', 'checkboxes', 'radio'];

// Field types with a free text length limit.
export const LENGTH_TYPES: FormFieldType[] = ['text', 'textarea'];

const NAME_MAX_LENGTH = 40;

// Turns a label into a field name like "your_name": lower case letters, numbers and _.
export function nameFromLabel(label: string): string {
    const name = label
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^[^a-z]+|_+$/g, '')
        .slice(0, NAME_MAX_LENGTH)
        .replace(/_+$/g, '');

    return name || 'field';
}

// Turns a title into a slug like "catering-request".
export function slugFromTitle(title: string): string {
    return title
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// A name that no other field uses yet: "phone", then "phone_2", "phone_3" and so on.
export function uniqueName(base: string, taken: string[]): string {
    if (!taken.includes(base)) {
        return base;
    }

    let number = 2;

    while (taken.includes(`${base}_${number}`)) {
        number += 1;
    }

    return `${base}_${number}`;
}

const DEFAULT_LABELS: Record<FormFieldType, string> = {
    text: msg('forms::Your name'),
    email: msg('forms::Email'),
    phone: msg('forms::Phone'),
    textarea: msg('forms::Message'),
    select: msg('forms::Topic'),
    checkboxes: msg('forms::Interests'),
    radio: msg('forms::Preferred contact'),
    date: msg('forms::Date'),
    file: msg('forms::Attachment'),
    consent: msg('forms::I agree that you store my answers to reply to me.'),
    hidden: msg('forms::Source'),
};

const DEFAULT_OPTIONS: Partial<Record<FormFieldType, string[]>> = {
    select: [
        msg('forms::General question'),
        msg('forms::Support'),
        msg('forms::Something else'),
    ],
    checkboxes: [msg('forms::News'), msg('forms::Events')],
    radio: [msg('forms::Email'), msg('forms::Phone')],
};

// A new field of a type, with a sensible label and a name no other field uses.
export function newField(
    type: FormFieldType,
    taken: string[],
    id: string,
): FormField {
    const label = t(DEFAULT_LABELS[type]);
    const base =
        type === 'consent'
            ? 'consent'
            : nameFromLabel(sourceText(DEFAULT_LABELS[type]));

    return {
        id,
        type,
        name: uniqueName(base, taken),
        label,
        placeholder: '',
        help: '',
        required: type === 'consent',
        width: 'full',
        options: (DEFAULT_OPTIONS[type] ?? []).map((option) => t(option)),
        min_length: null,
        max_length: null,
        accept: [],
        max_kb: null,
        value: '',
    };
}

// Moves the field at index by step (-1 up, 1 down). Out of range moves change nothing.
export function moveField(
    fields: FormField[],
    index: number,
    step: number,
): FormField[] {
    const target = index + step;

    if (
        index < 0 ||
        index >= fields.length ||
        target < 0 ||
        target >= fields.length
    ) {
        return fields;
    }

    const next = [...fields];
    [next[index], next[target]] = [next[target], next[index]];

    return next;
}

// One option per line; empty lines are dropped and spaces trimmed.
export function parseOptions(text: string): string[] {
    return text
        .split('\n')
        .map((option) => option.trim())
        .filter((option) => option !== '');
}

// Addresses split by commas, spaces or new lines.
export function parseEmails(text: string): string[] {
    return text
        .split(/[\s,;]+/)
        .map((email) => email.trim())
        .filter((email) => email !== '');
}

// Placeholders an email template can use for these fields.
export function placeholders(fields: FormField[]): string[] {
    return [
        '{all_fields}',
        '{form_title}',
        '{site_name}',
        '{submitted_at}',
        '{page_url}',
        ...fields.map((field) => `{${field.name}}`),
    ];
}

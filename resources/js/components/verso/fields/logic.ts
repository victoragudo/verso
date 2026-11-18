import { t } from '@/lib/i18n';
import type {
    ConditionValue,
    FieldCondition,
    FieldDefinition,
    FieldGroupDefinition,
    FieldOption,
    FieldType,
    FieldValues,
    LocationRule,
    LocationState,
} from './types';

// Values are compared as text, so "1", 1 and true are the same (like Condition::normalize in PHP).
export function normalizeConditionValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (typeof value === 'number' || typeof value === 'string') {
        return String(value).trim();
    }

    return '';
}

// True when a field with this condition should show, given the watched value.
export function conditionPasses(
    condition: FieldCondition,
    watched: unknown,
): boolean {
    const expected = normalizeConditionValue(condition.value);
    const equal = Array.isArray(watched)
        ? watched.map(normalizeConditionValue).includes(expected) ||
          (expected === '' && watched.length === 0)
        : normalizeConditionValue(watched) === expected;

    return condition.operator === '!=' ? !equal : equal;
}

// The value a new entry starts with.
export function defaultValue(field: FieldDefinition): unknown {
    if (field.default !== undefined && field.default !== null) {
        return field.default;
    }

    if (field.type === 'toggle') {
        return false;
    }

    if (
        ['multi_select', 'checkbox', 'gallery', 'repeater'].includes(
            field.type,
        ) ||
        (isRelation(field.type) && field.multiple)
    ) {
        return [];
    }

    if (field.type === 'group') {
        return initialValues(field.fields ?? [], {});
    }

    return null;
}

export function isRelation(type: FieldType): boolean {
    return (
        type === 'entry_relation' ||
        type === 'term_relation' ||
        type === 'user_relation'
    );
}

export function hasOptions(type: FieldType): boolean {
    return ['select', 'multi_select', 'checkbox', 'radio'].includes(type);
}

export function hasSubFields(type: FieldType): boolean {
    return type === 'repeater' || type === 'group';
}

// Starting values: the saved ones, else the defaults.
export function initialValues(
    fields: FieldDefinition[],
    saved: FieldValues | null | undefined,
): FieldValues {
    return Object.fromEntries(
        fields.map((field) => {
            const value = saved?.[field.name];

            if (value === undefined) {
                return [field.name, defaultValue(field)];
            }

            if (field.type === 'group' && isRecord(value)) {
                return [field.name, initialValues(field.fields ?? [], value)];
            }

            return [field.name, value];
        }),
    );
}

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

// Names of the fields of one level that show. A hidden field hides the fields that watch it.
export function visibleFields(
    fields: FieldDefinition[],
    values: FieldValues,
): Set<string> {
    const byName = new Map(fields.map((field) => [field.name, field]));

    const shows = (field: FieldDefinition, seen: Set<string>): boolean => {
        const condition = field.condition;

        if (!condition) {
            return true;
        }

        const watched = byName.get(condition.field);

        if (!watched || seen.has(field.name)) {
            return false;
        }

        if (!shows(watched, new Set([...seen, field.name]))) {
            return false;
        }

        const value =
            values[condition.field] === undefined
                ? defaultValue(watched)
                : values[condition.field];

        return conditionPasses(condition, value);
    };

    return new Set(
        fields
            .filter((field) => shows(field, new Set()))
            .map((field) => field.name),
    );
}

// True when the rule holds for the entry being edited. Role rules were already checked by the server.
export function ruleHolds(rule: LocationRule, state: LocationState): boolean {
    let result: boolean;

    switch (rule.param) {
        case 'content_type':
            result = state.contentType === rule.value;
            break;
        case 'page_template':
            result = state.template === rule.value;
            break;
        case 'taxonomy_term':
            result = state.termIds.includes(Number(rule.value));
            break;
        default:
            return true;
    }

    return rule.operator === '!=' ? !result : result;
}

// A group shows when every rule of at least one rule set holds.
export function groupMatches(
    group: FieldGroupDefinition,
    state: LocationState,
): boolean {
    return group.location.some((rules) =>
        rules.every((rule) => ruleHolds(rule, state)),
    );
}

// The error for one input path, like "meta.fields.quotes.1.text".
export function errorFor(
    errors: Record<string, string | undefined>,
    path: string,
): string | undefined {
    return errors[path];
}

// Move an item of a list from one place to another.
export function moveItem<T>(list: T[], from: number, to: number): T[] {
    if (to < 0 || to >= list.length || from === to) {
        return list;
    }

    const next = [...list];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}

// Options typed one per line in the builder: "value : Label" or just "Label".
export function parseOptions(text: string): FieldOption[] {
    const seen = new Set<string>();
    const options: FieldOption[] = [];

    for (const line of text.split('\n')) {
        const [rawValue, ...rest] = line.split(':');
        const value = rawValue.trim();
        const label = rest.join(':').trim();

        if (value === '' || seen.has(value)) {
            continue;
        }

        seen.add(value);
        options.push({ value, label: label === '' ? value : label });
    }

    return options;
}

export function formatOptions(options: FieldOption[] = []): string {
    return options
        .map((option) =>
            option.label === option.value
                ? option.value
                : `${option.value} : ${option.label}`,
        )
        .join('\n');
}

// A field name made from a label: "Book title" becomes "book_title".
export function nameFromLabel(label: string): string {
    const name = label
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/^[^a-z]+/, '');

    return name.slice(0, 64);
}

// A new field of a type, with a name that is free on its level.
export function newField(
    type: FieldType,
    siblings: FieldDefinition[],
): FieldDefinition {
    const taken = new Set(siblings.map((field) => field.name));
    let index = siblings.length + 1;

    while (taken.has(`field_${index}`)) {
        index += 1;
    }

    return {
        name: `field_${index}`,
        label: t('Field {number}', { number: index }),
        type,
        required: false,
        ...(hasOptions(type)
            ? { options: [{ value: 'first', label: t('First') }] }
            : {}),
        ...(hasSubFields(type)
            ? {
                  fields: [
                      {
                          name: 'text',
                          label: t('Text'),
                          type: 'text' as FieldType,
                          required: false,
                      },
                  ],
              }
            : {}),
    };
}

// Turns the value typed for a condition into the stored value: "true" and "false" become booleans.
export function conditionValueFromText(text: string): ConditionValue {
    if (text === 'true') {
        return true;
    }

    if (text === 'false') {
        return false;
    }

    return text;
}

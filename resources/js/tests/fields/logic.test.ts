import { describe, expect, it } from 'vitest';
import {
    conditionPasses,
    conditionValueFromText,
    defaultValue,
    formatOptions,
    groupMatches,
    initialValues,
    moveItem,
    nameFromLabel,
    newField,
    normalizeConditionValue,
    parseOptions,
    ruleHolds,
    visibleFields,
} from '@/components/verso/fields/logic';
import type {
    FieldDefinition,
    FieldGroupDefinition,
} from '@/components/verso/fields/types';

const mediaKind: FieldDefinition = {
    name: 'media_kind',
    label: 'Media kind',
    type: 'radio',
    required: false,
    options: [
        { value: 'none', label: 'None' },
        { value: 'video', label: 'Video' },
    ],
};
const videoUrl: FieldDefinition = {
    name: 'video_url',
    label: 'Video link',
    type: 'url',
    required: true,
    condition: { field: 'media_kind', operator: '==', value: 'video' },
};
const captions: FieldDefinition = {
    name: 'captions',
    label: 'Captions',
    type: 'toggle',
    required: false,
    condition: { field: 'video_url', operator: '!=', value: '' },
};

function group(
    location: FieldGroupDefinition['location'],
): FieldGroupDefinition {
    return {
        key: 'trip_facts',
        title: 'Trip facts',
        description: '',
        position: 'main',
        order: 0,
        active: true,
        location,
        fields: [],
    };
}

describe('conditions', () => {
    it.each([
        [true, true, true],
        [true, '1', true],
        [false, false, true],
        [3, '3', true],
        ['video', 'gallery', false],
        ['vegan', ['vegan', 'quick'], true],
        ['vegan', ['quick'], false],
        ['', [], true],
        [null, null, true],
    ])('compares %s with %s as text', (expected, watched, passes) => {
        expect(
            conditionPasses(
                { field: 'watched', operator: '==', value: expected },
                watched,
            ),
        ).toBe(passes);
        expect(
            conditionPasses(
                { field: 'watched', operator: '!=', value: expected },
                watched,
            ),
        ).toBe(!passes);
    });

    it('normalizes values like the server', () => {
        expect(normalizeConditionValue(true)).toBe('1');
        expect(normalizeConditionValue(false)).toBe('0');
        expect(normalizeConditionValue(' Porto ')).toBe('Porto');
        expect(normalizeConditionValue({})).toBe('');
        expect(normalizeConditionValue(undefined)).toBe('');
    });

    it('turns typed true and false into booleans', () => {
        expect(conditionValueFromText('true')).toBe(true);
        expect(conditionValueFromText('false')).toBe(false);
        expect(conditionValueFromText('video')).toBe('video');
    });

    it('hides fields whose watched field is hidden', () => {
        const fields = [mediaKind, videoUrl, captions];

        expect([
            ...visibleFields(fields, {
                media_kind: 'none',
                video_url: 'https://vimeo.com/1',
            }),
        ]).toEqual(['media_kind']);
        expect([
            ...visibleFields(fields, {
                media_kind: 'video',
                video_url: 'https://vimeo.com/1',
            }),
        ]).toEqual(['media_kind', 'video_url', 'captions']);
    });

    it('uses the default value of a watched field that has no value yet', () => {
        const free: FieldDefinition = {
            name: 'free',
            label: 'Free',
            type: 'toggle',
            required: false,
        };
        const price: FieldDefinition = {
            name: 'price',
            label: 'Price',
            type: 'number',
            required: true,
            condition: { field: 'free', operator: '==', value: false },
        };

        expect(visibleFields([free, price], {}).has('price')).toBe(true);
    });

    it('never loops on conditions that watch each other', () => {
        const first: FieldDefinition = {
            name: 'first',
            label: 'First',
            type: 'text',
            required: false,
            condition: { field: 'second', operator: '==', value: '' },
        };
        const second: FieldDefinition = {
            name: 'second',
            label: 'Second',
            type: 'text',
            required: false,
            condition: { field: 'first', operator: '==', value: '' },
        };

        expect(visibleFields([first, second], {}).size).toBe(0);
    });
});

describe('values', () => {
    it('gives typed defaults', () => {
        expect(defaultValue(captions)).toBe(false);
        expect(defaultValue({ ...mediaKind, type: 'checkbox' })).toEqual([]);
        expect(
            defaultValue({
                name: 'related',
                label: 'Related',
                type: 'entry_relation',
                required: false,
                multiple: true,
            }),
        ).toEqual([]);
        expect(defaultValue({ ...videoUrl, default: 'https://a.pt' })).toBe(
            'https://a.pt',
        );
        expect(defaultValue(videoUrl)).toBeNull();
    });

    it('fills missing values and nested groups', () => {
        const venue: FieldDefinition = {
            name: 'venue',
            label: 'Venue',
            type: 'group',
            required: false,
            fields: [
                { name: 'city', label: 'City', type: 'text', required: false },
                {
                    name: 'indoor',
                    label: 'Indoor',
                    type: 'toggle',
                    required: false,
                },
            ],
        };

        expect(
            initialValues([mediaKind, venue], { venue: { city: 'Braga' } }),
        ).toEqual({
            media_kind: null,
            venue: { city: 'Braga', indoor: false },
        });
    });
});

describe('locations', () => {
    const state = { contentType: 'post', template: '', termIds: [4, 9] };

    it('checks every rule kind', () => {
        expect(
            ruleHolds(
                { param: 'content_type', operator: '==', value: 'post' },
                state,
            ),
        ).toBe(true);
        expect(
            ruleHolds(
                { param: 'content_type', operator: '!=', value: 'post' },
                state,
            ),
        ).toBe(false);
        expect(
            ruleHolds(
                { param: 'taxonomy_term', operator: '==', value: '9' },
                state,
            ),
        ).toBe(true);
        expect(
            ruleHolds(
                { param: 'page_template', operator: '==', value: 'landing' },
                state,
            ),
        ).toBe(false);
        expect(
            ruleHolds(
                { param: 'user_role', operator: '==', value: 'admin' },
                state,
            ),
        ).toBe(true);
    });

    it('matches when one rule set holds completely', () => {
        const travel = group([
            [
                { param: 'content_type', operator: '==', value: 'post' },
                { param: 'taxonomy_term', operator: '==', value: '12' },
            ],
            [{ param: 'page_template', operator: '==', value: 'landing' }],
        ]);

        expect(groupMatches(travel, state)).toBe(false);
        expect(groupMatches(travel, { ...state, termIds: [12] })).toBe(true);
        expect(
            groupMatches(travel, {
                ...state,
                template: 'landing',
                termIds: [],
            }),
        ).toBe(true);
    });
});

describe('builder helpers', () => {
    it('moves items and ignores moves out of range', () => {
        expect(moveItem(['a', 'b', 'c'], 0, 2)).toEqual(['b', 'c', 'a']);
        expect(moveItem(['a', 'b', 'c'], 2, 1)).toEqual(['a', 'c', 'b']);
        expect(moveItem(['a', 'b'], 0, -1)).toEqual(['a', 'b']);
        expect(moveItem(['a', 'b'], 1, 2)).toEqual(['a', 'b']);
    });

    it('reads and writes options one per line', () => {
        const options = parseOptions(
            'north : North coast\nPorto\n\nPorto\nsouth:South: coast',
        );

        expect(options).toEqual([
            { value: 'north', label: 'North coast' },
            { value: 'Porto', label: 'Porto' },
            { value: 'south', label: 'South: coast' },
        ]);
        expect(formatOptions(options)).toBe(
            'north : North coast\nPorto\nsouth : South: coast',
        );
    });

    it('makes field names from labels', () => {
        expect(nameFromLabel('Book title')).toBe('book_title');
        expect(nameFromLabel('Ciudad de Llegada')).toBe('ciudad_de_llegada');
        expect(nameFromLabel('2nd Língua!')).toBe('nd_lingua');
        expect(nameFromLabel('')).toBe('');
    });

    it('creates new fields with free names and type settings', () => {
        const first = newField('text', []);
        const select = newField('select', [first]);
        const repeater = newField('repeater', [
            first,
            { ...first, name: 'field_3' },
        ]);

        expect(first.name).toBe('field_1');
        expect(select.name).toBe('field_2');
        expect(select.options).toHaveLength(1);
        expect(repeater.name).toBe('field_4');
        expect(repeater.fields?.[0].type).toBe('text');
    });
});

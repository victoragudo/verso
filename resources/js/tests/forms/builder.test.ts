import { describe, expect, it } from 'vitest';
import {
    moveField,
    nameFromLabel,
    newField,
    parseEmails,
    parseOptions,
    placeholders,
    slugFromTitle,
    uniqueName,
} from '@/components/verso/forms/builder';

describe('form builder helpers', () => {
    it('turns labels into simple field names', () => {
        expect(nameFromLabel('Your name')).toBe('your_name');
        expect(nameFromLabel('Número de teléfono')).toBe('numero_de_telefono');
        expect(nameFromLabel('2 guests or more?')).toBe('guests_or_more');
        expect(nameFromLabel('!!!')).toBe('field');
        expect(nameFromLabel('a'.repeat(60))).toHaveLength(40);
    });

    it('turns titles into slugs', () => {
        expect(slugFromTitle('Catering request')).toBe('catering-request');
        expect(slugFromTitle('  Pedidos de Bolos de Noiva! ')).toBe(
            'pedidos-de-bolos-de-noiva',
        );
        expect(slugFromTitle('Café & Pastelaria')).toBe('cafe-pastelaria');
    });

    it('keeps field names unique', () => {
        expect(uniqueName('phone', ['email'])).toBe('phone');
        expect(uniqueName('phone', ['phone'])).toBe('phone_2');
        expect(uniqueName('phone', ['phone', 'phone_2'])).toBe('phone_3');
    });

    it('creates new fields with a label, a free name and default options', () => {
        const select = newField('select', ['topic'], 'f_1');

        expect(select).toMatchObject({
            id: 'f_1',
            type: 'select',
            name: 'topic_2',
            label: 'Topic',
            required: false,
            width: 'full',
        });
        expect(select.options).toEqual([
            'General question',
            'Support',
            'Something else',
        ]);
        expect(newField('consent', [], 'f_2')).toMatchObject({
            name: 'consent',
            required: true,
        });
        expect(newField('email', [], 'f_3').options).toEqual([]);
    });

    it('moves fields up and down and ignores moves past the ends', () => {
        const fields = ['name', 'email', 'message'].map((name, index) =>
            newField('text', [name], `f_${index}`),
        );

        expect(moveField(fields, 2, -1).map((field) => field.id)).toEqual([
            'f_0',
            'f_2',
            'f_1',
        ]);
        expect(moveField(fields, 0, 1).map((field) => field.id)).toEqual([
            'f_1',
            'f_0',
            'f_2',
        ]);
        expect(moveField(fields, 0, -1)).toBe(fields);
        expect(moveField(fields, 2, 1)).toBe(fields);
    });

    it('reads options one per line and addresses split by commas or spaces', () => {
        expect(parseOptions('  Small \n\nLarge\n ')).toEqual([
            'Small',
            'Large',
        ]);
        expect(
            parseEmails('orders@padaria.pt, marta@padaria.pt\nrui@padaria.pt;'),
        ).toEqual(['orders@padaria.pt', 'marta@padaria.pt', 'rui@padaria.pt']);
    });

    it('lists the email placeholders with one per field', () => {
        const list = placeholders([newField('email', [], 'f_1')]);

        expect(list).toContain('{all_fields}');
        expect(list).toContain('{form_title}');
        expect(list[list.length - 1]).toBe('{email}');
    });
});

import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { DraftBanner } from '@/components/verso/easy/draft-banner';
import { deviceLabel } from '@/components/verso/easy/preview-devices';
import {
    ADD_CLASS,
    attachBridge,
    BAR_CLASS,
    coreSectionActions,
} from '@/lib/easy/frame-bridge';
import type { FrameBridge } from '@/lib/easy/frame-bridge';
import { sectionName } from '@/lib/easy/page-blocks';
import { plainMessage } from '@/lib/easy/plain-errors';
import { msg, setMessages } from '@/lib/i18n';

// The page editor in another admin language: Spanish strings and an Arabic toolbar on an English page.

let bridge: FrameBridge | null = null;

afterEach(() => {
    bridge?.detach();
    bridge = null;
    document.body.innerHTML = '';
});

describe('page editor translations', () => {
    it('shows the draft banner in the admin language', () => {
        setMessages('es', {
            'You have unsaved changes from an earlier visit.':
                'Tienes cambios sin guardar de una visita anterior.',
            'Keep editing them': 'Seguir editándolos',
            'Throw them away': 'Descartarlos',
        });

        render(
            <DraftBanner
                savedAt="not a date"
                onKeep={() => undefined}
                onDiscard={() => undefined}
            />,
        );

        expect(
            screen.getByText(
                'Tienes cambios sin guardar de una visita anterior.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Seguir editándolos' }),
        ).toBeInTheDocument();
    });

    it('names the button to press again in the translated message', () => {
        setMessages('es', {
            Publish: 'Publicar',
            'We could not reach your site. Check that it is still running, then press {button}.':
                'No hemos podido conectar con tu sitio. Comprueba que sigue en marcha y pulsa {button}.',
        });

        expect(plainMessage('offline', { retry: msg('Publish') })).toBe(
            'No hemos podido conectar con tu sitio. Comprueba que sigue en marcha y pulsa Publicar.',
        );
    });

    it('formats section names and screen sizes with values', () => {
        setMessages('de', {
            'Section {number}': 'Abschnitt {number}',
            Phone: 'Telefon',
            '{device}, {width, number} pixels wide':
                '{device}, {width, number} Pixel breit',
        });

        expect(
            sectionName(
                [
                    {
                        id: 'b_hero0001',
                        type: 'section',
                        attrs: {},
                        children: [],
                    },
                ],
                'b_hero0001',
            ),
        ).toBe('Abschnitt 1');
        expect(deviceLabel('phone', 1390)).toBe('Telefon, 1.390 Pixel breit');
    });

    it('draws the frame tools in the admin language and direction', () => {
        setMessages(
            'ar',
            { 'Move up': 'تحريك لأعلى', 'Add section': 'إضافة قسم' },
            'rtl',
        );
        document.body.innerHTML =
            '<main><section data-verso-block="b_hero0001" data-verso-type="section"><h2 data-verso-block="b_title001" data-verso-type="heading">Fresh bread</h2></section></main>';

        bridge = attachBridge(document, {
            sections: [
                { id: 'b_hero0001', name: 'Fresh bread', hidden: false },
            ],
            actions: coreSectionActions(),
            onText: () => undefined,
            onImage: () => undefined,
            onSectionAction: () => undefined,
            onAddSection: () => undefined,
        });

        const bar = document.querySelector<HTMLElement>(`.${BAR_CLASS}`);
        const add = document.querySelector<HTMLElement>(`.${ADD_CLASS}`);

        expect(bar?.dir).toBe('rtl');
        expect(bar?.lang).toBe('ar');
        expect(bar?.querySelector('button')?.textContent).toBe('تحريك لأعلى');
        expect(add?.textContent).toBe('+ إضافة قسم');
    });
});

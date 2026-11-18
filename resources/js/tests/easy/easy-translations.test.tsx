import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ContrastWarnings } from '@/components/verso/easy/contrast-warnings';
import { reviewPalette } from '@/lib/easy/contrast';
import { setMessages } from '@/lib/i18n';
import GuidedStart from '@/pages/admin/easy/start';
import { setPageProps } from '@/tests/mocks/inertia';
import { blog, restaurant } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

beforeEach(() => {
    setPageProps({ errors: {} });
});

describe('Easy mode in another admin language', () => {
    it('shows the guided start in Spanish', () => {
        setMessages('es', {
            'What is your site for?': '¿Para qué es tu sitio?',
            'Build my site': 'Crear mi sitio',
            'Pick one kind of site above.': 'Elige un tipo de sitio arriba.',
        });

        render(
            <GuidedStart
                starters={[blog, restaurant]}
                current={null}
                hasPages={false}
            />,
        );

        expect(
            screen.getByRole('heading', { name: '¿Para qué es tu sitio?' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Crear mi sitio' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Elige un tipo de sitio arriba.'),
        ).toBeInTheDocument();
    });

    it('builds contrast warnings with the translated sentence for the direction', () => {
        setMessages('es', {
            '{direction, select, darker {The text is hard to read on this background. Try a darker text color.} other {The text is hard to read on this background. Try a lighter text color.}}':
                '{direction, select, darker {El texto es difícil de leer sobre este fondo. Prueba un color de texto más oscuro.} other {El texto es difícil de leer sobre este fondo. Prueba un color de texto más claro.}}',
            'Fix it': 'Arreglar',
        });

        const warnings = reviewPalette({
            text: '#f4f4f5',
            background: '#ffffff',
        });

        render(<ContrastWarnings warnings={warnings} onFix={() => {}} />);

        expect(
            screen.getByText(
                'El texto es difícil de leer sobre este fondo. Prueba un color de texto más oscuro.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Arreglar' }),
        ).toBeInTheDocument();
    });
});

import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { RequirementsStep } from '@/components/verso/install/steps-start';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

vi.mock('@/components/verso/install/use-step-form', () => ({
    useStepForm: () => ({
        form: { processing: false, errors: {}, data: {} },
        formRef: { current: null },
        submit: vi.fn(),
        serverErrors: {},
    }),
}));

const storagePath =
    '/var/folders/ky/s_7wy2cj243gx7h5__lghjpc0000gn/T/harbor-lane-bakery/storage';

describe('Server check step', () => {
    it('wraps long folder paths so the step never scrolls sideways on phones', () => {
        render(
            <RequirementsStep
                ready
                requirements={[
                    {
                        group: 'folders',
                        label: `${storagePath} is writable`,
                        passed: true,
                        detail: 'Writable.',
                        fix: '',
                    },
                ]}
            />,
        );

        const label = screen.getByText(`${storagePath} is writable`);

        expect(label.className).toContain('[overflow-wrap:anywhere]');
        expect(screen.getByText('Writable.').className).toContain(
            '[overflow-wrap:anywhere]',
        );
    });
});

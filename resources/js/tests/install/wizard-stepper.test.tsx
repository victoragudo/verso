import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { WizardStepper } from '@/components/verso/install/wizard-stepper';

const steps = [
    { key: 'welcome', label: 'Welcome' },
    { key: 'requirements', label: 'Server check' },
    { key: 'database', label: 'Database' },
    { key: 'site', label: 'Site and admin' },
    { key: 'services', label: 'Services' },
    { key: 'content', label: 'Content' },
    { key: 'review', label: 'Review' },
    { key: 'install', label: 'Install' },
];

describe('WizardStepper', () => {
    it('marks the current step for assistive technology', () => {
        render(
            <WizardStepper
                steps={steps}
                current="database"
                done={['welcome', 'requirements']}
            />,
        );

        const nav = screen.getByRole('navigation', {
            name: 'Install progress',
        });
        const current = within(nav).getByText('Database').closest('li');

        expect(current).toHaveAttribute('aria-current', 'step');
        expect(
            within(nav)
                .getAllByRole('listitem')
                .filter((item) => item.hasAttribute('aria-current')),
        ).toHaveLength(1);
    });

    it('shows the step number and the progress', () => {
        render(
            <WizardStepper
                steps={steps}
                current="database"
                done={['welcome', 'requirements']}
            />,
        );

        expect(screen.getByText(/Step 3 of 8/)).toBeInTheDocument();
        expect(screen.getByText('29%')).toBeInTheDocument();
        expect(
            screen.getByRole('progressbar', { name: 'Install progress' }),
        ).toHaveAttribute('value', '3');
    });

    it('says which steps are done', () => {
        render(
            <WizardStepper
                steps={steps}
                current="site"
                done={['welcome', 'requirements', 'database']}
            />,
        );

        expect(screen.getAllByText('(done)')).toHaveLength(3);
        expect(
            screen.getByText('Services').closest('li'),
        ).not.toHaveTextContent('(done)');
    });

    it('does not count the current step as done when the user goes back', () => {
        render(
            <WizardStepper
                steps={steps}
                current="database"
                done={['welcome', 'requirements', 'database', 'site']}
            />,
        );

        expect(
            screen.getByText('Database').closest('li'),
        ).not.toHaveTextContent('(done)');
        expect(screen.getAllByText('(done)')).toHaveLength(3);
    });

    it('reaches 100% on the last step', () => {
        render(
            <WizardStepper
                steps={steps}
                current="install"
                done={steps.map((step) => step.key)}
            />,
        );

        expect(screen.getByText('100%')).toBeInTheDocument();
    });
});

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PasswordStrengthMeter } from '@/components/verso/install/password-strength-meter';
import { passwordStrength } from '@/lib/password-strength';

describe('passwordStrength', () => {
    it('gives zero to an empty or too short password', () => {
        expect(passwordStrength('', 10).score).toBe(0);
        expect(passwordStrength('Bread7', 10)).toMatchObject({
            score: 0,
            meetsRules: false,
        });
        expect(passwordStrength('Bread7', 10).hints).toContain(
            'Use at least 10 characters.',
        );
    });

    it('asks for letters and numbers like the server does', () => {
        const result = passwordStrength('sardinesandvinho', 10);

        expect(result.meetsRules).toBe(false);
        expect(result.hints).toContain('Mix letters and numbers.');
    });

    it('rates long mixed passwords as strong', () => {
        expect(
            passwordStrength('Tram 28 to Graça, then ginjinha!', 10),
        ).toMatchObject({ score: 4, label: 'Strong', meetsRules: true });
    });

    it('rates a plain password that meets the rules as weak or fair', () => {
        const result = passwordStrength('harbor2026x', 10);

        expect(result.meetsRules).toBe(true);
        expect(result.score).toBeGreaterThanOrEqual(1);
        expect(result.score).toBeLessThanOrEqual(2);
    });

    it('punishes common words, sequences and repeats', () => {
        expect(passwordStrength('Password2026!x', 10).hints).toContain(
            'Avoid common words like "password" or "admin".',
        );
        expect(passwordStrength('Lisbon1234Tram!', 10).hints).toContain(
            'Avoid runs like "abcd" or "1234".',
        );
        expect(passwordStrength('Lisbonnnn77Tram!', 10).hints).toContain(
            'Avoid repeated characters like "aaa".',
        );
        expect(passwordStrength('Password2026!x', 10).score).toBeLessThan(
            passwordStrength('Pastel9Nata!Belem', 10).score,
        );
    });

    it('punishes the email or site name inside the password', () => {
        const result = passwordStrength('Ines-Lisbon-2026!', 10, [
            'ines@lisbonfood.example',
            'Inês Duarte',
        ]);

        expect(result.hints).toContain(
            'Do not use your name, email or site name.',
        );
    });
});

describe('PasswordStrengthMeter', () => {
    it('shows the rules before the user types', () => {
        render(<PasswordStrengthMeter id="meter" password="" minLength={12} />);

        expect(
            screen.getByText(
                'At least 12 characters, with letters and numbers.',
            ),
        ).toBeInTheDocument();
    });

    it('announces the strength politely and fills the bar', () => {
        const { container } = render(
            <PasswordStrengthMeter
                id="meter"
                password="Tram 28 to Graça, then ginjinha!"
                minLength={10}
            />,
        );

        expect(screen.getByText('Strength: Strong.')).toBeInTheDocument();
        expect(
            screen.getByText(/Strength/).closest('[aria-live]'),
        ).toHaveAttribute('aria-live', 'polite');
        expect(container.querySelector('[data-score]')).toHaveAttribute(
            'data-score',
            '4',
        );
        expect(
            screen
                .getAllByTestId('strength-segment')
                .filter((segment) =>
                    segment.className.includes('bg-emerald-600'),
                ),
        ).toHaveLength(4);
    });

    it('shows the first tip for a weak password', () => {
        render(
            <PasswordStrengthMeter
                id="meter"
                password="bread"
                minLength={10}
            />,
        );

        expect(screen.getByText('Strength: Too weak.')).toBeInTheDocument();
        expect(
            screen.getByText(/Use at least 10 characters\./),
        ).toBeInTheDocument();
    });
});

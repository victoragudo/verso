import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EntryStatusBadge } from '@/components/verso/content/status-badge';
import { StatusBadge, statusTone } from '@/components/verso/ui/status-badge';
import type { StatusTone } from '@/components/verso/ui/status-badge';

describe('statusTone', () => {
    it.each([
        ['published', 'success'],
        ['approved', 'success'],
        ['completed', 'success'],
        ['active', 'success'],
        ['scheduled', 'info'],
        ['running', 'info'],
        ['pending', 'warning'],
        ['draft', 'neutral'],
        ['trash', 'neutral'],
        ['spam', 'danger'],
        ['failed', 'danger'],
    ])('gives %s the %s tone', (status, tone) => {
        expect(statusTone(status)).toBe(tone);
    });

    it('gives unknown words the neutral tone', () => {
        expect(statusTone('archived')).toBe('neutral');
        expect(statusTone('')).toBe('neutral');
    });

    it.each(['constructor', 'toString', '__proto__', 'hasOwnProperty'])(
        'gives the object key %s the neutral tone',
        (status) => {
            expect(statusTone(status)).toBe('neutral');
        },
    );
});

describe('StatusBadge', () => {
    const tones: [StatusTone, string, string][] = [
        ['success', 'bg-success-soft', 'bg-success-text'],
        ['warning', 'bg-warning-soft', 'bg-warning-text'],
        ['danger', 'bg-danger-soft', 'bg-danger-text'],
        ['neutral', 'bg-neutral-soft', 'bg-neutral-text/60'],
        ['info', 'bg-info-soft', 'bg-info-text'],
    ];

    it.each(tones)('uses soft colors for the %s tone', (tone, soft, dot) => {
        render(<StatusBadge tone={tone}>Approved</StatusBadge>);

        const badge = screen.getByText('Approved');

        expect(badge).toHaveClass(soft);
        expect(badge.querySelector('span')).toHaveClass(dot);
    });

    it('hides the dot from screen readers', () => {
        render(<StatusBadge tone="info">Running</StatusBadge>);

        expect(
            screen.getByText('Running').querySelector('span'),
        ).toHaveAttribute('aria-hidden', 'true');
    });

    it('adds extra classes', () => {
        render(
            <StatusBadge tone="neutral" className="capitalize">
                draft
            </StatusBadge>,
        );

        expect(screen.getByText('draft')).toHaveClass('capitalize');
    });

    it('renders rich children', () => {
        render(
            <StatusBadge tone="warning">
                <strong>3</strong> pending
            </StatusBadge>,
        );

        expect(screen.getByText('3').tagName).toBe('STRONG');
    });
});

describe('EntryStatusBadge', () => {
    it.each([
        ['published', 'Published', 'bg-success-soft'],
        ['scheduled', 'Scheduled', 'bg-info-soft'],
        ['draft', 'Draft', 'bg-neutral-soft'],
        ['trash', 'Trash', 'bg-danger-soft'],
    ] as const)('shows the %s status', (status, label, soft) => {
        render(<EntryStatusBadge status={status} />);

        expect(screen.getByText(label)).toHaveClass(soft);
    });
});

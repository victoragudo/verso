import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
    findingLocation,
    riskLabel,
    RiskBadge,
    VerificationReportCard,
} from '@/components/verso/extensions/verification-report';
import { capabilityLabels, shellFinding, verificationReport } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

describe('riskLabel', () => {
    it.each([
        ['low', 'Low risk'],
        ['medium', 'Medium risk'],
        ['high', 'High risk'],
    ] as const)('names %s as "%s"', (risk, label) => {
        expect(riskLabel(risk)).toBe(label);
    });
});

describe('findingLocation', () => {
    it('joins the file and line', () => {
        expect(findingLocation(shellFinding)).toBe('src/Backup.php:15');
    });

    it('shows the file alone when there is no line', () => {
        expect(findingLocation({ ...shellFinding, line: null })).toBe(
            'src/Backup.php',
        );
    });

    it('is empty without a file', () => {
        expect(
            findingLocation({ ...shellFinding, file: null, line: null }),
        ).toBe('');
    });
});

describe('RiskBadge', () => {
    it.each([
        ['low', 'bg-success-soft'],
        ['medium', 'bg-warning-soft'],
        ['high', 'bg-danger-soft'],
    ] as const)('gives %s risk the %s tone', (risk, tone) => {
        render(<RiskBadge risk={risk} />);

        expect(screen.getByText(riskLabel(risk))).toHaveClass(tone);
    });
});

describe('VerificationReportCard', () => {
    it('shows the risk, the score and the counts', () => {
        render(
            <VerificationReportCard
                report={verificationReport()}
                capabilities={capabilityLabels}
            />,
        );

        expect(screen.getAllByText('High risk')[0]).toBeInTheDocument();
        expect(screen.getByText('Score').nextSibling).toHaveTextContent('31');
        expect(screen.getByText(/3 files scanned/)).toBeInTheDocument();
        expect(screen.getByText(/from the uploaded zip/)).toBeInTheDocument();
    });

    it('lists each finding with its rule and location', () => {
        render(
            <VerificationReportCard
                report={verificationReport()}
                capabilities={capabilityLabels}
            />,
        );

        expect(
            screen.getAllByText('exec() runs a shell command.').length,
        ).toBeGreaterThan(0);
        expect(screen.getAllByText('php.shell').length).toBeGreaterThan(0);
        expect(screen.getAllByText('src/Backup.php:15').length).toBeGreaterThan(
            0,
        );
    });

    it('shows an empty state when nothing was found', () => {
        render(
            <VerificationReportCard
                report={verificationReport({
                    risk: 'low',
                    score: 0,
                    findings: [],
                    counts: { high: 0, medium: 0, low: 0, info: 0 },
                })}
                capabilities={capabilityLabels}
            />,
        );

        expect(screen.getByText('No findings.')).toBeInTheDocument();
        expect(screen.getAllByText('Low risk')[0]).toBeInTheDocument();
    });

    it('marks the permissions the code uses without declaring them', () => {
        render(
            <VerificationReportCard
                report={verificationReport()}
                capabilities={capabilityLabels}
            />,
        );

        const row = screen.getByText('Not declared').parentElement!;

        expect(within(row).getByText('Manage users')).toBeInTheDocument();
        expect(within(row).queryByText('Upload files')).toBeNull();
        expect(
            screen.getByText('backups.harbourlights.io'),
        ).toBeInTheDocument();
    });

    it('shows the sandbox run and its errors', () => {
        const report = verificationReport();
        report.details.sandbox = {
            ...report.details.sandbox!,
            status: 'failed',
            errors: [
                {
                    stage: 'migrate',
                    message:
                        'QueryException: no such table: donation_currencies',
                    file: 'database/migrations/2026_12_01_000000_create_donations_table.php',
                    line: 17,
                },
            ],
        };

        render(
            <VerificationReportCard
                report={report}
                capabilities={capabilityLabels}
            />,
        );

        expect(screen.getByText('Failed')).toBeInTheDocument();
        expect(
            screen.getByText(/no such table: donation_currencies/),
        ).toBeInTheDocument();
        expect(screen.getByText('1 up, 1 down')).toBeInTheDocument();
    });

    it('explains a skipped sandbox', () => {
        const report = verificationReport();
        report.details.sandbox = {
            ...report.details.sandbox!,
            status: 'skipped',
        };

        render(
            <VerificationReportCard
                report={report}
                capabilities={capabilityLabels}
            />,
        );

        expect(screen.getByText('Not run')).toBeInTheDocument();
        expect(
            screen.getByText(/The sandbox is turned off/),
        ).toBeInTheDocument();
    });
});

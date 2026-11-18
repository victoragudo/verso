import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
    PreviewPages,
    ThemeChecksCard,
    previewDocument,
} from '@/components/verso/extensions/theme-report';
import { VerificationReportCard } from '@/components/verso/extensions/verification-report';
import { themeReport } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const report = themeReport();
const details = report.details.theme!;
const pages = report.details.sandbox!.pages!;

describe('ThemeChecksCard', () => {
    it('lists the declared versions, screenshot and hosts', () => {
        render(<ThemeChecksCard theme={details} />);

        expect(screen.getByText('Verso ^0.1, PHP ^8.4')).toBeInTheDocument();
        expect(screen.getByText('screenshot.png')).toBeInTheDocument();
        expect(screen.getByText('cdn.jsdelivr.net')).toBeInTheDocument();
    });

    it('marks each quality marker as found or missing', () => {
        render(<ThemeChecksCard theme={details} />);
        const list = screen.getByRole('list', { name: 'Quality checks' });
        const row = (label: RegExp) =>
            within(list).getByText(label).closest('li')!;

        expect(within(row(/SEO tags/)).getByText('Found')).toBeInTheDocument();
        expect(
            within(row(/Page language/)).getByText('Missing'),
        ).toBeInTheDocument();
    });

    it('says when nothing is declared', () => {
        render(
            <ThemeChecksCard
                theme={{
                    ...details,
                    requires: null,
                    php: null,
                    screenshot: null,
                    network: [],
                }}
            />,
        );

        expect(
            screen.getByText('Verso not declared, PHP not declared'),
        ).toBeInTheDocument();
    });
});

describe('PreviewPages', () => {
    it('shows each rendered page in a sandboxed frame without scripts or network', () => {
        render(<PreviewPages pages={pages} />);

        const frame = screen.getByTitle('Preview of the home page');

        expect(frame).toHaveAttribute('sandbox', '');
        expect(frame).toHaveAttribute('loading', 'lazy');
        expect(frame).toHaveAttribute('referrerpolicy', 'no-referrer');
        expect(frame.getAttribute('srcdoc')).toContain("default-src 'none'");
        expect(frame.getAttribute('srcdoc')).toContain(
            '<h1>Harbor Journal</h1>',
        );
    });

    it('shows the status of each page and a note when no HTML was kept', () => {
        render(<PreviewPages pages={pages} />);

        expect(screen.getAllByText('Rendered')).toHaveLength(2);
        expect(screen.getByText('Error 500')).toBeInTheDocument();
        expect(
            screen.getByText('No preview kept for this page.'),
        ).toBeInTheDocument();
    });

    it('renders nothing without pages', () => {
        const { container } = render(<PreviewPages pages={[]} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('puts the policy before the page HTML', () => {
        expect(previewDocument('<p>Tides</p>')).toMatch(
            /^<meta http-equiv="Content-Security-Policy"[^>]+><p>Tides<\/p>$/,
        );
    });
});

describe('VerificationReportCard for themes', () => {
    it('shows the theme checks instead of plugin permissions', () => {
        render(<VerificationReportCard report={report} />);

        expect(
            screen.queryByRole('heading', { name: 'Permissions and network' }),
        ).toBeNull();
        expect(
            screen.getByRole('heading', { name: 'Requirements and quality' }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 of 3 rendered')).toBeInTheDocument();
    });

    it('talks about the theme when there are no findings', () => {
        render(
            <VerificationReportCard report={themeReport({ findings: [] })} />,
        );

        expect(
            screen.getByText('The checks found nothing risky in this theme.'),
        ).toBeInTheDocument();
    });
});

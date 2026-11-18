import { render, renderHook, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SiteDate } from '@/components/verso/ui/site-date';
import { useSiteDates } from '@/hooks/use-site-dates';
import { formatDate, formatDay } from '@/lib/dates';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

afterEach(() => resetPageProps());

describe('useSiteDates', () => {
    it('uses the site time zone shared by the server', () => {
        setPageProps({ siteTimezone: 'Asia/Tokyo' });

        const { result } = renderHook(() => useSiteDates());

        expect(result.current.timeZone).toBe('Asia/Tokyo');
        expect(result.current.toLocalInput('2026-11-17T23:15:00Z')).toBe(
            '2026-11-18T08:15',
        );
    });

    it('falls back to UTC when the zone is empty', () => {
        setPageProps({ siteTimezone: '' });

        const { result } = renderHook(() => useSiteDates());

        expect(result.current.timeZone).toBe('UTC');
        expect(result.current.toLocalInput('2026-11-17T23:15:00Z')).toBe(
            '2026-11-17T23:15',
        );
    });

    it.each([
        ['America/New_York', '2026-11-17T04:30'],
        ['Europe/Madrid', '2026-11-17T10:30'],
        ['Australia/Sydney', '2026-11-17T20:30'],
        ['America/Sao_Paulo', '2026-11-17T06:30'],
    ])('reads dates in %s', (zone, expected) => {
        setPageProps({ siteTimezone: zone });

        const { result } = renderHook(() => useSiteDates());

        expect(result.current.toLocalInput('2026-11-17T09:30:00Z')).toBe(
            expected,
        );
    });

    it('formats dates and days in the site zone', () => {
        setPageProps({ siteTimezone: 'Pacific/Honolulu' });

        const { result } = renderHook(() => useSiteDates());

        expect(result.current.formatDate('2026-11-17T05:00:00Z')).toBe(
            formatDate('2026-11-16T19:00:00Z', 'UTC'),
        );
        expect(result.current.formatDay('2026-11-17T05:00:00Z')).toBe(
            formatDay('2026-11-16T12:00:00Z', 'UTC'),
        );
    });

    it('passes typed wall times through unchanged', () => {
        setPageProps({ siteTimezone: 'Europe/Madrid' });

        const { result } = renderHook(() => useSiteDates());

        expect(result.current.fromLocalInput('2026-12-24T18:00')).toBe(
            '2026-12-24T18:00',
        );
        expect(result.current.fromLocalInput('')).toBeNull();
    });

    it('keeps the same helpers while the zone does not change', () => {
        setPageProps({ siteTimezone: 'Europe/Lisbon' });

        const { result, rerender } = renderHook(() => useSiteDates());
        const first = result.current;

        rerender();

        expect(result.current).toBe(first);
    });

    it('builds new helpers when the zone changes', () => {
        setPageProps({ siteTimezone: 'Europe/Lisbon' });

        const { result, rerender } = renderHook(() => useSiteDates());
        const first = result.current;

        setPageProps({ siteTimezone: 'Asia/Singapore' });
        rerender();

        expect(result.current).not.toBe(first);
        expect(result.current.timeZone).toBe('Asia/Singapore');
    });
});

describe('SiteDate', () => {
    it('prints nothing without a date', () => {
        const { container } = render(<SiteDate iso={null} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('prints a time tag with the UTC value and the local text', () => {
        setPageProps({ siteTimezone: 'Asia/Tokyo' });

        render(<SiteDate iso="2026-11-17T23:15:00Z" className="text-xs" />);

        const time = screen.getByText(
            formatDate('2026-11-18T08:15:00Z', 'UTC'),
        );

        expect(time.tagName).toBe('TIME');
        expect(time).toHaveAttribute('datetime', '2026-11-17T23:15:00Z');
        expect(time).toHaveClass('text-xs');
    });

    it('prints only the day when asked', () => {
        setPageProps({ siteTimezone: 'America/Los_Angeles' });

        render(<SiteDate iso="2026-11-17T03:00:00Z" dayOnly />);

        expect(
            screen.getByText(formatDay('2026-11-16T12:00:00Z', 'UTC')),
        ).toBeInTheDocument();
    });
});

import type { CSSProperties } from 'react';
import { msg, t } from '@/lib/i18n';
import type { Rating } from '@/lib/seo-analysis';
import { cn } from '@/lib/utils';

// Plain words for each rating, used by the ring and the traffic lights.
export const RATING_LABELS: Record<Rating, string> = {
    good: msg('Good'),
    ok: msg('Needs work'),
    poor: msg('Poor'),
    none: msg('Not checked'),
};

const RING_COLORS: Record<Rating, string> = {
    good: 'var(--color-success-text)',
    ok: 'var(--color-warning-text)',
    poor: 'var(--color-danger-text)',
    none: 'var(--color-muted-foreground)',
};

const DOT_CLASSES: Record<Rating, string> = {
    good: 'bg-success-text',
    ok: 'bg-warning-text',
    poor: 'bg-danger-text',
    none: 'bg-neutral-text/50',
};

type Props = {
    score: number;
    rating: Rating;
    label: string;
    size?: 'sm' | 'lg';
    className?: string;
};

// A ring that fills up to the score. A native <meter> gives the value to screen readers.
export function ScoreRing({
    score,
    rating,
    label,
    size = 'sm',
    className,
}: Props) {
    const value = Math.max(0, Math.min(100, Math.round(score)));
    const style = {
        '--score': `${value * 3.6}deg`,
        '--ring': RING_COLORS[rating],
    } as CSSProperties;

    return (
        <div
            className={cn(
                'relative grid shrink-0 place-items-center rounded-full',
                size === 'lg' ? 'size-32' : 'size-12',
                className,
            )}
            style={{
                ...style,
                background:
                    'conic-gradient(var(--ring) var(--score), var(--color-muted) 0)',
            }}
        >
            <div
                className={cn(
                    'grid size-full place-items-center rounded-full bg-card',
                    size === 'lg' ? 'scale-[0.84]' : 'scale-[0.78]',
                )}
                aria-hidden="true"
            >
                <span
                    className={cn(
                        'font-semibold text-foreground tabular-nums',
                        size === 'lg' ? 'text-3xl' : 'text-sm',
                    )}
                >
                    {rating === 'none' ? '–' : value}
                </span>
            </div>
            <meter
                className="sr-only"
                min={0}
                max={100}
                low={40}
                high={70}
                optimum={100}
                value={value}
                aria-label={label}
                aria-valuetext={t(
                    '{value} out of 100, {rating, select, good {good} ok {needs work} poor {poor} other {not checked}}',
                    { value, rating },
                )}
            />
        </div>
    );
}

// A small colored dot with the rating in words next to it.
export function TrafficLight({
    rating,
    label,
    className,
}: {
    rating: Rating;
    label?: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 text-xs text-muted-foreground',
                className,
            )}
        >
            <span
                className={cn('size-2.5 rounded-full', DOT_CLASSES[rating])}
                aria-hidden="true"
            />
            {label ?? t(RATING_LABELS[rating])}
        </span>
    );
}

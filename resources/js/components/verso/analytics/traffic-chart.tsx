import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { formatDay, formatNumber, formatTick, niceTicks } from './format';
import type { TrafficPoint } from './types';

type SeriesKey = 'visitors' | 'views';

type Series = {
    key: SeriesKey;
    label: string;
    stroke: string;
    fill: string;
    swatch: string;
};

// Colors checked with the dataviz palette validator (light on white, dark on zinc-900).
export const trafficSeries: Series[] = [
    {
        key: 'visitors',
        label: msg('analytics::Visitors'),
        stroke: 'stroke-[#2563eb] dark:stroke-[#3b82f6]',
        fill: 'fill-[#2563eb] dark:fill-[#3b82f6]',
        swatch: 'bg-[#2563eb] dark:bg-[#3b82f6]',
    },
    {
        key: 'views',
        label: msg('analytics::Page views'),
        stroke: 'stroke-[#059669]',
        fill: 'fill-[#059669]',
        swatch: 'bg-[#059669]',
    },
];

const HEIGHT = 240;
const MARGIN = { top: 12, right: 16, bottom: 28, left: 44 };
const END_LABEL_SPACE = 56;
const END_LABEL_MIN_GAP = 16;
const X_LABEL_MIN_SPACE = 72;
const TOOLTIP_GAP = 12;

// Measures the width of a box, so the SVG draws at real pixel size.
function useWidth<T extends HTMLElement>() {
    const ref = useRef<T>(null);
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(Math.floor(entry.contentRect.width)),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, width] as const;
}

type Props = {
    points: TrafficPoint[];
    className?: string;
};

// Daily visitors and page views as two lines on one count axis, with a crosshair tooltip.
export function TrafficChart({ points, className }: Props) {
    const [boxRef, width] = useWidth<HTMLDivElement>();
    const [active, setActive] = useState<number | null>(null);
    const titleId = useId();

    const max = Math.max(1, ...points.map((point) => point.views));
    const ticks = useMemo(() => niceTicks(max), [max]);
    const top = ticks[ticks.length - 1];
    const showEndLabels = width >= 480;
    const plotLeft = MARGIN.left;
    const plotRight = Math.max(
        plotLeft + 1,
        width - MARGIN.right - (showEndLabels ? END_LABEL_SPACE : 0),
    );
    const plotTop = MARGIN.top;
    const plotBottom = HEIGHT - MARGIN.bottom;
    const plotWidth = plotRight - plotLeft;
    const plotHeight = plotBottom - plotTop;

    const x = (index: number) =>
        points.length <= 1
            ? plotLeft + plotWidth / 2
            : plotLeft + (index / (points.length - 1)) * plotWidth;
    const y = (value: number) => plotBottom - (value / top) * plotHeight;

    const linePath = (key: SeriesKey) =>
        points
            .map(
                (point, index) =>
                    `${index === 0 ? 'M' : 'L'}${x(index).toFixed(1)},${y(point[key]).toFixed(1)}`,
            )
            .join('');

    const areaPath = (key: SeriesKey) =>
        points.length === 0
            ? ''
            : `${linePath(key)}L${x(points.length - 1).toFixed(1)},${plotBottom}L${x(0).toFixed(1)},${plotBottom}Z`;

    const labelEvery = Math.max(
        1,
        Math.ceil(
            points.length /
                Math.max(1, Math.floor(plotWidth / X_LABEL_MIN_SPACE)),
        ),
    );
    const last = points.length - 1;
    const labelIndexes = points
        .map((_, index) => index)
        .filter(
            (index) =>
                index === last ||
                (index % labelEvery === 0 && last - index >= labelEvery),
        );
    const lastPoint = points[last];
    const endLabels =
        showEndLabels &&
        lastPoint &&
        Math.abs(y(lastPoint.visitors) - y(lastPoint.views)) >=
            END_LABEL_MIN_GAP;

    const pickIndex = (event: PointerEvent<SVGRectElement>) => {
        const bounds = event.currentTarget.getBoundingClientRect();
        const ratio = (event.clientX - bounds.left) / Math.max(1, bounds.width);
        setActive(Math.min(last, Math.max(0, Math.round(ratio * last))));
    };

    const moveWithKeys = (event: KeyboardEvent<SVGSVGElement>) => {
        const steps: Record<string, number> = {
            ArrowLeft: -1,
            ArrowRight: 1,
            Home: -points.length,
            End: points.length,
        };

        if (!(event.key in steps)) {
            return;
        }

        event.preventDefault();
        setActive((current) =>
            Math.min(last, Math.max(0, (current ?? last) + steps[event.key])),
        );
    };

    const activePoint = active === null ? null : points[active];
    const tooltipOnLeft = active !== null && x(active) > width / 2;
    const tooltipLeft =
        active === null
            ? 0
            : x(active) + (tooltipOnLeft ? -TOOLTIP_GAP : TOOLTIP_GAP);

    return (
        <div ref={boxRef} className={cn('relative w-full', className)}>
            {width > 0 && (
                <svg
                    width={width}
                    height={HEIGHT}
                    viewBox={`0 0 ${width} ${HEIGHT}`}
                    role="img"
                    aria-labelledby={titleId}
                    tabIndex={0}
                    onKeyDown={moveWithKeys}
                    onFocus={() => setActive((current) => current ?? last)}
                    onBlur={() => setActive(null)}
                    className="block overflow-visible rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    <title id={titleId}>
                        {t(
                            'analytics::Daily visitors and page views from {from} to {to}. Use the arrow keys to read each day.',
                            {
                                from: points[0]
                                    ? formatDay(points[0].date)
                                    : '',
                                to: lastPoint ? formatDay(lastPoint.date) : '',
                            },
                        )}
                    </title>

                    {ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={plotLeft}
                                x2={plotRight}
                                y1={y(tick)}
                                y2={y(tick)}
                                className="stroke-border"
                                strokeWidth={1}
                                shapeRendering="crispEdges"
                            />
                            <text
                                x={plotLeft - 8}
                                y={y(tick)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground text-[11px] tabular-nums"
                            >
                                {formatTick(tick, top)}
                            </text>
                        </g>
                    ))}

                    {labelIndexes.map((index) => (
                        <text
                            key={points[index].date}
                            x={x(index)}
                            y={HEIGHT - 8}
                            textAnchor={
                                index === 0
                                    ? 'start'
                                    : index === last
                                      ? 'end'
                                      : 'middle'
                            }
                            className="fill-muted-foreground text-[11px] tabular-nums"
                        >
                            {formatDay(points[index].date)}
                        </text>
                    ))}

                    <path
                        d={areaPath('visitors')}
                        className={trafficSeries[0].fill}
                        fillOpacity={0.1}
                    />

                    {trafficSeries.map((series) => (
                        <path
                            key={series.key}
                            d={linePath(series.key)}
                            fill="none"
                            className={series.stroke}
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    ))}

                    {activePoint && active !== null && (
                        <line
                            x1={x(active)}
                            x2={x(active)}
                            y1={plotTop}
                            y2={plotBottom}
                            className="stroke-muted-foreground/50"
                            strokeWidth={1}
                            shapeRendering="crispEdges"
                        />
                    )}

                    {trafficSeries.map((series) => {
                        const index = active ?? last;
                        const point = points[index];

                        return point ? (
                            <circle
                                key={series.key}
                                cx={x(index)}
                                cy={y(point[series.key])}
                                r={4}
                                className={cn(series.fill, 'stroke-card')}
                                strokeWidth={2}
                            />
                        ) : null;
                    })}

                    {endLabels &&
                        trafficSeries.map((series) => (
                            <text
                                key={series.key}
                                x={plotRight + 10}
                                y={y(lastPoint[series.key])}
                                dy="0.32em"
                                className="fill-foreground text-xs font-medium tabular-nums"
                            >
                                {formatNumber(lastPoint[series.key])}
                            </text>
                        ))}

                    <rect
                        x={plotLeft}
                        y={plotTop}
                        width={plotWidth}
                        height={plotHeight}
                        fill="transparent"
                        onPointerMove={pickIndex}
                        onPointerDown={pickIndex}
                        onPointerLeave={() => setActive(null)}
                    />
                </svg>
            )}

            {activePoint && (
                <div
                    role="status"
                    className={cn(
                        'pointer-events-none absolute top-0 z-10 min-w-36 rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-xs',
                        tooltipOnLeft && '-translate-x-full',
                    )}
                    style={{ left: tooltipLeft }}
                >
                    <p className="mb-1.5 text-muted-foreground">
                        {formatDay(activePoint.date, {
                            weekday: 'short',
                            month: 'short',
                            day: 'numeric',
                        })}
                    </p>
                    <ul className="flex flex-col gap-1">
                        {trafficSeries.map((series) => (
                            <li
                                key={series.key}
                                className="flex items-center gap-2"
                            >
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'h-0.5 w-3 rounded-full',
                                        series.swatch,
                                    )}
                                />
                                <span className="font-semibold text-foreground tabular-nums">
                                    {formatNumber(activePoint[series.key])}
                                </span>
                                <span className="text-muted-foreground">
                                    {t(series.label)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {width === 0 && <div style={{ height: HEIGHT }} />}
        </div>
    );
}

// Legend above the chart: a short line key for each series.
export function TrafficLegend() {
    return (
        <ul className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            {trafficSeries.map((series) => (
                <li key={series.key} className="flex items-center gap-2">
                    <span
                        aria-hidden="true"
                        className={cn('h-0.5 w-4 rounded-full', series.swatch)}
                    />
                    {t(series.label)}
                </li>
            ))}
        </ul>
    );
}

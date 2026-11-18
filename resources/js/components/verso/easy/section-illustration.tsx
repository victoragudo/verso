import type { ReactNode } from 'react';

// Small drawings of the ready sections for the "Add section" gallery. Lines use the text color, blocks the accent.

const LINE = 'fill-current opacity-25';
const ACCENT = 'fill-brand/70';
const SOFT = 'fill-current opacity-10';

const lines = (x: number, y: number, widths: number[], gap = 7) =>
    widths.map((width, index) => (
        <rect
            key={index}
            x={x}
            y={y + index * gap}
            width={width}
            height="3"
            rx="1.5"
            className={LINE}
        />
    ));

const card = (
    x: number,
    y: number,
    w: number,
    h: number,
    children?: ReactNode,
) => (
    <g>
        <rect x={x} y={y} width={w} height={h} rx="4" className={SOFT} />
        {children}
    </g>
);

const DRAWINGS: Record<string, ReactNode> = {
    hero: (
        <>
            {card(8, 8, 144, 84)}
            <rect
                x="20"
                y="26"
                width="80"
                height="8"
                rx="3"
                className="fill-current opacity-60"
            />
            {lines(20, 42, [96, 70])}
            <rect
                x="20"
                y="62"
                width="40"
                height="12"
                rx="4"
                className={ACCENT}
            />
        </>
    ),
    about: (
        <>
            <rect
                x="12"
                y="16"
                width="56"
                height="7"
                rx="3"
                className="fill-current opacity-60"
            />
            {lines(12, 32, [64, 60, 64, 48, 60, 40])}
            <rect
                x="88"
                y="16"
                width="60"
                height="68"
                rx="4"
                className={ACCENT}
                opacity="0.35"
            />
        </>
    ),
    services: (
        <>
            <rect
                x="52"
                y="10"
                width="56"
                height="6"
                rx="3"
                className="fill-current opacity-60"
            />
            {[10, 58, 106].map((x) =>
                card(
                    x,
                    26,
                    44,
                    62,
                    <>
                        {lines(x + 6, 50, [28, 32, 20])}
                        <rect
                            x={x + 6}
                            y={33}
                            width="12"
                            height="10"
                            rx="2"
                            className={ACCENT}
                        />
                    </>,
                ),
            )}
        </>
    ),
    gallery: (
        <>
            {[0, 1, 2].flatMap((col) =>
                [0, 1].map((row) => (
                    <rect
                        key={`${col}-${row}`}
                        x={12 + col * 46}
                        y={12 + row * 40}
                        width="42"
                        height="36"
                        rx="3"
                        className={ACCENT}
                        opacity={0.25 + 0.15 * ((col + row) % 3)}
                    />
                )),
            )}
        </>
    ),
    testimonials: (
        <>
            {[12, 84].map((x) =>
                card(
                    x,
                    18,
                    64,
                    64,
                    <>
                        <text
                            x={x + 6}
                            y={38}
                            className="fill-brand text-[20px] font-bold"
                        >
                            “
                        </text>
                        {lines(x + 8, 44, [46, 40, 30])}
                        <rect
                            x={x + 8}
                            y={68}
                            width="24"
                            height="3"
                            rx="1.5"
                            className="fill-current opacity-60"
                        />
                    </>,
                ),
            )}
        </>
    ),
    pricing: (
        <>
            {[16, 84].map((x, index) => (
                <g key={x}>
                    <rect
                        x={x}
                        y={12}
                        width="60"
                        height="76"
                        rx="4"
                        className={SOFT}
                    />
                    {index === 1 && (
                        <rect
                            x={x}
                            y={12}
                            width="60"
                            height="76"
                            rx="4"
                            fill="none"
                            strokeWidth="2"
                            className="stroke-brand"
                        />
                    )}
                    <rect
                        x={x + 8}
                        y={22}
                        width="28"
                        height="9"
                        rx="3"
                        className="fill-current opacity-60"
                    />
                    {lines(x + 8, 40, [40, 36, 40])}
                    <rect
                        x={x + 8}
                        y={68}
                        width="44"
                        height="11"
                        rx="4"
                        className={ACCENT}
                    />
                </g>
            ))}
        </>
    ),
    team: (
        <>
            {[22, 66, 110].map((x) => (
                <g key={x}>
                    <circle
                        cx={x + 14}
                        cy={34}
                        r="14"
                        className={ACCENT}
                        opacity="0.4"
                    />
                    <rect
                        x={x}
                        y={56}
                        width="28"
                        height="4"
                        rx="2"
                        className="fill-current opacity-60"
                    />
                    {lines(x + 2, 66, [24, 18])}
                </g>
            ))}
        </>
    ),
    faq: (
        <>
            {[14, 38, 62].map((y) => (
                <g key={y}>
                    <rect
                        x="16"
                        y={y}
                        width="128"
                        height="18"
                        rx="4"
                        className={SOFT}
                    />
                    <rect
                        x="24"
                        y={y + 7}
                        width="70"
                        height="4"
                        rx="2"
                        className="fill-current opacity-60"
                    />
                    <text
                        x="132"
                        y={y + 13}
                        className="fill-brand text-[12px] font-bold"
                    >
                        +
                    </text>
                </g>
            ))}
        </>
    ),
    contact: (
        <>
            <rect
                x="12"
                y="14"
                width="52"
                height="7"
                rx="3"
                className="fill-current opacity-60"
            />
            {lines(12, 30, [56, 48, 52])}
            {[18, 38].map((y) => (
                <rect
                    key={y}
                    x="84"
                    y={y - 4}
                    width="64"
                    height="14"
                    rx="3"
                    className={SOFT}
                />
            ))}
            <rect
                x="84"
                y="56"
                width="64"
                height="18"
                rx="3"
                className={SOFT}
            />
            <rect
                x="84"
                y="80"
                width="30"
                height="10"
                rx="4"
                className={ACCENT}
            />
        </>
    ),
    map: (
        <>
            <rect
                x="12"
                y="10"
                width="136"
                height="80"
                rx="4"
                className={SOFT}
            />
            <path
                d="M12 60 L60 40 L100 64 L148 34"
                fill="none"
                strokeWidth="3"
                className="stroke-current opacity-25"
            />
            <path
                d="M80 26a10 10 0 0 1 10 10c0 8-10 18-10 18s-10-10-10-18a10 10 0 0 1 10-10z"
                className={ACCENT}
            />
        </>
    ),
    cta: (
        <>
            {card(10, 22, 140, 56)}
            <rect
                x="22"
                y="36"
                width="70"
                height="8"
                rx="3"
                className="fill-current opacity-60"
            />
            {lines(22, 52, [60])}
            <rect
                x="104"
                y="40"
                width="36"
                height="14"
                rx="4"
                className={ACCENT}
            />
        </>
    ),
};

const FALLBACK = (
    <>
        <rect
            x="16"
            y="14"
            width="64"
            height="8"
            rx="3"
            className="fill-current opacity-60"
        />
        {lines(16, 32, [128, 112, 120, 80])}
        <rect x="16" y="66" width="40" height="12" rx="4" className={ACCENT} />
    </>
);

export function hasIllustration(key: string): boolean {
    return key in DRAWINGS;
}

export function SectionIllustration({ sectionKey }: { sectionKey: string }) {
    return (
        <svg
            viewBox="0 0 160 100"
            aria-hidden="true"
            focusable="false"
            className="block h-auto w-full text-foreground"
            data-section-drawing={
                hasIllustration(sectionKey) ? sectionKey : 'default'
            }
        >
            {DRAWINGS[sectionKey] ?? FALLBACK}
        </svg>
    );
}

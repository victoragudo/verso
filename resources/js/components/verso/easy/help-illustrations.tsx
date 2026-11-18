import type { ReactNode } from 'react';

// Calm line drawings for the help panel, one per topic, in the Verso icon style (round 1.5 strokes, currentColor).
// The accent parts use the brand color, the rest follows the text color.

type Props = { name: string; className?: string };

function Frame({ children }: { children: ReactNode }) {
    return (
        <>
            <rect
                x="8"
                y="8"
                width="184"
                height="104"
                rx="8"
                className="fill-card"
            />
            <path d="M8 26h184" />
            <circle cx="20" cy="17" r="2" />
            <circle cx="28" cy="17" r="2" />
            <circle cx="36" cy="17" r="2" />
            {children}
        </>
    );
}

const accent = 'text-brand';

const drawings: Record<string, ReactNode> = {
    starter: (
        <Frame>
            {[20, 76, 132].map((x, index) => (
                <g key={x} className={index === 1 ? accent : undefined}>
                    <rect x={x} y="38" width="48" height="60" rx="5" />
                    <path d={`M${x + 8} 50h32M${x + 8} 60h20M${x + 8} 70h26`} />
                    <rect x={x + 8} y="80" width="18" height="8" rx="2" />
                </g>
            ))}
            <path className={accent} d="M116 92l6 6 12-14" />
        </Frame>
    ),
    style: (
        <Frame>
            <rect x="20" y="38" width="36" height="24" rx="4" />
            <path d="M30 50h16" />
            {[0, 1, 2, 3].map((index) => (
                <circle
                    key={index}
                    cx={30 + index * 16}
                    cy="84"
                    r="7"
                    className={index === 1 ? accent : undefined}
                />
            ))}
            <path d="M104 40h70M104 52h50" />
            <text
                x="104"
                y="88"
                className="fill-current stroke-none"
                fontSize="26"
                fontFamily="Georgia, serif"
            >
                Aa
            </text>
            <path className={accent} d="M144 72h30M144 82h22" />
        </Frame>
    ),
    'edit-page': (
        <Frame>
            <path d="M24 42h96" />
            <rect
                className={accent}
                x="20"
                y="52"
                width="112"
                height="22"
                rx="3"
                strokeDasharray="4 3"
            />
            <path className={accent} d="M28 63h70" />
            <path className={accent} d="M100 58v10" />
            <path d="M24 86h80M24 96h60" />
            <rect x="142" y="42" width="38" height="54" rx="4" />
            <path d="M148 86l9-11 8 8 5-5 6 8" />
            <path className={accent} d="M118 80l14 14 4-8 8-4z" />
        </Frame>
    ),
    'add-section': (
        <Frame>
            <rect x="20" y="36" width="160" height="26" rx="4" />
            <path d="M30 46h60M30 54h40" />
            <path className={accent} d="M20 74h66M114 74h66" />
            <circle className={accent} cx="100" cy="74" r="9" />
            <path className={accent} d="M100 70v8M96 74h8" />
            <rect
                x="20"
                y="86"
                width="160"
                height="18"
                rx="4"
                strokeDasharray="4 3"
            />
        </Frame>
    ),
    menu: (
        <Frame>
            <path d="M22 40h20" />
            {[72, 104, 136].map((x, index) => (
                <path
                    key={x}
                    d={`M${x} 40h22`}
                    className={index === 1 ? accent : undefined}
                />
            ))}
            {[56, 74, 92].map((y, index) => (
                <g key={y} className={index === 1 ? accent : undefined}>
                    <rect x="40" y={y} width="120" height="13" rx="3" />
                    <path
                        d={`M48 ${y + 4.5}h4M48 ${y + 8.5}h4M60 ${y + 6.5}h46`}
                    />
                </g>
            ))}
        </Frame>
    ),
    'blog-post': (
        <Frame>
            <path d="M28 42h88" strokeWidth="3" />
            <rect
                className={accent}
                x="28"
                y="52"
                width="60"
                height="38"
                rx="4"
            />
            <path className={accent} d="M34 84l12-14 10 9 8-6 18 11" />
            <path d="M98 56h74M98 66h64M98 76h70M98 86h50M28 100h120" />
        </Frame>
    ),
    photos: (
        <Frame>
            {[
                [24, 38],
                [72, 38],
                [24, 74],
                [72, 74],
            ].map(([x, y]) => (
                <g key={`${x}-${y}`}>
                    <rect x={x} y={y} width="40" height="28" rx="3" />
                    <path d={`M${x + 4} ${y + 24}l10-11 8 7 6-4 8 8`} />
                </g>
            ))}
            <rect
                className={accent}
                x="124"
                y="38"
                width="56"
                height="64"
                rx="6"
                strokeDasharray="4 3"
            />
            <path className={accent} d="M152 80V58M144 66l8-8 8 8" />
        </Frame>
    ),
    'go-live': (
        <Frame>
            <rect x="24" y="40" width="100" height="14" rx="7" />
            <circle cx="34" cy="47" r="3" />
            <path d="M42 47h50" />
            <path d="M24 66h80M24 76h60M24 86h70" />
            <path
                className={accent}
                d="M152 96V70M152 70c0-12 8-24 8-24s8 12 8 24v8h-16z"
                transform="translate(-8 0)"
            />
            <path className={accent} d="M136 84l8-6M168 84l-8-6" />
            <path className={accent} d="M148 104v-4M152 106v-6M156 104v-4" />
        </Frame>
    ),
    generic: (
        <Frame>
            <circle className={accent} cx="100" cy="66" r="22" />
            <path
                className={accent}
                d="M93 60a7 7 0 1 1 10 6c-2 1-3 2-3 5M100 78v1"
            />
        </Frame>
    ),
};

// True when there is a drawing for this key. Topics with an unknown key get the generic one.
export function hasHelpIllustration(name: string): boolean {
    return Object.hasOwn(drawings, name);
}

export function HelpIllustration({ name, className }: Props) {
    return (
        <svg
            viewBox="0 0 200 120"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
            data-illustration={hasHelpIllustration(name) ? name : 'generic'}
            className={className}
        >
            {drawings[name] ?? drawings.generic}
        </svg>
    );
}

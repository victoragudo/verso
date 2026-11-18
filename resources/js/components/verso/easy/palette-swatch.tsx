import type { Palette } from '@/lib/easy/contrast';

// A tiny page drawn in the palette colors: background, a panel, text, quiet text and an accent button.
export function PaletteSwatch({ colors }: { colors: Palette }) {
    return (
        <span
            aria-hidden="true"
            className="grid gap-1.5 rounded-md border p-2.5 forced-color-adjust-none"
            style={{ background: colors.background, color: colors.text }}
        >
            <span className="text-base leading-none font-semibold">Aa</span>
            <span
                className="block h-1.5 w-3/4 rounded-full"
                style={{ background: colors.muted ?? colors.text }}
            />
            <span className="flex items-center gap-1.5">
                <span
                    className="block h-4 w-10 rounded-sm"
                    style={{ background: colors.accent }}
                />
                <span
                    className="block h-4 flex-1 rounded-sm"
                    style={{ background: colors.surface ?? colors.background }}
                />
            </span>
        </span>
    );
}

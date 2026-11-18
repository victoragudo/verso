import type { ColorRole, Palette } from './contrast';

// Builds the token values and the preview address of the picks on "Make it yours".

export type ColorRoleInfo = { role: ColorRole; token: string; label: string };

export type StylePresetInfo = {
    key: string;
    label: string;
    description: string;
    tokens: Record<string, string>;
    fontPair: string | null;
};

export type FontPairInfo = {
    key: string;
    label: string;
    heading: string;
    body: string;
    sample: string;
    headingLabel: string;
    bodyLabel: string;
    headingStack: string;
    bodyStack: string;
    tokens: Record<string, string>;
};

export type StyleChoice = {
    colors: Palette;
    preset: StylePresetInfo | null;
    fontPair: FontPairInfo | null;
};

// Token id => value: preset first, then fonts, then colors, like the server does.
export function choiceTokens(
    choice: StyleChoice,
    roles: ColorRoleInfo[],
): Record<string, string> {
    const tokens: Record<string, string> = {
        ...choice.preset?.tokens,
        ...choice.fontPair?.tokens,
    };

    for (const { role, token } of roles) {
        const color = choice.colors[role];

        if (color) {
            tokens[token] = color;
        }
    }

    return tokens;
}

// The font pair a style preset suggests, unless the person already chose fonts on this visit.
export function suggestedFontPair(
    preset: StylePresetInfo | undefined,
    fontPairs: FontPairInfo[],
    fontPairChosen: boolean,
): string | null {
    const suggested = preset?.fontPair ?? null;

    if (
        fontPairChosen ||
        suggested === null ||
        !fontPairs.some((pair) => pair.key === suggested)
    ) {
        return null;
    }

    return suggested;
}

// The preview frame address with ?tokens[...] values and, when the logo changed, ?logo=<id> (0 for none).
export function previewSrc(
    base: string,
    tokens: Record<string, string>,
    logo?: number | null,
): string {
    const params = new URLSearchParams(
        Object.entries(tokens).map(([id, value]) => [`tokens[${id}]`, value]),
    );

    if (logo !== undefined) {
        params.set('logo', String(logo ?? 0));
    }

    const query = params.toString();

    return query === ''
        ? base
        : `${base}${base.includes('?') ? '&' : '?'}${query}`;
}

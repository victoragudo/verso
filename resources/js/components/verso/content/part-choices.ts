import type { PartChoice } from '@/types';

// Select value for "use the style of the template or the whole site".
export const INHERIT_STYLE = 'inherit';

// The styles saved in meta.parts, only for parts the theme still offers or that hold a value.
export function partsFromMeta(
    meta: Record<string, unknown> | undefined,
): Record<string, string> {
    const parts = meta?.parts;

    if (!parts || typeof parts !== 'object' || Array.isArray(parts)) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(parts).filter(
            (entry): entry is [string, string] =>
                typeof entry[1] === 'string' && entry[1] !== '',
        ),
    );
}

// The value for meta.parts: picked styles only, or undefined when nothing is picked.
export function partsForMeta(
    picked: Record<string, string>,
): Record<string, string> | undefined {
    const parts = Object.fromEntries(
        Object.entries(picked).filter(([, style]) => style !== INHERIT_STYLE),
    );

    return Object.keys(parts).length > 0 ? parts : undefined;
}

// Label of the style a part gets when the select says "inherit".
export function inheritedLabel(choice: PartChoice, style?: string): string {
    const key = style ?? choice.default;
    const option = choice.options.find((item) => item.value === key);

    return option?.label ?? key;
}

// Errors of meta.parts.<part> by part name.
export function partErrors(
    errors: Record<string, string | undefined>,
): Record<string, string | undefined> {
    const prefix = 'meta.parts.';

    return Object.fromEntries(
        Object.entries(errors)
            .filter(([key]) => key.startsWith(prefix))
            .map(([key, message]) => [key.slice(prefix.length), message]),
    );
}

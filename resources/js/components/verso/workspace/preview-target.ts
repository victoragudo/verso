// A page the preview can show. usesLayout says if the rules of the layout pick it for that page (null when unknown).
export type PreviewTarget = {
    value: string;
    label: string;
    usesLayout?: boolean | null;
};

const FALLBACK_TARGET = 'home';

// The first page that uses the layout, else the first page. A single post template opens on a post, not the home page.
export function defaultPreviewTarget(targets: PreviewTarget[]): string {
    return (
        targets.find((target) => target.usesLayout === true)?.value ??
        targets[0]?.value ??
        FALLBACK_TARGET
    );
}

// The page to show: the one people picked while it still exists, else the default.
export function previewTargetFor(
    targets: PreviewTarget[],
    picked: string | null,
): string {
    if (picked !== null && targets.some((target) => target.value === picked)) {
        return picked;
    }

    return defaultPreviewTarget(targets);
}

// True or false when the server knows if the page uses the layout, null otherwise.
export function targetUsesLayout(
    targets: PreviewTarget[],
    value: string,
): boolean | null {
    return targets.find((target) => target.value === value)?.usesLayout ?? null;
}

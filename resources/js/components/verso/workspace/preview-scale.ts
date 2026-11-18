// How the preview frame fits its pane. The page is drawn at frameWidth and shrunk by scale (1 means real size).
export type PreviewFit = { frameWidth: number; scale: number };

// A computer preview is drawn at least this wide, so narrow panes show the computer layout, made smaller.
export const DEFAULT_DESKTOP_MIN_WIDTH = 1280;

export function previewFit(input: {
    paneWidth: number;
    deviceWidth: number | null;
    desktopMinWidth: number;
}): PreviewFit {
    const { paneWidth, deviceWidth, desktopMinWidth } = input;
    const frameWidth =
        deviceWidth ?? Math.max(Math.round(paneWidth), desktopMinWidth);

    if (paneWidth <= 0 || frameWidth <= 0) {
        return { frameWidth, scale: 1 };
    }

    return { frameWidth, scale: Math.min(1, paneWidth / frameWidth) };
}

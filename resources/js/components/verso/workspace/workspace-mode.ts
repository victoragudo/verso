// How much fits in the builder workspace at a width (in pixels, of the workspace itself).
// wide: every pane open at once. large: the settings stay open, the blocks panel is a toggle.
// medium: canvas and preview side by side, panels are toggles. narrow: Edit and Preview tabs, panels are sheets.
export type WorkspaceMode = 'wide' | 'large' | 'medium' | 'narrow';

const REM = 16;

export const WORKSPACE_WIDTHS: { mode: WorkspaceMode; from: number }[] = [
    { mode: 'wide', from: 150 * REM },
    { mode: 'large', from: 100 * REM },
    { mode: 'medium', from: 64 * REM },
    { mode: 'narrow', from: 0 },
];

export function workspaceMode(width: number): WorkspaceMode {
    return (
        WORKSPACE_WIDTHS.find((item) => width >= item.from)?.mode ?? 'narrow'
    );
}

// The settings column starts open when there is room for it next to the canvas and the preview.
export function settingsOpenByDefault(mode: WorkspaceMode): boolean {
    return mode === 'wide' || mode === 'large';
}

// The Add blocks panel starts open only when every pane fits.
export function blocksPanelOpenByDefault(mode: WorkspaceMode): boolean {
    return mode === 'wide';
}

// The settings sit in a column from the medium width; below it they open as a sheet.
export function settingsInline(mode: WorkspaceMode): boolean {
    return mode !== 'narrow';
}

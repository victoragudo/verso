// Shapes of the theme builder pages (App\Verso\Layouts on the server).

export type LayoutKindName = 'template' | 'header' | 'footer' | 'popup';

export type LayoutRule = { type: string; value: string; exclude: boolean };

export type LayoutConditionDefinition = {
    key: string;
    label: string;
    options: { value: string; label: string }[] | null;
};

export type LayoutSummary = {
    id: number;
    kind: LayoutKindName;
    kindLabel: string;
    name: string;
    status: 'draft' | 'published' | 'off';
    live: boolean;
    priority: number;
    conditions: LayoutRule[];
    updatedAt: string | null;
    editUrl: string;
};

export type LayoutSettingsValue = Record<string, unknown>;

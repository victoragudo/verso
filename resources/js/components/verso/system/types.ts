// Data shared by the System settings and Server health pages.

export type SettingType =
    | 'integer'
    | 'decimal'
    | 'boolean'
    | 'text'
    | 'choice'
    | 'list';

export type SettingValue = number | boolean | string | string[] | null;

export type UploadPart = {
    key: string;
    label: string;
    bytes: number | null;
    note: string;
};

export type UploadLimit = {
    bytes: number;
    human: string;
    bottleneck: string;
    limitedByServer: boolean;
    explanation: string;
    parts: UploadPart[];
};

export type SystemSetting = {
    key: string;
    group: string;
    label: string;
    help: string;
    type: SettingType;
    unit: string;
    dimension: 'size' | 'time' | null;
    min: number | null;
    max: number | null;
    options: { value: string; label: string }[];
    env: string | null;
    apply: { key: string; label: string }[];
    limits: string[];
    format: string | null;
    value: SettingValue;
    default: SettingValue;
    changed: boolean;
    envLocked: boolean;
    problem: string | null;
    upload: UploadLimit | null;
};

export type SystemGroup = {
    key: string;
    label: string;
    description: string;
    icon: string;
    plugin: string | null;
};

export type ServerSnippet = {
    key: string;
    label: string;
    file: string;
    code: string;
    recommended: boolean;
};

export type ServerLimits = {
    sapi: string;
    webServer: string;
    phpUpload: Record<string, string | null>;
    target: {
        key: string;
        label: string;
        file: string;
        writable: boolean;
        preview: string | null;
    } | null;
    cacheTtl: number;
    recommended: {
        name: string;
        current: string | null;
        value: string;
        scope: string;
    }[];
    snippets: ServerSnippet[];
};

export type AuditEntry = {
    id: number;
    action: 'update' | 'reset' | 'ini_write' | 'permissions_fix';
    key: string | null;
    label: string | null;
    old: unknown;
    new: unknown;
    details: Record<string, unknown> | null;
    user: string | null;
    at: string | null;
};

export type HealthStatus = 'ok' | 'info' | 'warning' | 'danger';

export type PlannedFix = {
    type: string;
    path: string;
    mode: string | null;
    description: string;
};

export type HealthCheck = {
    key: string;
    group: 'folders' | 'files' | 'web';
    label: string;
    status: HealthStatus;
    detail: string;
    path: string | null;
    mode: string | null;
    recommended: string | null;
    fixes: PlannedFix[];
    command: string | null;
};

export type IniValue = {
    name: string;
    value: string | null;
    about: string;
    runtime: boolean;
    perDirectory: boolean;
    scope: string;
};

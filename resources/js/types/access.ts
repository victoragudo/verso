// Types of the roles and permissions admin pages.

export type CapabilityRisk = 'low' | 'medium' | 'high' | 'critical';

// One thing a role can allow, like "Publish posts".
export type Capability = {
    key: string;
    label: string;
    description: string;
    group: string;
    section: string | null;
    risk: CapabilityRisk;
    source: string;
    implies: string[];
};

// A role with its capabilities and what the viewer may do with it.
export type AccessRole = {
    slug: string;
    name: string;
    description: string;
    capabilities: string[];
    source: string;
    builtIn: boolean;
    locked: boolean;
    modified: boolean;
    basedOn: string | null;
    users: number;
    canAssign: boolean;
};

export type AccessAuditAction =
    | 'role.create'
    | 'role.update'
    | 'role.delete'
    | 'role.reset'
    | 'user.role'
    | 'user.access'
    | 'role.view_as';

// One line of the roles audit log.
export type AccessAuditEntry = {
    id: number;
    action: AccessAuditAction;
    key: string | null;
    user: string | null;
    at: string | null;
    old: unknown;
    new: unknown;
    details: Record<string, unknown> | null;
};

export type RoleMember = {
    id: number;
    name: string;
    email: string;
    main: boolean;
};

export type RoleMembers = {
    total: number;
    items: RoleMember[];
};

export type RoleSummary = {
    slug: string;
    name: string;
};

// The user shown on the access page.
export type AccessSubject = {
    id: number;
    name: string;
    email: string;
    role: string;
    extraRoles: string[];
    overrides: Record<string, boolean>;
    effective: string[];
    isAdmin: boolean;
    isSelf: boolean;
};

export type ViewAsState = {
    role: string;
    name: string;
};

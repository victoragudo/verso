import type { AccessAuditEntry, AccessRole, Capability } from '@/types';

// Capabilities and roles of a small bakery site, shared by the access tests.

function capability(
    key: string,
    label: string,
    group: string,
    section: string | null,
    extra: Partial<Capability> = {},
): Capability {
    return {
        key,
        label,
        description: '',
        group,
        section,
        risk: 'low',
        source: 'core',
        implies: [],
        ...extra,
    };
}

export const capabilities: Capability[] = [
    capability(
        'publish_entries',
        'Publish entries',
        'Content',
        'All content types',
        {
            risk: 'medium',
            description: 'Make posts and pages public.',
        },
    ),
    capability(
        'delete_others_entries',
        'Delete entries of others',
        'Content',
        'All content types',
        {
            risk: 'high',
        },
    ),
    capability('upload_media', 'Upload files', 'Content', 'Media'),
    capability('manage_users', 'Manage users', 'Administration', 'Users', {
        risk: 'critical',
        implies: ['list_users'],
    }),
    capability('list_users', 'See the user list', 'Administration', 'Users'),
    capability('manage_roles', 'Manage roles', 'Administration', 'Users', {
        risk: 'critical',
    }),
    capability('manage_settings', 'Change site settings', 'Settings', null, {
        risk: 'high',
    }),
    capability('seo.redirects', 'Edit redirects', 'SEO toolkit', null, {
        source: 'seo-toolkit',
    }),
];

export const allKeys = capabilities.map((item) => item.key);

function role(
    slug: string,
    name: string,
    extra: Partial<AccessRole>,
): AccessRole {
    return {
        slug,
        name,
        description: '',
        capabilities: [],
        source: 'core',
        builtIn: true,
        locked: false,
        modified: false,
        basedOn: null,
        users: 0,
        canAssign: true,
        ...extra,
    };
}

export const administrator = role('admin', 'Administrator', {
    locked: true,
    capabilities: allKeys,
    users: 2,
    description: 'Runs the whole site.',
});

export const editor = role('editor', 'Editor', {
    capabilities: [
        'publish_entries',
        'delete_others_entries',
        'upload_media',
        'list_users',
    ],
    users: 3,
});

export const author = role('author', 'Author', {
    capabilities: ['publish_entries', 'upload_media'],
    modified: true,
    users: 5,
});

export const seoManager = role('seo_manager', 'SEO manager', {
    capabilities: ['seo.redirects', 'upload_media'],
    builtIn: false,
    source: 'custom',
    basedOn: 'editor',
    users: 1,
    description: 'Looks after redirects and search snippets.',
});

export const roles: AccessRole[] = [administrator, editor, author, seoManager];

// The viewer has every capability except settings and roles.
export const grantable = allKeys.filter(
    (key) => key !== 'manage_settings' && key !== 'manage_roles',
);

export const audit: AccessAuditEntry[] = [
    {
        id: 41,
        action: 'role.update',
        key: 'author',
        user: 'Marta Ribeiro',
        at: '2026-11-12T09:30:00+00:00',
        old: ['publish_entries'],
        new: ['publish_entries', 'upload_media'],
        details: { name: 'Author', added: ['upload_media'] },
    },
    {
        id: 40,
        action: 'user.role',
        key: 'seo_manager',
        user: 'Marta Ribeiro',
        at: '2026-11-11T16:05:00+00:00',
        old: null,
        new: 'added',
        details: { user: 'Tomás Aguilar', user_id: 9 },
    },
];

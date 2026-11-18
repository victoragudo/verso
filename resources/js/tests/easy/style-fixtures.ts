import type {
    ColorRoleInfo,
    FontPairInfo,
    StylePresetInfo,
} from '@/lib/easy/style-choice';

// Props like the server sends for the Ashgrove theme of Harbor Lane Bakery.

export const roles: ColorRoleInfo[] = [
    { role: 'accent', token: 'color.accent', label: 'Accent' },
    { role: 'background', token: 'color.background', label: 'Background' },
    { role: 'text', token: 'color.text', label: 'Text' },
    { role: 'muted', token: 'color.muted', label: 'Quiet text' },
    { role: 'surface', token: 'color.surface', label: 'Panels' },
];

export const currentColors = {
    accent: '#2e5e4e',
    background: '#f6f7f3',
    text: '#1b2420',
    muted: '#545f59',
    surface: '#eceee8',
};

export const calmPreset: StylePresetInfo = {
    key: 'calm',
    label: 'Calm',
    description: 'Soft corners and room to breathe. Easy on the eyes.',
    tokens: { 'radius.base': '6px', 'spacing.small': '0.5rem' },
    fontPair: 'classic',
};

export const boldPreset: StylePresetInfo = {
    key: 'bold',
    label: 'Bold',
    description: 'Square corners, strong headings and big gaps. Hard to miss.',
    tokens: { 'radius.base': '0px', 'spacing.small': '0.5rem' },
    fontPair: 'modern',
};

export const classicPair: FontPairInfo = {
    key: 'classic',
    label: 'Classic',
    heading: 'georgia',
    body: 'charter',
    sample: 'Fresh bread every morning, baked by the harbor.',
    headingLabel: 'Georgia (serif)',
    bodyLabel: 'Charter (serif)',
    headingStack: "Georgia, 'Times New Roman', Times, serif",
    bodyStack: 'Charter, Georgia, serif',
    tokens: { 'font.heading': 'georgia', 'font.body': 'charter' },
};

export const modernPair: FontPairInfo = {
    key: 'modern',
    label: 'Clean and modern',
    heading: 'system',
    body: 'system',
    sample: 'Open seven days a week, from eight to six.',
    headingLabel: 'System (sans serif)',
    bodyLabel: 'System (sans serif)',
    headingStack: 'system-ui, sans-serif',
    bodyStack: 'system-ui, sans-serif',
    tokens: { 'font.heading': 'system', 'font.body': 'system' },
};

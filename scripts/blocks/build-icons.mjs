// Builds resources/blocks/icons/icons.json, the bundled icon set of the icon blocks, from Lucide (ISC license).
// Run: node scripts/blocks/build-icons.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const ICONS = [
    'heart',
    'star',
    'check',
    'circle-check',
    'x',
    'circle-alert',
    'info',
    'triangle-alert',
    'phone',
    'mail',
    'map-pin',
    'clock',
    'calendar',
    'calendar-days',
    'house',
    'building-2',
    'store',
    'shopping-cart',
    'shopping-bag',
    'credit-card',
    'truck',
    'package',
    'gift',
    'tag',
    'percent',
    'user',
    'users',
    'smile',
    'thumbs-up',
    'message-circle',
    'message-square',
    'send',
    'share-2',
    'link',
    'globe',
    'search',
    'settings',
    'wrench',
    'hammer',
    'lightbulb',
    'rocket',
    'zap',
    'shield-check',
    'lock',
    'key',
    'leaf',
    'sprout',
    'sun',
    'moon',
    'cloud',
    'snowflake',
    'flame',
    'droplet',
    'coffee',
    'utensils',
    'croissant',
    'cake',
    'wine',
    'pizza',
    'cookie',
    'camera',
    'image',
    'video',
    'music',
    'mic',
    'headphones',
    'book-open',
    'graduation-cap',
    'pen-tool',
    'palette',
    'paintbrush',
    'scissors',
    'briefcase',
    'chart-bar',
    'chart-line',
    'trending-up',
    'award',
    'trophy',
    'medal',
    'crown',
    'target',
    'compass',
    'map',
    'plane',
    'car',
    'bike',
    'dumbbell',
    'activity',
    'stethoscope',
    'paw-print',
    'baby',
    'flower-2',
    'tree-pine',
    'mountain',
    'waves',
    'anchor',
    'sparkles',
    'circle-play',
    'download',
    'upload',
    'arrow-right',
    'arrow-left',
    'arrow-up',
    'arrow-down',
    'chevron-right',
    'chevron-down',
    'plus',
    'minus',
    'quote',
    'badge-check',
    'menu',
];

const root = resolve(import.meta.dirname, '../..');
const iconsDir = resolve(root, 'node_modules/lucide-react/dist/esm/icons');
const icons = {};

// Lucide 1.x keeps old names as small files that point to the new name, so follow that link.
async function iconNodes(name) {
    const file = resolve(iconsDir, `${name}.mjs`);
    const { __iconData: iconData } = await import(pathToFileURL(file).href);

    if (iconData) {
        return iconData.node;
    }

    const target = readFileSync(file, 'utf8').match(
        /from '\.\/([a-z0-9-]+)\.mjs'/,
    );

    return target ? iconNodes(target[1]) : undefined;
}

for (const name of ICONS) {
    const nodes = await iconNodes(name);

    if (!Array.isArray(nodes)) {
        throw new Error(`No icon data in ${name}`);
    }

    icons[name] = nodes
        .map(([tag, attrs]) => {
            const parts = Object.entries(attrs)
                .filter(([key]) => key !== 'key')
                .map(
                    ([key, value]) =>
                        `${key}="${String(value).replace(/"/g, '&quot;')}"`,
                );

            return `<${tag} ${parts.join(' ')}/>`;
        })
        .join('');
}

writeFileSync(
    resolve(root, 'resources/blocks/icons/icons.json'),
    `${JSON.stringify({ license: 'Lucide icons, ISC license, https://lucide.dev/license', icons }, null, 1)}\n`,
);
console.log(`${Object.keys(icons).length} icons written.`);

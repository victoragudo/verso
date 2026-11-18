import { createRegistry } from './registry';
import type {
    BuilderBlock,
    EditorManifest,
    BuilderBreakpoint,
    BuilderCategory,
    BuilderCodeSerializer,
    BuilderDropRule,
    BuilderInsertSource,
    BuilderInspectorPanel,
    BuilderShortcut,
    BuilderStyleProperty,
    BuilderToolbarButton,
} from './types';
import { BUILDER_API_VERSION } from './types';
import type {
    PageEditorButton,
    PageSectionAction,
} from '@/lib/easy/page-editor-registry';

export * from './types';
export { createRegistry } from './registry';
export type { Registry } from './registry';

const byName = (item: { name: string }) => item.name;
const byOrder = (item: { order?: number }) => item.order ?? 50;

// The public builder API. Core, plugins and themes all add their items here.
export const builder = {
    version: BUILDER_API_VERSION,
    blocks: createRegistry<BuilderBlock>((block) => block.type, byOrder),
    categories: createRegistry<BuilderCategory>(byName, byOrder),
    styleProperties: createRegistry<BuilderStyleProperty>(
        (property) => property.path,
        byOrder,
    ),
    inspectorPanels: createRegistry<BuilderInspectorPanel>(byName, byOrder),
    toolbarButtons: createRegistry<BuilderToolbarButton>(byName, byOrder),
    insertSources: createRegistry<BuilderInsertSource>(byName, byOrder),
    dropRules: createRegistry<BuilderDropRule>(byName, byOrder),
    codeSerializers: createRegistry<BuilderCodeSerializer>(byName),
    shortcuts: createRegistry<BuilderShortcut>(byName),
    breakpoints: createRegistry<BuilderBreakpoint>(byName, byOrder),
    // The visual page editor of Easy mode: top bar buttons and section toolbar actions.
    pageEditorButtons: createRegistry<PageEditorButton>(byName, byOrder),
    pageSectionActions: createRegistry<PageSectionAction>(byName, byOrder),
};

export type BuilderApi = typeof builder;

// What a plugin or theme file "resources/js/builder.ts" exports.
export type BuilderModule = {
    default?: (api: BuilderApi) => void;
};

// True when a block of type "child" may go inside "parent".
// Rules are asked from the highest order down; the first answer wins. With no answer the block is refused.
export function canDrop(parent: string, child: string): boolean {
    const rules = builder.dropRules.all();

    for (let index = rules.length - 1; index >= 0; index--) {
        const answer = rules[index].allows(parent, child);

        if (answer !== null) {
            return answer;
        }
    }

    return false;
}

// Features people get on pages that send no settings, like the newsletter editor.
export const DEFAULT_FEATURES = ['add_panel', 'list_view', 'styles_panel'];

// True when the editor settings give this feature to the current user.
export function featureEnabled(
    manifest: EditorManifest | undefined,
    feature: string,
): boolean {
    return (manifest?.features ?? DEFAULT_FEATURES).includes(feature);
}

// Blocks people can add on this page: every visible block the settings and the theme allow.
export function editorBlockTypes(
    manifest: EditorManifest | undefined,
): BuilderBlock[] {
    const allowed = manifest?.allowedBlocks ?? null;

    return builder.blocks
        .all()
        .filter(
            (block) =>
                !block.hidden &&
                (!block.feature || featureEnabled(manifest, block.feature)) &&
                (allowed === null || allowed.includes(block.type)),
        );
}

function isMac(): boolean {
    return /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
}

// True when a key event matches a shortcut like "Mod+Shift+D" or "Alt+Shift+ArrowUp".
export function matchesShortcut(
    event: Pick<
        KeyboardEvent,
        'key' | 'altKey' | 'shiftKey' | 'ctrlKey' | 'metaKey'
    >,
    keys: string,
): boolean {
    const parts = keys.split('+');
    const key = parts.pop() ?? '';
    const mod = parts.includes('Mod');
    const ctrl = parts.includes('Ctrl') || (mod && !isMac());
    const meta = parts.includes('Meta') || (mod && isMac());

    return (
        event.key.toLowerCase() === key.toLowerCase() &&
        event.altKey === parts.includes('Alt') &&
        event.shiftKey === parts.includes('Shift') &&
        event.ctrlKey === ctrl &&
        event.metaKey === meta
    );
}

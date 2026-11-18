import { slotRegistry } from './registry';
import type { SlotRegistry } from './registry';

// A plugin module at plugins/<slug>/resources/js/index.tsx. Its default export registers components.
export type PluginModule = {
    default?: (registry: SlotRegistry) => void;
};

const pluginModules = import.meta.glob<PluginModule>(
    '/plugins/*/resources/js/index.tsx',
    { eager: true },
);

// Runs the register function of every plugin module, sorted by path. Returns the plugin slugs.
export function loadPluginModules(
    modules: Record<string, PluginModule> = pluginModules,
): string[] {
    const loaded: string[] = [];

    for (const path of Object.keys(modules).sort()) {
        const register = modules[path].default;

        if (typeof register === 'function') {
            register(slotRegistry);
            loaded.push(path.split('/').at(-4) ?? path);
        }
    }

    return loaded;
}

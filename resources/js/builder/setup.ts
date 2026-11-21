import { applyManifest, registerCore, registerPluginBlocks } from './core';
import { editorDevice } from './device';
import { builder } from './index';
import type { BuilderModule, EditorManifest } from './index';
import type { PluginBlockDefinition } from '@/types';

// Builder files of plugins and themes, found when the app is built: plugins/<slug>/resources/js/builder.ts(x)
// and themes/<slug>/resources/js/builder.ts(x).
export function discoverModules(): Record<string, BuilderModule> {
    return import.meta.glob<BuilderModule>(
        [
            '/plugins/*/resources/js/builder.{ts,tsx}',
            '/themes/*/resources/js/builder.{ts,tsx}',
        ],
        { eager: true },
    );
}

let ready = false;

// The folder of a module path, like "plugins/bookings" for "/plugins/bookings/resources/js/builder.ts".
export function moduleFolder(path: string): string {
    return path.replace(/^\//, '').split('/').slice(0, 2).join('/');
}

// Registers core once, then runs the plugin and theme modules the server allows
// (enabled plugins and the active theme). A broken module is reported and skipped.
export function setupBuilder(
    modules: Record<string, BuilderModule> = discoverModules(),
    allowed: string[] | null = null,
): void {
    if (ready) {
        return;
    }

    ready = true;
    registerCore(builder);

    Object.entries(modules)
        .filter(
            ([path]) =>
                allowed === null || allowed.includes(moduleFolder(path)),
        )
        .sort(([a], [b]) => a.localeCompare(b))
        .forEach(([path, module]) => {
            try {
                module.default?.(builder);
            } catch (error) {
                console.error(`The builder module ${path} failed.`, error);
            }
        });
}

// Brings the registries in line with one editor page: core, modules, server blocks and settings.
export function prepareBuilder(
    manifest: EditorManifest | undefined,
    pluginBlocks: PluginBlockDefinition[],
): void {
    setupBuilder(discoverModules(), manifest?.modules ?? []);
    registerPluginBlocks(builder, pluginBlocks);

    if (manifest) {
        applyManifest(builder, manifest);
    }
}

// Clears every registry, so tests start fresh.
export function resetBuilder(): void {
    Object.values(builder).forEach((value) => {
        if (typeof value === 'object' && 'clear' in value) {
            value.clear();
        }
    });
    editorDevice.set('base');
    ready = false;
}

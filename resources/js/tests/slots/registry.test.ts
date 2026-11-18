import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    clearSlotRegistry,
    loadPluginModules,
    registerRowAction,
    registerSlotComponent,
    rowActionBuilder,
    slotComponent,
    slotComponentOptions,
    slotRegistry,
} from '@/components/verso/slots';
import type { PluginModule } from '@/components/verso/slots';

function ReadingTime() {
    return null;
}

function ReadingTimeV2() {
    return null;
}

afterEach(() => clearSlotRegistry());

describe('slot registry', () => {
    it('finds a registered component by key', () => {
        registerSlotComponent('reading-time.badge', ReadingTime);

        expect(slotComponent('reading-time.badge')).toBe(ReadingTime);
        expect(slotComponent('missing.widget')).toBeNull();
    });

    it('replaces a component registered again with the same key', () => {
        registerSlotComponent('reading-time.badge', ReadingTime);
        registerSlotComponent('reading-time.badge', ReadingTimeV2);

        expect(slotComponent('reading-time.badge')).toBe(ReadingTimeV2);
    });

    it('keeps the options of a component', () => {
        const initialMeta = () => ({ reading_time: 4 });
        registerSlotComponent('reading-time.badge', ReadingTime, {
            initialMeta,
        });

        expect(slotComponentOptions('reading-time.badge').initialMeta).toBe(
            initialMeta,
        );
        expect(slotComponentOptions('missing.widget')).toEqual({});
    });

    it('keeps row action builders apart from components', () => {
        const builder = vi.fn(() => null);
        registerRowAction('duplicate.action', builder);

        expect(rowActionBuilder('duplicate.action')).toBe(builder);
        expect(slotComponent('duplicate.action')).toBeNull();
    });

    it('forgets everything when cleared', () => {
        registerSlotComponent('reading-time.badge', ReadingTime);
        registerRowAction('duplicate.action', () => null);
        clearSlotRegistry();

        expect(slotComponent('reading-time.badge')).toBeNull();
        expect(rowActionBuilder('duplicate.action')).toBeNull();
    });
});

describe('loadPluginModules', () => {
    it('runs the register function of each plugin module in path order', () => {
        const calls: string[] = [];
        const modules: Record<string, PluginModule> = {
            '/plugins/shop/resources/js/index.tsx': {
                default: (registry) => {
                    calls.push('shop');
                    registry.registerSlotComponent('shop.orders', ReadingTime);
                },
            },
            '/plugins/analytics/resources/js/index.tsx': {
                default: () => calls.push('analytics'),
            },
            '/plugins/newsletter/resources/js/index.tsx': {},
        };

        expect(loadPluginModules(modules)).toEqual(['analytics', 'shop']);
        expect(calls).toEqual(['analytics', 'shop']);
        expect(slotComponent('shop.orders')).toBe(ReadingTime);
    });

    it('passes the shared registry functions', () => {
        const register = vi.fn();

        loadPluginModules({
            '/plugins/events/resources/js/index.tsx': { default: register },
        });

        expect(register).toHaveBeenCalledWith(slotRegistry);
    });

    it('loads the real plugin modules found by Vite', () => {
        expect(loadPluginModules()).toContain('ai-assistant');
        expect(slotComponent('ai-assistant.panel')).not.toBeNull();
    });
});

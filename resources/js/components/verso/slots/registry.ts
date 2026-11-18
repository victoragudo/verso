import type {
    RowActionBuilder,
    SlotComponent,
    SlotComponentOptions,
} from './types';

type ComponentEntry = {
    component: SlotComponent<never>;
    options: SlotComponentOptions;
};

const components = new Map<string, ComponentEntry>();
const rowActions = new Map<string, RowActionBuilder>();

// Registers a React component under the key a PHP SlotItem names. A new call with the same key replaces it.
export function registerSlotComponent<C>(
    key: string,
    component: SlotComponent<C>,
    options: SlotComponentOptions = {},
): void {
    components.set(key, {
        component: component as SlotComponent<never>,
        options,
    });
}

// Registers a builder for an entries table row action.
export function registerRowAction(
    key: string,
    builder: RowActionBuilder,
): void {
    rowActions.set(key, builder);
}

export function slotComponent<C>(key: string): SlotComponent<C> | null {
    return (components.get(key)?.component as SlotComponent<C>) ?? null;
}

export function slotComponentOptions(key: string): SlotComponentOptions {
    return components.get(key)?.options ?? {};
}

export function rowActionBuilder(key: string): RowActionBuilder | null {
    return rowActions.get(key) ?? null;
}

// Forgets every registration. Used by tests.
export function clearSlotRegistry(): void {
    components.clear();
    rowActions.clear();
}

// The functions a plugin module gets in its default export.
export const slotRegistry = {
    registerSlotComponent,
    registerRowAction,
};

export type SlotRegistry = typeof slotRegistry;

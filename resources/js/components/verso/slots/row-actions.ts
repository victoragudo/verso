import type { Method } from '@inertiajs/core';
import type { RowAction } from '@/components/verso/ui';
import { rowActionBuilder } from './registry';
import type { EntryRowContext, SlotItem } from './types';

const METHODS: Method[] = ['get', 'post', 'put', 'patch', 'delete'];

// A row action from PHP props only: { href: '/admin/x/{id}', method?, external?, destructive? }.
function declarativeAction(
    item: SlotItem,
    context: EntryRowContext,
): RowAction | null {
    const { href, method, external, destructive } = item.props;

    if (typeof href !== 'string' || !item.label) {
        return null;
    }

    return {
        label: item.label,
        href: href.replaceAll('{id}', String(context.row.id)),
        method: METHODS.find((value) => value === method),
        external: external === true,
        destructive: destructive === true,
    };
}

// The row actions plugins add to one entries table row, in slot order.
export function slotRowActions(
    items: SlotItem[],
    context: EntryRowContext,
): RowAction[] {
    return items.flatMap((item) => {
        const builder = rowActionBuilder(item.component);
        const result = builder
            ? builder(context, item.props, item)
            : declarativeAction(item, context);

        if (result === null) {
            return [];
        }

        return Array.isArray(result) ? result : [result];
    });
}

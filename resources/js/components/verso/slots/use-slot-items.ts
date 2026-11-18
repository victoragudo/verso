import { usePage } from '@inertiajs/react';
import type { SharedSlots, SlotItem, SlotName } from './types';

// The items the server shared for one slot, in order.
export function useSlotItems(name: SlotName): SlotItem[] {
    const slots = usePage().props.versoSlots as SharedSlots | undefined;
    const items = slots?.[name];

    return Array.isArray(items) ? items : [];
}

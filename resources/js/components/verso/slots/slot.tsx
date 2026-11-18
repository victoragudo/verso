import { slotComponent } from './registry';
import type { SlotContexts, SlotItem, SlotName } from './types';
import { useSlotItems } from './use-slot-items';

// Renders one slot item with its registered component, or nothing when no plugin registered the key.
export function SlotItemView<N extends SlotName>({
    item,
    context,
}: {
    item: SlotItem;
    context: SlotContexts[N];
}) {
    const Component = slotComponent<SlotContexts[N]>(item.component);

    if (!Component) {
        return null;
    }

    return <Component item={item} props={item.props} context={context} />;
}

// Renders every item of a slot, in order.
export function Slot<N extends SlotName>({
    name,
    context,
}: {
    name: N;
    context: SlotContexts[N];
}) {
    return (
        <>
            {useSlotItems(name).map((item) => (
                <SlotItemView<N> key={item.key} item={item} context={context} />
            ))}
        </>
    );
}

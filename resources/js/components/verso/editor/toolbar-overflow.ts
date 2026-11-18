// A toolbar group as the overflow math sees it. Groups with a higher hideOrder move to the More menu first.
export type MeasuredGroup = { key: string; width: number; hideOrder: number };

// The keys of the groups that stay in the row. Groups keep their place; the More button takes room at the end.
export function visibleGroupKeys(input: {
    available: number;
    groups: MeasuredGroup[];
    moreWidth: number;
    gap: number;
}): string[] {
    const { available, groups, moreWidth, gap } = input;
    const total = (list: MeasuredGroup[]) =>
        list.reduce((sum, group) => sum + group.width, 0) +
        gap * Math.max(0, list.length - 1);

    if (total(groups) <= available) {
        return groups.map((group) => group.key);
    }

    // Later groups go first when two have the same hideOrder.
    const hideFirst = groups
        .map((group, index) => ({ group, index }))
        .sort(
            (a, b) =>
                b.group.hideOrder - a.group.hideOrder || b.index - a.index,
        )
        .map((item) => item.group.key);
    let shown = [...groups];

    for (const key of hideFirst) {
        if (shown.length === 0 || total(shown) + gap + moreWidth <= available) {
            break;
        }

        shown = shown.filter((group) => group.key !== key);
    }

    return shown.map((group) => group.key);
}

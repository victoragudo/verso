import { matchesSearch, normalizeSearchText } from '@/lib/option-search';
import type { AccessRole, Capability } from '@/types';

export type CapabilitySection = {
    key: string;
    title: string | null;
    capabilities: Capability[];
};

export type CapabilityGroup = {
    group: string;
    sections: CapabilitySection[];
    capabilities: Capability[];
};

// Puts capabilities in groups and sections, keeping the order of the server.
export function groupCapabilities(
    capabilities: Capability[],
): CapabilityGroup[] {
    const groups = new Map<string, CapabilityGroup>();

    for (const capability of capabilities) {
        let group = groups.get(capability.group);

        if (!group) {
            group = { group: capability.group, sections: [], capabilities: [] };
            groups.set(capability.group, group);
        }

        const sectionKey = `${capability.group}/${capability.section ?? ''}`;
        let section = group.sections.find((item) => item.key === sectionKey);

        if (!section) {
            section = {
                key: sectionKey,
                title: capability.section,
                capabilities: [],
            };
            group.sections.push(section);
        }

        section.capabilities.push(capability);
        group.capabilities.push(capability);
    }

    return [...groups.values()];
}

// All the text a search can find in a capability, without accents and in lower case.
function capabilitySearchText(capability: Capability): string {
    return normalizeSearchText(
        [
            capability.label,
            capability.key,
            capability.description,
            capability.group,
            capability.section ?? '',
        ].join(' '),
    );
}

// Returns a filter that keeps the capabilities that match a query.
export function createCapabilityFilter(
    capabilities: Capability[],
): (query: string) => Capability[] {
    const texts = capabilities.map(capabilitySearchText);

    return (query) => {
        if (normalizeSearchText(query) === '') {
            return capabilities;
        }

        return capabilities.filter((_, index) =>
            matchesSearch(texts[index], query),
        );
    };
}

// The keys given by a list of keys, following "implies" all the way down.
export function expandCapabilities(
    keys: Iterable<string>,
    byKey: Map<string, Capability>,
): Set<string> {
    const found = new Set<string>();
    const queue = [...keys];

    while (queue.length > 0) {
        const current = queue.shift() as string;

        if (found.has(current)) {
            continue;
        }

        found.add(current);
        queue.push(...(byKey.get(current)?.implies ?? []));
    }

    return found;
}

// For each key, the capabilities that list it in "implies".
export function impliedByMap(
    capabilities: Capability[],
): Map<string, Capability[]> {
    const map = new Map<string, Capability[]>();

    for (const capability of capabilities) {
        for (const key of capability.implies) {
            map.set(key, [...(map.get(key) ?? []), capability]);
        }
    }

    return map;
}

export function capabilityMap(
    capabilities: Capability[],
): Map<string, Capability> {
    return new Map(
        capabilities.map((capability) => [capability.key, capability]),
    );
}

export type EffectiveInput = {
    capabilities: Capability[];
    roles: AccessRole[];
    slugs: string[];
    grants: string[];
    denies: string[];
};

// What a user can do, like the server: role capabilities and grants with what they imply, minus denies. Admins have all.
export function effectiveCapabilities({
    capabilities,
    roles,
    slugs,
    grants,
    denies,
}: EffectiveInput): Set<string> {
    const picked = roles.filter((role) => slugs.includes(role.slug));

    if (picked.some((role) => role.locked)) {
        return new Set(capabilities.map((capability) => capability.key));
    }

    const byKey = capabilityMap(capabilities);
    const granted = expandCapabilities(
        [...picked.flatMap((role) => role.capabilities), ...grants],
        byKey,
    );
    const denied = expandCapabilities(denies, byKey);

    return new Set([...granted].filter((key) => !denied.has(key)));
}

export function sameKeys(a: Set<string>, b: Set<string>): boolean {
    return a.size === b.size && [...a].every((key) => b.has(key));
}

// Keys of a set in the order of the capability list, for stable payloads.
export function orderedKeys(
    keys: Set<string>,
    capabilities: Capability[],
): string[] {
    return capabilities
        .map((capability) => capability.key)
        .filter((key) => keys.has(key));
}

export type CheckState = boolean | 'indeterminate';

// Checked when all keys are in the set, unchecked when none, else mixed.
export function checkState(keys: string[], selected: Set<string>): CheckState {
    const count = keys.filter((key) => selected.has(key)).length;

    if (count === 0) {
        return false;
    }

    return count === keys.length ? true : 'indeterminate';
}

// Adds or removes every key of a list, as a "select all" box does.
export function toggleAll(
    selected: Set<string>,
    keys: string[],
    on: boolean,
): Set<string> {
    const next = new Set(selected);

    for (const key of keys) {
        if (on) {
            next.add(key);
        } else {
            next.delete(key);
        }
    }

    return next;
}

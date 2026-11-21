import { xsrfToken } from '@/lib/json-request';
import type { Design } from './styles';
import type { StylePresetItem } from './types';

// The style presets of the site, shared by the Styles panel and the canvas. Saving one updates both at once.
let presets: StylePresetItem[] = [];
let editable = false;
let designs: Record<string, Design> = {};
const listeners = new Set<() => void>();

function publish(next: StylePresetItem[]): void {
    // The editor applies the manifest on every render; only a real change tells the listeners.
    if (JSON.stringify(next) === JSON.stringify(presets)) {
        return;
    }

    presets = next;
    designs = Object.fromEntries(next.map((item) => [item.key, item.design]));
    listeners.forEach((listener) => listener());
}

export const stylePresets = {
    set: publish,
    // True when this person may save, change and delete presets.
    setEditable(value: boolean) {
        editable = value;
    },
    editable: (): boolean => editable,
    all: (): StylePresetItem[] => presets,
    designs: (): Record<string, Design> => designs,
    find: (key: string | null): StylePresetItem | undefined =>
        key === null ? undefined : presets.find((item) => item.key === key),
    subscribe: (listener: () => void) => {
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    },
};

type PresetAnswer = { preset?: StylePresetItem; presets: StylePresetItem[] };

async function request(
    url: string,
    method: 'POST' | 'PUT' | 'DELETE',
    body?: unknown,
): Promise<PresetAnswer> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        const answer = (await response.json().catch(() => ({}))) as {
            message?: string;
        };

        throw new Error(answer.message ?? String(response.status));
    }

    const answer = (await response.json()) as PresetAnswer;
    publish(answer.presets);

    return answer;
}

// Saves a new preset (no key) or changes one. Gives the saved preset.
export async function savePreset(
    urls: { store: string; update: (key: string) => string },
    key: string | null,
    label: string,
    design: Design,
): Promise<StylePresetItem | undefined> {
    const answer = await request(
        key === null ? urls.store : urls.update(key),
        key === null ? 'POST' : 'PUT',
        { label, design },
    );

    return answer.preset;
}

export async function deletePreset(url: string): Promise<void> {
    await request(url, 'DELETE');
}

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

// Folders with admin TSX. The plugins folders are added when they have code.
const ROOTS = [
    'resources/js',
    ...readdirSync('plugins')
        .map((plugin) => join('plugins', plugin, 'resources/js'))
        .filter((folder) => existsSync(folder)),
];

// The base select components may draw a native select. Tests are not admin UI.
const SKIPPED_FOLDERS = ['resources/js/components/ui', 'resources/js/tests'];

// Files that may keep a native select, with the reason. Keep it empty if you can.
const ALLOWED: Record<string, string> = {};

const NATIVE_SELECT = /<select[\s>]/;

function tsxFiles(folder: string): string[] {
    if (SKIPPED_FOLDERS.includes(folder)) {
        return [];
    }

    return readdirSync(folder, { withFileTypes: true }).flatMap((entry) => {
        const path = join(folder, entry.name);

        if (entry.isDirectory()) {
            return tsxFiles(path);
        }

        return entry.name.endsWith('.tsx') ? [path] : [];
    });
}

describe('admin selects', () => {
    it('use the admin select components, not a native <select>', () => {
        const offenders = ROOTS.flatMap(tsxFiles).filter(
            (file) =>
                !(file in ALLOWED) &&
                NATIVE_SELECT.test(readFileSync(file, 'utf8')),
        );

        expect(
            offenders,
            'Use OptionSelect, Select or SearchableSelect instead of <select>.',
        ).toEqual([]);
    });
});

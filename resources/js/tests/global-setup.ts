import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));

// Makes the Wayfinder route helpers once, like the Vite build does, when they are missing.
export default function setup(): void {
    if (existsSync(`${root}resources/js/routes/index.ts`)) {
        return;
    }

    execFileSync('php', ['artisan', 'wayfinder:generate', '--with-form'], {
        cwd: root,
        stdio: 'inherit',
    });
}

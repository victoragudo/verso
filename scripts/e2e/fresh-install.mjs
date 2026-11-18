// Installs Verso the way the README says, then runs the web wizard in a real browser.
// 1. composer create-project (from this checkout, or from E2E_FRESH_PACKAGE and E2E_FRESH_REPOSITORY)
// 2. php artisan serve, which restarts when the wizard writes .env
// 3. Playwright: the whole wizard with SQLite, then an admin login
// Usage: node scripts/e2e/fresh-install.mjs. Set E2E_KEEP=1 to keep the temp folder.
import { execFileSync, spawn, spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repo = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const host = '127.0.0.1';
const keep = process.env.E2E_KEEP === '1';
const localVersion = 'dev-fresh-install-e2e';
const bootTimeoutMs = 60_000;

const root = mkdtempSync(join(process.env.E2E_FRESH_ROOT ?? tmpdir(), 'verso-fresh-'));
const site = join(root, 'site');
let server = null;

function log(text) {
    console.log(`[fresh-install] ${text}`);
}

// Copy the files git knows about (also changed and new ones), so the test uses the code as it is now.
function copyCheckout(target) {
    const listed = execFileSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], { cwd: repo })
        .toString()
        .split('\0')
        .filter((file) => file !== '');

    for (const file of listed) {
        const from = join(repo, file);

        if (!existsSync(from)) {
            continue;
        }

        mkdirSync(dirname(join(target, file)), { recursive: true });
        copyFileSync(from, join(target, file));
    }
}

function packageSource() {
    if (process.env.E2E_FRESH_PACKAGE) {
        return { name: process.env.E2E_FRESH_PACKAGE, repository: process.env.E2E_FRESH_REPOSITORY ?? '' };
    }

    const source = join(root, 'source');
    copyCheckout(source);

    const repository = {
        type: 'path',
        url: source,
        options: { symlink: false, versions: { 'victoragudo/verso': localVersion } },
    };

    return { name: `victoragudo/verso:${localVersion}`, repository: JSON.stringify(repository) };
}

function run(command, args, options = {}) {
    const result = spawnSync(command, args, { stdio: 'inherit', ...options });

    if (result.status !== 0) {
        throw new Error(`${command} ${args.join(' ')} failed with exit code ${result.status}`);
    }
}

// The documented install must not leave any table behind: the wizard needs an empty database.
function tablesIn(databaseFile) {
    const code = `$pdo = new PDO('sqlite:'.$argv[1]); echo implode(',', $pdo->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN));`;

    return execFileSync('php', ['-r', code, databaseFile]).toString().trim();
}

function freePort() {
    const script = "const s=require('net').createServer();s.listen(0,'127.0.0.1',()=>{process.stdout.write(String(s.address().port));s.close()})";

    return execFileSync(process.execPath, ['-e', script]).toString();
}

async function waitUntilUp(url) {
    const deadline = Date.now() + bootTimeoutMs;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);

            if (response.ok) {
                return;
            }
        } catch {
            // Not listening yet.
        }

        await new Promise((done) => setTimeout(done, 500));
    }

    throw new Error(`${url} did not answer in time`);
}

// "artisan serve" starts a child PHP server, so stop the whole process group.
function stopServer() {
    if (server === null || server.exitCode !== null) {
        return;
    }

    try {
        process.kill(-server.pid, 'SIGTERM');
    } catch {
        server.kill('SIGTERM');
    }
}

function cleanUp() {
    stopServer();

    if (!keep) {
        rmSync(root, { recursive: true, force: true });
    } else {
        log(`kept ${root}`);
    }
}

async function main() {
    const source = packageSource();
    const createArgs = ['create-project', source.name, site, '--no-interaction', '--no-progress'];

    if (source.repository !== '') {
        createArgs.push(`--repository=${source.repository}`);
    }

    log(`composer ${createArgs.join(' ')}`);
    run('composer', createArgs, { cwd: root, env: { ...process.env, COMPOSER_NO_AUDIT: '1' } });

    const databaseFile = join(site, 'database/database.sqlite');
    const tables = existsSync(databaseFile) ? tablesIn(databaseFile) : '';

    if (tables !== '') {
        throw new Error(`create-project left tables in database/database.sqlite: ${tables}`);
    }

    log('database/database.sqlite has no tables');

    const port = freePort();
    const baseURL = `http://${host}:${port}`;
    server = spawn('php', ['artisan', 'serve', `--host=${host}`, `--port=${port}`], {
        cwd: site,
        stdio: ['ignore', 'inherit', 'inherit'],
        detached: true,
    });
    await waitUntilUp(`${baseURL}/up`);
    log(`php artisan serve is up at ${baseURL}`);

    run('npx', ['playwright', 'test', '--config', 'playwright.install.config.ts'], {
        cwd: repo,
        env: { ...process.env, E2E_FRESH_URL: baseURL, E2E_FRESH_SITE: site },
    });
}

for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
    process.on(signal, () => {
        cleanUp();
        process.exit(1);
    });
}

try {
    await main();
    cleanUp();
} catch (error) {
    console.error(`[fresh-install] ${error.message}`);
    cleanUp();
    process.exit(1);
}

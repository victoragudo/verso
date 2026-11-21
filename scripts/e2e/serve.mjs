// Boots Verso for end to end tests on a throwaway SQLite database and storage folder.
// Usage: node scripts/e2e/serve.mjs <name> <port>. Nothing touches .env or database/database.sqlite.
import { spawn } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { cpSync, existsSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const [name = 'site', port = process.env.E2E_PORT ?? '8700'] =
    process.argv.slice(2);
const host = process.env.E2E_HOST ?? '127.0.0.1';
const workers = process.env.E2E_PHP_WORKERS ?? '4';
const keep = process.env.E2E_KEEP === '1';
// Laravel logs are copied here when the server stops, so CI can keep them after a failure.
const logDir = resolve(process.env.E2E_LOG_DIR ?? 'test-results/laravel-logs');

const root = mkdtempSync(join(tmpdir(), `verso-e2e-${name}-`));
const storage = join(root, 'storage');
const storageFolders = [
    'app/public',
    'app/private',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
];

for (const folder of storageFolders) {
    mkdirSync(join(storage, folder), { recursive: true });
}

const database = join(root, 'database.sqlite');

const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    APP_URL: `http://${host}:${port}`,
    APP_LOCALE: 'en',
    LARAVEL_STORAGE_PATH: storage,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    LOG_CHANNEL: 'single',
    VERSO_INSTALL_REDIRECT: 'true',
    VERSO_INSTALL_CREATE_ENV: 'false',
    VERSO_INSTALL_ENV_FILE: join(root, '.env'),
    VERSO_INSTALL_LOCK_FILE: join(root, 'installed.lock'),
    VERSO_INSTALL_STATE_FILE: join(root, 'install-progress.json'),
    VERSO_INSTALL_SQLITE_PATH: database,
    PHP_CLI_SERVER_WORKERS: workers,
};

console.log(`[e2e:${name}] ${env.APP_URL} using ${root}`);

const server = spawn(
    'php',
    [
        '-d',
        'variables_order=EGPCS',
        '-S',
        `${host}:${port}`,
        '-t',
        'public',
        'scripts/e2e/router.php',
    ],
    { env, stdio: ['ignore', 'inherit', 'pipe'] },
);

// The built-in server logs every connection; only keep the lines that matter.
const noise = / (Accepted|Closing)$|\[\d{3}\]: (GET|POST|PUT|DELETE|HEAD) /;

server.stderr.on('data', (chunk) => {
    for (const line of String(chunk).split('\n')) {
        if (line.trim() !== '' && !noise.test(line)) {
            process.stderr.write(`${line}\n`);
        }
    }
});

let stopping = false;

function saveLogs() {
    const logs = join(storage, 'logs');

    try {
        if (existsSync(logs)) {
            cpSync(logs, join(logDir, name), { recursive: true });
        }
    } catch (error) {
        console.error(
            `[e2e:${name}] could not save the logs: ${error.message}`,
        );
    }
}

// Stop the server and remove the throwaway files (kept with E2E_KEEP=1 for debugging).
function stop(code = 0) {
    if (stopping) {
        return;
    }

    stopping = true;
    server.kill('SIGTERM');
    saveLogs();

    if (!keep) {
        rmSync(root, { recursive: true, force: true });
    }

    process.exit(code);
}

server.on('exit', (code) => stop(code ?? 0));

for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP']) {
    process.on(signal, () => stop(0));
}

<?php

// Runs once after "composer create-project victoragudo/verso".
// It builds the admin assets when npm is installed, then explains the next step.

$root = dirname(__DIR__);
$hasBuild = is_file($root.'/public/build/manifest.json');

function verso_line(string $text = ''): void
{
    fwrite(STDOUT, $text.PHP_EOL);
}

function verso_has_npm(): bool
{
    $finder = PHP_OS_FAMILY === 'Windows' ? 'where npm 2>NUL' : 'command -v npm 2>/dev/null';

    return trim((string) shell_exec($finder)) !== '';
}

if (! $hasBuild) {
    if (verso_has_npm()) {
        verso_line('Building the admin assets (npm ci and npm run build). This takes about a minute...');
        passthru('npm ci --no-audit --no-fund && npm run build', $status);

        if ($status !== 0) {
            verso_line();
            verso_line('The build did not finish. Run "npm ci && npm run build" in this folder, then start the site.');
            exit(0);
        }
    } else {
        verso_line();
        verso_line('Node.js and npm are needed once to build the admin. Install Node.js 22 or newer, then run:');
        verso_line('  npm ci && npm run build');
    }
}

verso_line();
verso_line('Verso is ready. Start it and open the install wizard:');
verso_line('  php artisan serve');
verso_line('  open http://localhost:8000');

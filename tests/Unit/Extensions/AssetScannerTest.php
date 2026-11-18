<?php

use App\Verso\Extensions\Verification\CapabilityMap;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\Scanners\AssetScanner;
use App\Verso\Extensions\Verification\Scanners\PhpScanner;
use App\Verso\Extensions\Verification\ScanResult;

// A scanner for JavaScript and Blade files.
function assetScanner(): AssetScanner
{
    return new AssetScanner(new PhpScanner(new CapabilityMap, 400));
}

// Rule id and line of each finding.
function findingLines(ScanResult $result): array
{
    return array_map(fn (Finding $finding): array => [$finding->rule, $finding->line], $result->findings);
}

it('finds nothing in plain JavaScript', function () {
    $result = new ScanResult;
    assetScanner()->scanScript('assets/tour.js', "document.querySelectorAll('.tour-date').forEach((row) => row.classList.add('is-ready'));\nfetch('/api/v1/entries');\n", $result);

    expect($result->findings)->toBe([])
        ->and($result->hosts)->toBe([]);
});

it('reports code built from strings in JavaScript', function () {
    $result = new ScanResult;
    assetScanner()->scanScript('assets/tour.js', "const run = eval(atob(payload));\nconst add = new Function('a', 'return a + 1');\nsetTimeout(\"alert('hi')\", 10);\nsetTimeout(() => refresh(), 10);\n", $result);

    expect(findingLines($result))->toBe([['js.eval', 1], ['js.eval', 2], ['js.eval', 3]]);
});

it('reports script injection in JavaScript', function () {
    $result = new ScanResult;
    assetScanner()->scanScript('assets/tour.js', "const tag = document.createElement('script');\ndocument.write('<p>Concerts</p>');\n", $result);

    expect(findingLines($result))->toBe([['js.script-injection', 1], ['js.script-injection', 2]]);
});

it('reports external scripts and requests with their hosts', function () {
    $code = "import('https://cdn.jsdelivr.net/npm/confetti.js');\nfetch('https://stats.tour-metrics.io/hit', { method: 'POST' });\nnew WebSocket('wss://live.tour-dates.io/socket');\nxhr.open('GET', 'https://api.songkick.com/v3/events');\n";
    $result = new ScanResult;
    assetScanner()->scanScript('assets/tour.js', $code, $result);

    expect(findingLines($result))->toBe([['js.external-script', 1], ['js.external-request', 2], ['js.external-request', 3], ['js.external-request', 4]])
        ->and(array_keys($result->hosts))->toBe(['cdn.jsdelivr.net', 'stats.tour-metrics.io', 'live.tour-dates.io', 'api.songkick.com'])
        ->and($result->networkCalls)->toHaveCount(3);
});

it('finds nothing in a plain Blade view', function () {
    $result = new ScanResult;
    assetScanner()->scanBlade('resources/views/list.blade.php', "<ul>\n@foreach (\$dates as \$date)\n    <li>{{ \$date->city }}</li>\n@endforeach\n</ul>\n<script src=\"/build/tour.js\"></script>\n", $result);

    expect($result->findings)->toBe([]);
});

it('reports raw output, inline scripts and data printed inside scripts', function () {
    $view = "<div>{!! \$intro !!}</div>\n<script>\n    window.tour = {{ \$json }};\n    const city = @json(\$city);\n</script>\n<p>{{ \$city }}</p>\n";
    $result = new ScanResult;
    assetScanner()->scanBlade('resources/views/list.blade.php', $view, $result);

    expect(findingLines($result))->toBe([
        ['blade.raw-output', 1],
        ['blade.inline-script', 2],
        ['blade.script-injection', 3],
        ['blade.script-injection', 4],
    ]);
});

it('reports external scripts in Blade views', function () {
    $result = new ScanResult;
    assetScanner()->scanBlade('resources/views/layout.blade.php', "<script async src=\"https://cdn.adnetwork-metrics.com/tag.js\"></script>\n<script src='//widgets.tour-dates.io/embed.js'></script>\n", $result);

    expect(findingLines($result))->toBe([['blade.external-script', 1], ['blade.external-script', 2]])
        ->and(array_keys($result->hosts))->toBe(['cdn.adnetwork-metrics.com', 'widgets.tour-dates.io']);
});

it('scans PHP tags and @php blocks of Blade views with the right lines', function () {
    $view = "<h1>{{ \$title }}</h1>\n<?php system('id'); ?>\n@php\n    \$rows = 3;\n    exec('whoami');\n@endphp\n@php(\$count = 2)\n";
    $result = new ScanResult;
    assetScanner()->scanBlade('resources/views/list.blade.php', $view, $result);

    expect(findingLines($result))->toBe([['php.shell', 2], ['php.shell', 5]]);
});

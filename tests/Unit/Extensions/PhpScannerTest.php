<?php

use App\Verso\Extensions\Verification\CapabilityMap;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\Scanners\PhpScanner;
use App\Verso\Extensions\Verification\ScanResult;

// Scan a piece of PHP and return the result.
function scanPhp(string $code, string $file = 'src/TourDates.php', int $encodedLength = 400): ScanResult
{
    $result = new ScanResult;
    (new PhpScanner(new CapabilityMap, $encodedLength))->scan($file, $code, $result);

    return $result;
}

// The rule ids found, in order.
function ruleIds(ScanResult $result): array
{
    return array_map(fn (Finding $finding): string => $finding->rule, $result->findings);
}

// Wrap a body in a namespaced class method.
function inMethod(string $body, string $imports = ''): string
{
    return "<?php\n\nnamespace Verso\\Plugins\\TourDates;\n\n{$imports}\nclass Concerts\n{\n    public function run(\$value, \$path)\n    {\n{$body}\n    }\n}\n";
}

it('finds nothing in plain plugin code', function () {
    $code = inMethod(<<<'PHP'
        $dates = collect(['2026-12-04', '2026-12-11'])->map(fn (string $day) => strtoupper($day));
        $total = array_sum([3, 4]);
        return view('tour-dates::list', ['dates' => $dates, 'total' => $total]);
PHP);

    expect(scanPhp($code)->findings)->toBe([]);
});

it('reports eval with its line', function () {
    $result = scanPhp("<?php\n\n\$code = 'return 1;';\neval(\$code);\n");

    expect($result->findings)->toHaveCount(1)
        ->and($result->findings[0]->rule)->toBe('php.eval')
        ->and($result->findings[0]->line)->toBe(4)
        ->and($result->findings[0]->file)->toBe('src/TourDates.php');
});

it('reports eval of decoded code as obfuscation', function (string $call) {
    expect(ruleIds(scanPhp("<?php\neval({$call});\n")))->toContain('php.eval', 'php.obfuscated-code');
})->with([
    'base64' => "base64_decode('ZWNobyAxOw==')",
    'gzinflate' => 'gzinflate(base64_decode($blob))',
    'rot13' => "str_rot13('rpub 1;')",
    'hex' => "hex2bin('6563686f20313b')",
]);

it('reports shell functions', function (string $function) {
    expect(ruleIds(scanPhp("<?php\n{$function}('ls -la');\n")))->toBe(['php.shell']);
})->with(['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec']);

it('reports fully qualified shell calls', function () {
    expect(ruleIds(scanPhp("<?php\nnamespace Lighthouse;\n\\exec('ls');\n")))->toBe(['php.shell']);
});

it('ignores methods and functions that only share a shell name', function () {
    $code = inMethod(<<<'PHP'
        $this->system('ok');
        $query->exec();
        Report::system();
        \Verso\Plugins\TourDates\exec('ok');
PHP);

    expect(scanPhp($code)->findings)->toBe([]);
});

it('reports backticks once per command', function () {
    $result = scanPhp("<?php\n\$files = `ls storage`;\n\$user = `whoami`;\n");

    expect(ruleIds($result))->toBe(['php.shell', 'php.shell'])
        ->and($result->findings[1]->line)->toBe(3);
});

it('reports functions called by a string name', function () {
    expect(ruleIds(scanPhp("<?php\n'shell_exec'('whoami');\ncall_user_func('passthru', 'id');\ncall_user_func_array('system', ['id']);\n")))
        ->toBe(['php.shell', 'php.shell', 'php.shell']);
});

it('reports assert with a string, but not with an expression', function () {
    expect(ruleIds(scanPhp("<?php\nassert('\$total > 0');\nassert(\$total > 0);\nassert(\"\$x\");\n")))
        ->toBe(['php.assert-string', 'php.assert-string']);
});

it('reports preg_replace with the e modifier only', function () {
    $code = "<?php\npreg_replace('/(\\w+)/e', 'strtoupper(\"\$1\")', \$text);\npreg_replace('#a#ie', '', \$text);\npreg_replace('/[^a-z]/i', '', \$text);\npreg_replace('{x}e', '', \$text);\n";

    expect(ruleIds(scanPhp($code)))->toBe(['php.preg-replace-eval', 'php.preg-replace-eval', 'php.preg-replace-eval']);
});

it('reports dl and create_function', function () {
    expect(ruleIds(scanPhp("<?php\ndl('sockets.so');\n\$add = create_function('\$a', 'return \$a + 1;');\n")))
        ->toBe(['php.dl', 'php.create-function']);
});

it('reports includes built at runtime but allows fixed paths', function () {
    $code = inMethod(<<<'PHP'
        require __DIR__.'/../routes/web.php';
        require_once dirname(__DIR__).'/helpers.php';
        include base_path('vendor/autoload.php');
        include $path;
        require 'templates/'.$value.'.php';
        include_once($this->file());
PHP);

    $result = scanPhp($code);

    expect(ruleIds($result))->toBe(['php.dynamic-include', 'php.dynamic-include', 'php.dynamic-include'])
        ->and(array_map(fn (Finding $finding) => $finding->line, $result->findings))->toBe([13, 14, 15]);
});

it('reports includes of decoded paths as obfuscation', function () {
    expect(ruleIds(scanPhp("<?php\ninclude base64_decode('L3RtcC94LnBocA==');\n")))->toBe(['php.obfuscated-code']);
});

it('reports nested decoding', function () {
    expect(ruleIds(scanPhp("<?php\n\$code = gzinflate(base64_decode(\$blob));\n")))->toBe(['php.obfuscated-code']);
});

it('reports very long encoded strings', function () {
    $blob = base64_encode(str_repeat('echo "hidden";', 40));

    $result = scanPhp("<?php\n\$blob = '{$blob}';\n\$text = '".str_repeat('Lisbon tram 28 ', 40)."';\n");

    expect(ruleIds($result))->toBe(['php.encoded-string'])
        ->and($result->findings[0]->message)->toContain((string) strlen($blob));
});

it('respects the encoded string length', function () {
    $blob = base64_encode(str_repeat('a', 90));

    expect(ruleIds(scanPhp("<?php\n\$blob = '{$blob}';\n", encodedLength: 100)))->toBe(['php.encoded-string'])
        ->and(ruleIds(scanPhp("<?php\n\$blob = '{$blob}';\n", encodedLength: 400)))->toBe([]);
});

it('reports calls through a variable unless it holds a closure', function () {
    $code = inMethod(<<<'PHP'
        $format = fn (int $cents) => number_format($cents / 100, 2);
        $format(1999);
        $render = function () { return 'ok'; };
        $render();
        $callable = strtoupper(...);
        $callable('porto');
        $value('rm -rf');
PHP);

    $result = scanPhp($code);

    expect(ruleIds($result))->toBe(['php.dynamic-function'])
        ->and($result->findings[0]->message)->toContain('$value');
});

it('trusts variables typed as Closure or callable', function () {
    $code = "<?php\nfunction handle(\$request, \\Closure \$next, ?Closure \$fallback, callable \$done) {\n    \$next(\$request);\n    \$fallback();\n    \$done();\n}\n";

    expect(scanPhp($code)->findings)->toBe([]);
});

it('reports variable variables', function () {
    expect(ruleIds(scanPhp("<?php\n\$\$name = 'harbour';\n\${'city'} = 'Porto';\n")))->toBe(['php.dynamic-variable', 'php.dynamic-variable']);
});

it('reports superglobals', function (string $global) {
    expect(ruleIds(scanPhp("<?php\n\$value = {$global}['token'] ?? null;\n")))->toBe(['php.superglobal']);
})->with(['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES', '$_SERVER', '$_ENV', '$_SESSION', '$GLOBALS']);

it('reports PHP setting and environment changes', function (string $function) {
    expect(ruleIds(scanPhp("<?php\n{$function}('memory_limit', '1G');\n")))->toBe(['php.environment']);
})->with(['ini_set', 'ini_alter', 'putenv', 'set_include_path']);

it('reports raw SQL', function () {
    $code = inMethod(<<<'PHP'
        DB::statement('DROP TABLE sessions');
        \DB::unprepared('DELETE FROM users');
        DB::connection('mysql')->statement('OPTIMIZE TABLE entries');
        mysqli_query($link, 'SELECT 1');
        DB::table('entries')->whereRaw('LOWER(title) = ?', [$value])->get();
        DB::raw('COUNT(*)');
PHP, 'use Illuminate\Support\Facades\DB;');

    expect(ruleIds(scanPhp($code)))->toBe(['php.raw-sql', 'php.raw-sql', 'php.raw-sql', 'php.raw-sql', 'php.raw-sql-fragment', 'php.raw-sql-fragment']);
});

it('ignores a DB class that is not the Laravel facade', function () {
    $code = inMethod("        DB::statement('report');", 'use Verso\Plugins\TourDates\Support\DB;');

    expect(scanPhp($code)->findings)->toBe([]);
});

it('records outbound HTTP calls and the hosts in the same file', function () {
    $code = inMethod(<<<'PHP'
        $response = Http::withToken($value)->get('https://api.songkick.com/v3/events');
        $page = file_get_contents('https://tickets.example-box-office.com/feed.json');
        $handle = curl_init();
        fsockopen('irc.libera.chat', 6667);
        stream_socket_client('tcp://relay.tour-dates.io:9000');
PHP, 'use Illuminate\Support\Facades\Http;');

    $result = scanPhp($code);

    expect(array_count_values(ruleIds($result)))->toBe(['php.network' => 5])
        ->and(array_keys($result->hosts))->toEqualCanonicalizing(['api.songkick.com', 'tickets.example-box-office.com', 'relay.tour-dates.io'])
        ->and($result->networkCalls)->toHaveCount(5);
});

it('does not treat URLs in files without network calls as hosts', function () {
    $code = inMethod("        return ['docs' => 'https://docs.tour-dates.io/setup'];");

    expect(scanPhp($code)->hosts)->toBe([]);
});

it('treats URLs in config files as hosts', function () {
    $result = scanPhp("<?php\n\nreturn ['api_url' => env('TOUR_API_URL', 'https://api.songkick.com')];\n", 'config/tour-dates.php');

    expect($result->hosts)->toBe(['api.songkick.com' => ['file' => 'config/tour-dates.php', 'line' => 3]]);
});

it('reports network clients created with new', function () {
    $code = inMethod('        $client = new Client(["base_uri" => "https://api.bandsintown.com"]);', 'use GuzzleHttp\Client;');

    $result = scanPhp($code);

    expect(ruleIds($result))->toBe(['php.network'])
        ->and(array_keys($result->hosts))->toBe(['api.bandsintown.com']);
});

it('reports shell processes from Symfony and the Process facade', function () {
    $code = inMethod(<<<'PHP'
        (new Process(['ls']))->run();
        ProcessFacade::run('ls');
PHP, "use Symfony\\Component\\Process\\Process;\nuse Illuminate\\Support\\Facades\\Process as ProcessFacade;");

    expect(ruleIds(scanPhp($code)))->toBe(['php.shell', 'php.shell']);
});

it('rates file writes by where they go', function () {
    $code = inMethod(<<<'PHP'
        file_put_contents(__DIR__.'/../cache/dates.json', '[]');
        file_put_contents(storage_path('app/tour-dates.json'), '[]');
        file_put_contents(dirname(__DIR__).'/cache.json', '[]');
        file_put_contents(sys_get_temp_dir().'/tour.json', '[]');
        $handle = fopen('php://output', 'w');
        $read = fopen($path, 'rb');
        file_put_contents('/etc/cron.d/tour', '* * * * *');
        unlink('../../.env');
        File::put(base_path('public/hello.php'), '<?php');
        mkdir(public_path('uploads/tour'));
        file_put_contents($path, $value);
        File::copy($value, $path);
PHP, 'use Illuminate\Support\Facades\File;');

    $result = scanPhp($code);

    expect(array_map(fn (Finding $finding) => [$finding->line, $finding->severity->value], $result->findings))->toBe([
        [16, 'high'],
        [17, 'high'],
        [18, 'high'],
        [19, 'high'],
        [20, 'medium'],
        [21, 'medium'],
    ]);
});

it('maps Verso actions and model writes to capabilities', function () {
    $code = inMethod(<<<'PHP'
        app(PublishEntry::class)->handle($value);
        User::create(['name' => 'Ines Duarte']);
        Media::query()->count();
        Storage::disk('public')->put('tour.json', '[]');
        $this->settings->set('tour-dates.city', 'Lisbon');
PHP, "use App\\Verso\\Content\\Actions\\PublishEntry;\nuse App\\Models\\User;\nuse App\\Verso\\Media\\Models\\Media;\nuse Illuminate\\Support\\Facades\\Storage;\nuse App\\Verso\\Core\\Settings\\Settings;");

    $result = scanPhp($code);

    expect(array_keys($result->capabilities))->toEqualCanonicalizing(['publish_entries', 'manage_users', 'upload_files', 'manage_settings'])
        ->and($result->capabilities['publish_entries']['line'])->toBe(5)
        ->and($result->findings)->toBe([]);
});

it('does not ask for capabilities for reads', function () {
    $code = inMethod(<<<'PHP'
        User::query()->where('role', 'editor')->get();
        Storage::disk('public')->url('tour.json');
        $this->settings->get('site.name');
PHP, "use App\\Models\\User;\nuse Illuminate\\Support\\Facades\\Storage;\nuse App\\Verso\\Core\\Settings\\Settings;");

    expect(scanPhp($code)->capabilities)->toBe([]);
});

it('records declared classes, created tables and merged config keys', function () {
    $provider = inMethod("        \$this->mergeConfigFrom(__DIR__.'/../config/tour-dates.php', 'tour-dates');");
    $migration = "<?php\n\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('tour_dates', function (\$table) {});\n    }\n};\n";

    $result = new ScanResult;
    $scanner = new PhpScanner(new CapabilityMap, 400);
    $scanner->scan('src/TourDatesServiceProvider.php', $provider, $result);
    $scanner->scan('database/migrations/2026_12_01_000000_create_tour_dates_table.php', $migration, $result);

    expect($result->classes)->toBe(['Verso\\Plugins\\TourDates\\Concerts' => ['file' => 'src/TourDatesServiceProvider.php', 'line' => 6]])
        ->and($result->configKeys)->toBe(['tour-dates' => 'src/TourDatesServiceProvider.php'])
        ->and($result->tables)->toBe(['tour_dates' => ['file' => 'database/migrations/2026_12_01_000000_create_tour_dates_table.php', 'line' => 9]]);
});

it('reports syntax errors and keeps scanning', function () {
    $result = scanPhp("<?php\n\nfunction broken( {\n    exec('ls');\n");

    expect(ruleIds($result))->toBe(['php.syntax-error', 'php.shell'])
        ->and($result->findings[0]->line)->toBe(3);
});

it('shifts line numbers by the offset', function () {
    $result = new ScanResult;
    (new PhpScanner(new CapabilityMap, 400))->scan('resources/views/list.blade.php', "<?php\nexec('ls');\n", $result, 10);

    expect($result->findings[0]->line)->toBe(12);
});

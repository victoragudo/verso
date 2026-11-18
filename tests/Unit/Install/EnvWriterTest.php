<?php

use App\Verso\Install\Env\EnvironmentFile;
use App\Verso\Install\Env\EnvWriteInput;
use App\Verso\Install\Env\EnvWriter;
use App\Verso\Install\Env\InvalidEnvValue;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->folder = storage_path('framework/testing/env-'.uniqid());
    mkdir($this->folder, 0755, true);
    $this->path = $this->folder.'/.env';
    $this->writer = new EnvWriter(new Filesystem, 0640);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->folder);
});

function readEnv(string $path): array
{
    return Dotenv::parse((string) file_get_contents($path));
}

it('keeps comments, blank lines and unknown keys', function () {
    file_put_contents($this->path, "# Bakery site\nAPP_NAME=Old\n\n# Mail\nMAIL_MAILER=log\nCUSTOM_FLAG=on\n");

    $this->writer->write(new EnvWriteInput($this->path, ['APP_NAME' => 'Harbor Lane Bakery']));

    expect(file_get_contents($this->path))->toBe("# Bakery site\nAPP_NAME=\"Harbor Lane Bakery\"\n\n# Mail\nMAIL_MAILER=log\nCUSTOM_FLAG=on\n");
});

it('fills a commented key in place and adds new keys at the end', function () {
    file_put_contents($this->path, "DB_CONNECTION=sqlite\n# DB_HOST=127.0.0.1\n# DB_PORT=3306\n");

    $output = $this->writer->write(new EnvWriteInput($this->path, ['DB_HOST' => 'db.internal', 'DB_PREFIX' => 'vs_']));

    expect(file_get_contents($this->path))->toBe("DB_CONNECTION=sqlite\nDB_HOST=db.internal\n# DB_PORT=3306\nDB_PREFIX=vs_\n")
        ->and($output->updated)->toBe(['DB_HOST'])
        ->and($output->added)->toBe(['DB_PREFIX']);
});

it('quotes values so dotenv reads them back exactly', function (string $value) {
    $this->writer->write(new EnvWriteInput($this->path, ['DB_PASSWORD' => $value]));

    expect(readEnv($this->path)['DB_PASSWORD'])->toBe($value);
})->with([
    'spaces' => 'correct horse battery',
    'hash' => 'pa#ss word',
    'dollar' => 'S3cr$t${APP_KEY}',
    'quotes' => 'say "cheese" and \'bread\'',
    'backslash' => 'C:\\bakery\\new',
    'equals' => 'a=b==c',
    'unicode' => 'Pão de Açúcar 2026',
    'empty' => '',
]);

it('writes booleans, numbers and null as plain words', function () {
    $this->writer->write(new EnvWriteInput($this->path, ['APP_DEBUG' => false, 'DB_PORT' => 5432, 'MAIL_SCHEME' => null]));

    expect(file_get_contents($this->path))->toBe("APP_DEBUG=false\nDB_PORT=5432\nMAIL_SCHEME=null\n");
});

it('refuses line breaks and bad keys', function () {
    expect(fn () => $this->writer->write(new EnvWriteInput($this->path, ['DB_PASSWORD' => "one\ntwo"])))->toThrow(InvalidEnvValue::class)
        ->and(fn () => $this->writer->write(new EnvWriteInput($this->path, ['BAD KEY' => 'x'])))->toThrow(InvalidEnvValue::class);
});

it('writes atomically and leaves no temporary files', function () {
    file_put_contents($this->path, "APP_NAME=Verso\n");

    $this->writer->write(new EnvWriteInput($this->path, ['APP_NAME' => 'Lisbon Food Notes']));

    expect(glob($this->folder.'/.*.tmp'))->toBe([])
        ->and(decoct(fileperms($this->path) & 0777))->toBe('640');
});

it('replaces every active copy of a key', function () {
    file_put_contents($this->path, "APP_URL=http://one.test\nexport APP_URL=http://two.test\n");

    $this->writer->write(new EnvWriteInput($this->path, ['APP_URL' => 'https://harborlane.example']));

    expect(file_get_contents($this->path))->toBe("APP_URL=https://harborlane.example\nAPP_URL=https://harborlane.example\n");
});

it('makes app keys Laravel can use', function () {
    expect(strlen((string) base64_decode(substr($this->writer->newAppKey(), 7), true)))->toBe(32);
});

it('creates .env from the example with a fresh key', function () {
    file_put_contents($this->folder.'/.env.example', "APP_NAME=Verso\nAPP_KEY=\nDB_CONNECTION=sqlite\n");

    expect(EnvironmentFile::createFrom($this->folder.'/.env.example', $this->path))->toBeTrue();

    $values = readEnv($this->path);

    expect($values['APP_KEY'])->toStartWith('base64:')
        ->and($values['DB_CONNECTION'])->toBe('sqlite')
        ->and(decoct(fileperms($this->path) & 0777))->toBe('640');
});

it('fills an empty key in an existing .env', function () {
    file_put_contents($this->path, "APP_NAME=Verso\nAPP_KEY=\n");
    $saved = [$_ENV['APP_KEY'] ?? null, $_SERVER['APP_KEY'] ?? null, getenv('APP_KEY')];
    unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
    putenv('APP_KEY');

    try {
        expect(EnvironmentFile::fillEmptyKey($this->path))->toBeTrue()
            ->and(readEnv($this->path)['APP_KEY'])->toStartWith('base64:')
            ->and(EnvironmentFile::fillEmptyKey($this->path))->toBeFalse();
    } finally {
        [$env, $server, $process] = $saved;
        $env === null ?: $_ENV['APP_KEY'] = $env;
        $server === null ?: $_SERVER['APP_KEY'] = $server;
        $process === false ?: putenv('APP_KEY='.$process);
    }
});

it('does not create .env while tests run', function () {
    file_put_contents($this->folder.'/.env.example', "APP_KEY=\n");

    expect(EnvironmentFile::ensure($this->folder))->toBeFalse()
        ->and(file_exists($this->path))->toBeFalse();
});

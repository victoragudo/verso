<?php

use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\Finding;
use Illuminate\Support\Facades\Lang;

beforeEach(function () {
    Lang::setLoaded(['*' => ['*' => ['es' => [
        'The code connects to :host, which is not in the network list of verso.json.' => 'El código se conecta a :host, que no está en la lista network de verso.json.',
        'Backticks run a shell command.' => 'Las comillas invertidas ejecutan un comando de shell.',
    ]]]]);
});

it('keeps the English message and the text with its values for storage', function () {
    $finding = new Finding('permission.undeclared-network', Severity::Medium, 'The code connects to :host, which is not in the network list of verso.json.', 'src/StormFeed.php', 18, ['host' => 'api.stormwatch.io']);

    expect($finding->message)->toBe('The code connects to api.stormwatch.io, which is not in the network list of verso.json.')
        ->and($finding->toArray())->not->toHaveKey('text')
        ->and($finding->toStoredArray())->toMatchArray([
            'message' => 'The code connects to api.stormwatch.io, which is not in the network list of verso.json.',
            'text' => 'The code connects to :host, which is not in the network list of verso.json.',
            'replace' => ['host' => 'api.stormwatch.io'],
        ]);
});

it('shows a stored finding in the admin language', function () {
    $stored = (new Finding('permission.undeclared-network', Severity::Medium, 'The code connects to :host, which is not in the network list of verso.json.', 'src/StormFeed.php', 18, ['host' => 'api.stormwatch.io']))->toStoredArray();

    app()->setLocale('es');

    expect(Finding::fromArray($stored)->toDisplayArray())->toBe([
        'rule' => 'permission.undeclared-network',
        'severity' => 'medium',
        'message' => 'El código se conecta a api.stormwatch.io, que no está en la lista network de verso.json.',
        'file' => 'src/StormFeed.php',
        'line' => 18,
    ]);
});

it('translates findings without values by their English message', function () {
    app()->setLocale('es');

    $finding = Finding::fromArray(['rule' => 'php.shell', 'severity' => 'high', 'message' => 'Backticks run a shell command.', 'file' => 'src/Backup.php', 'line' => 4]);

    expect($finding->translated())->toBe('Las comillas invertidas ejecutan un comando de shell.');
});

it('shows reports stored before findings kept their text in English', function () {
    app()->setLocale('es');

    $finding = Finding::fromArray(['rule' => 'php.superglobal', 'severity' => 'low', 'message' => 'Reads $_POST directly instead of the Laravel request.', 'file' => 'src/Form.php', 'line' => 9]);

    expect($finding->translated())->toBe('Reads $_POST directly instead of the Laravel request.');
});

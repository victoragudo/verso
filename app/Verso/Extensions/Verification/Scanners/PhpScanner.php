<?php

namespace App\Verso\Extensions\Verification\Scanners;

use App\Verso\Extensions\Verification\CapabilityMap;
use App\Verso\Extensions\Verification\Enums\Severity;
use App\Verso\Extensions\Verification\ScanResult;

// Reads PHP code with the PHP tokenizer and reports dangerous calls, obfuscation and what the code really uses.
class PhpScanner
{
    // Functions that run shell commands.
    protected const SHELL_FUNCTIONS = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec'];

    // Functions that decode hidden data. Feeding them to eval or include is a sign of malware.
    protected const DECODE_FUNCTIONS = ['base64_decode', 'gzinflate', 'gzuncompress', 'gzdecode', 'str_rot13', 'hex2bin', 'convert_uudecode', 'strrev', 'rawurldecode'];

    // Functions that write, move or delete files. The value is the argument that holds the target path.
    protected const FILE_WRITE_FUNCTIONS = [
        'file_put_contents' => 0, 'fopen' => 0, 'mkdir' => 0, 'rename' => 1, 'unlink' => 0, 'copy' => 1, 'rmdir' => 0,
        'touch' => 0, 'chmod' => 0, 'chown' => 0, 'symlink' => 1, 'link' => 1, 'move_uploaded_file' => 1,
    ];

    // Laravel File facade methods that write. The value is the argument that holds the target path.
    protected const FILE_FACADE_WRITES = [
        'put' => 0, 'append' => 0, 'prepend' => 0, 'replace' => 0, 'delete' => 0, 'move' => 1, 'copy' => 1, 'makedirectory' => 0,
        'ensuredirectoryexists' => 0, 'deletedirectory' => 0, 'cleandirectory' => 0, 'chmod' => 0, 'link' => 1, 'copydirectory' => 1, 'movedirectory' => 1,
    ];

    // Storage facade methods that write to a disk.
    protected const STORAGE_WRITES = ['put', 'putfile', 'putfileas', 'writestream', 'append', 'prepend', 'delete', 'move', 'copy', 'makedirectory', 'deletedirectory'];

    // Functions that open network connections.
    protected const NETWORK_FUNCTIONS = ['curl_init', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_create', 'socket_connect', 'ftp_connect', 'ftp_ssl_connect'];

    // Functions that read a file and also accept URLs.
    protected const URL_READ_FUNCTIONS = ['file_get_contents', 'fopen', 'file', 'readfile', 'get_headers', 'copy', 'simplexml_load_file'];

    // Database functions that run SQL text directly.
    protected const RAW_SQL_FUNCTIONS = ['mysqli_query', 'mysqli_multi_query', 'mysqli_real_query', 'pg_query', 'pg_send_query'];

    // Query builder methods that take raw SQL pieces.
    protected const RAW_SQL_METHODS = ['whereraw', 'orwhereraw', 'selectraw', 'orderbyraw', 'havingraw', 'groupbyraw', 'fromraw'];

    // Connection methods that run SQL text without bindings.
    protected const UNPREPARED_METHODS = ['statement', 'unprepared'];

    // Functions that change PHP settings or the process environment.
    protected const ENVIRONMENT_FUNCTIONS = ['ini_set', 'ini_alter', 'ini_restore', 'putenv', 'set_include_path', 'apache_setenv'];

    // Functions that call another function by name.
    protected const CALLER_FUNCTIONS = ['call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array'];

    protected const SUPERGLOBALS = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES', '$_SERVER', '$_ENV', '$_SESSION', '$GLOBALS'];

    // Calls whose arguments may build a path that stays inside the plugin, storage or the temp folder.
    protected const SAFE_PATH_FUNCTIONS = ['storage_path', 'dirname', 'sys_get_temp_dir', 'tempnam'];

    // Calls that build a path inside the application, outside the plugin.
    protected const APP_PATH_FUNCTIONS = ['base_path', 'app_path', 'public_path', 'config_path', 'resource_path', 'database_path', 'lang_path'];

    // Functions allowed inside a static include path.
    protected const INCLUDE_PATH_FUNCTIONS = ['dirname', 'base_path', 'app_path', 'storage_path', 'resource_path', 'config_path', 'database_path'];

    // Classes that open network connections when created.
    protected const NETWORK_CLASSES = ['GuzzleHttp\\Client', 'Symfony\\Component\\HttpClient\\HttpClient', 'Symfony\\Component\\HttpClient\\CurlHttpClient', 'Symfony\\Component\\HttpClient\\NativeHttpClient'];

    // Classes that run shell commands when created.
    protected const PROCESS_CLASSES = ['Symfony\\Component\\Process\\Process', 'Illuminate\\Support\\Facades\\Process'];

    // URL schemes that count as network hosts.
    protected const URL_PATTERN = '~^(?:https?|ftps?|wss?|tcp|udp|ssl|tls)://([a-z0-9.-]+)~i';

    public function __construct(
        protected CapabilityMap $capabilities,
        protected int $encodedStringLength,
    ) {}

    // Scan one PHP file. $file is the path inside the plugin, used in findings.
    public function scan(string $file, string $code, ScanResult $result, int $lineOffset = 0): void
    {
        $tokens = new PhpTokens($code, $lineOffset);

        if ($tokens->parseError !== null) {
            $result->add('php.syntax-error', Severity::High, message: 'The file has a PHP syntax error (:error).', file: $file, line: $tokens->parseErrorLine, replace: ['error' => (string) $tokens->parseError]);
        }

        $urls = [];
        $insideBackticks = false;
        $count = $tokens->count();
        $callables = $this->callableVariables($tokens);

        $this->checkImports($tokens, $file, $result);

        for ($i = 0; $i < $count; $i++) {
            $id = $tokens->id($i);
            $line = $tokens->line($i);

            if ($tokens->is($i, '`')) {
                if (! $insideBackticks) {
                    $result->add('php.shell', Severity::High, message: 'Backticks run a shell command.', file: $file, line: $line);
                }

                $insideBackticks = ! $insideBackticks;

                continue;
            }

            match (true) {
                $id === T_EVAL => $this->checkEval($tokens, $i, $file, $result),
                in_array($id, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true) => $this->checkInclude($tokens, $i, $file, $result),
                $id === T_VARIABLE => $this->checkVariable($tokens, $i, $file, $result, $callables),
                $id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE => $this->checkString($tokens, $i, $file, $result, $urls),
                in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) => $this->recordClass($tokens, $i, $file, $result),
                $id === T_NEW && $tokens->isName($i + 1) => $this->checkNew($tokens, $tokens->resolve($tokens->text($i + 1)), $i, $file, $result),
                $tokens->isName($i) && $tokens->is($i + 1, '(') => $this->checkFunctionCall($tokens, $i, $file, $result),
                $tokens->isName($i) && $tokens->id($i + 1) === T_DOUBLE_COLON && $tokens->id($i + 2) === T_STRING && $tokens->is($i + 3, '(') => $this->checkStaticCall($tokens, $i, $file, $result),
                $id === T_OBJECT_OPERATOR || $id === T_NULLSAFE_OBJECT_OPERATOR => $this->checkMethodCall($tokens, $i, $file, $result),
                $tokens->is($i, '$') && ($tokens->id($i + 1) === T_VARIABLE || $tokens->is($i + 1, '{')) => $result->add('php.dynamic-variable', Severity::Low, message: 'A variable variable hides which variable is used.', file: $file, line: $line),
                default => null,
            };
        }

        if ($urls !== [] && ($result->callsNetworkIn($file) || str_starts_with($file, 'config/'))) {
            foreach ($urls as $host => $hostLine) {
                $result->nameHost($host, $file, $hostLine);
            }
        }
    }

    /**
     * Variables that hold closures: typed as Closure or callable, or given a closure, an arrow function or a first class callable.
     *
     * @return array<string, true>
     */
    protected function callableVariables(PhpTokens $tokens): array
    {
        $variables = [];
        $count = $tokens->count();

        for ($i = 0; $i < $count; $i++) {
            if ($tokens->id($i) !== T_VARIABLE) {
                continue;
            }

            $typed = $tokens->id($i - 1) === T_CALLABLE || ($tokens->isName($i - 1) && strtolower(ltrim($tokens->text($i - 1), '\\')) === 'closure')
                || ($tokens->is($i - 1, '?') && $tokens->isName($i - 2) && strtolower(ltrim($tokens->text($i - 2), '\\')) === 'closure');
            $assigned = $tokens->is($i + 1, '=') && (in_array($tokens->id($i + 2), [T_FUNCTION, T_FN, T_STATIC], true) || $this->isFirstClassCallable($tokens, $i + 2));

            if ($typed || $assigned) {
                $variables[$tokens->text($i)] = true;
            }
        }

        return $variables;
    }

    // True for "strlen(...)" or "$this->method(...)" style first class callables starting at $index.
    protected function isFirstClassCallable(PhpTokens $tokens, int $index): bool
    {
        $count = $tokens->count();

        for ($i = $index; $i < $count && ! $tokens->is($i, ';'); $i++) {
            if ($tokens->is($i, '(') && $tokens->id($i + 1) === T_ELLIPSIS && $tokens->is($i + 2, ')')) {
                return true;
            }
        }

        return false;
    }

    // Imported classes that need a capability.
    protected function checkImports(PhpTokens $tokens, string $file, ScanResult $result): void
    {
        foreach ($tokens->imports as $class) {
            $capability = $this->capabilities->forClass($class);

            if ($capability !== null) {
                $result->useCapability($capability->value, $file, $this->firstLineOf($tokens, $class));
            }
        }
    }

    protected function checkEval(PhpTokens $tokens, int $index, string $file, ScanResult $result): void
    {
        $line = $tokens->line($index);
        $result->add('php.eval', Severity::High, message: 'eval() runs PHP code built at runtime.', file: $file, line: $line);

        if ($tokens->is($index + 1, '(') && $this->rangeCallsAny($tokens, $index + 1, $tokens->closing($index + 1), self::DECODE_FUNCTIONS)) {
            $result->add('php.obfuscated-code', Severity::High, message: 'eval() runs code that is decoded first, a common way to hide malware.', file: $file, line: $line);
        }
    }

    // include and require must name a fixed file. Paths built from variables can load any code.
    protected function checkInclude(PhpTokens $tokens, int $index, string $file, ScanResult $result): void
    {
        $line = $tokens->line($index);
        $end = $this->expressionEnd($tokens, $index + 1);

        if ($this->rangeCallsAny($tokens, $index + 1, $end, self::DECODE_FUNCTIONS)) {
            $result->add('php.obfuscated-code', Severity::High, message: 'A file is included from a decoded path or decoded data.', file: $file, line: $line);

            return;
        }

        for ($i = $index + 1; $i <= $end; $i++) {
            $id = $tokens->id($i);
            $allowed = $id === T_CONSTANT_ENCAPSED_STRING || $id === T_DIR || $id === T_FILE || $id === T_LNUMBER
                || $tokens->is($i, '.') || $tokens->is($i, '(') || $tokens->is($i, ')') || $tokens->is($i, ',')
                || ($id === T_STRING && in_array(strtolower($tokens->text($i)), self::INCLUDE_PATH_FUNCTIONS, true));

            if (! $allowed) {
                $result->add('php.dynamic-include', Severity::Medium, message: 'A file is included from a path built at runtime.', file: $file, line: $line);

                return;
            }
        }
    }

    /**
     * Superglobals and calls through a variable that does not hold a closure.
     *
     * @param  array<string, true>  $callables
     */
    protected function checkVariable(PhpTokens $tokens, int $index, string $file, ScanResult $result, array $callables): void
    {
        $name = $tokens->text($index);

        if (in_array($name, self::SUPERGLOBALS, true)) {
            $result->add('php.superglobal', Severity::Low, message: 'Reads :name directly instead of the Laravel request.', file: $file, line: $tokens->line($index), replace: ['name' => $name]);

            return;
        }

        $previous = $tokens->id($index - 1);

        if ($tokens->is($index + 1, '(') && ! isset($callables[$name]) && ! in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_FUNCTION, T_FN], true)) {
            $result->add('php.dynamic-function', Severity::Low, message: 'Calls a function whose name is stored in :name.', file: $file, line: $tokens->line($index), replace: ['name' => $name]);
        }
    }

    /**
     * Long encoded strings, function names called as strings and URLs that point to network hosts.
     *
     * @param  array<string, int>  $urls
     */
    protected function checkString(PhpTokens $tokens, int $index, string $file, ScanResult $result, array &$urls): void
    {
        $text = $tokens->literal($index) ?? $tokens->text($index);
        $line = $tokens->line($index);
        $compact = preg_replace('/[\r\n]+/', '', $text) ?? $text;

        if (strlen($compact) >= $this->encodedStringLength && preg_match('~^(?:[A-Za-z0-9+/]+={0,2}|[A-Fa-f0-9]+)$~', $compact) === 1) {
            $result->add('php.encoded-string', Severity::Medium, message: 'A very long encoded string (:length characters) may hide code.', file: $file, line: $line, replace: ['length' => strlen($compact)]);
        }

        if (preg_match(self::URL_PATTERN, trim($text), $match) === 1) {
            $urls[strtolower($match[1])] ??= $line;
        }

        if ($tokens->is($index + 1, '(') && $tokens->id($index) === T_CONSTANT_ENCAPSED_STRING) {
            $this->checkNamedFunction(strtolower(ltrim($text, '\\')), $tokens, $index, $file, $result, true);
        }
    }

    protected function recordClass(PhpTokens $tokens, int $index, string $file, ScanResult $result): void
    {
        if ($tokens->id($index - 1) === T_DOUBLE_COLON || $tokens->id($index - 1) === T_NEW || $tokens->id($index + 1) !== T_STRING) {
            return;
        }

        $class = ltrim($tokens->namespace.'\\'.$tokens->text($index + 1), '\\');
        $result->classes[$class] ??= ['file' => $file, 'line' => $tokens->line($index)];
    }

    protected function checkNew(PhpTokens $tokens, string $class, int $index, string $file, ScanResult $result): void
    {
        $line = $tokens->line($index);

        if (in_array($class, self::PROCESS_CLASSES, true)) {
            $result->add('php.shell', Severity::High, message: 'Starts a shell process with :class.', file: $file, line: $line, replace: ['class' => class_basename($class)]);
        }

        if (in_array($class, self::NETWORK_CLASSES, true)) {
            $this->network($result, message: 'Opens HTTP connections with :class.', file: $file, line: $line, replace: ['class' => class_basename($class)]);
        }

        $capability = $this->capabilities->forClass($class);

        if ($capability !== null) {
            $result->useCapability($capability->value, $file, $line);
        }
    }

    protected function checkFunctionCall(PhpTokens $tokens, int $index, string $file, ScanResult $result): void
    {
        $previous = $tokens->id($index - 1);

        if (in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true)) {
            return;
        }

        $name = $tokens->text($index);

        if ($tokens->id($index) === T_NAME_QUALIFIED || ($tokens->id($index) === T_NAME_FULLY_QUALIFIED && substr_count($name, '\\') > 1)) {
            return;
        }

        $this->checkNamedFunction(strtolower(ltrim($name, '\\')), $tokens, $index, $file, $result, false);
    }

    // Check a call to a global function. $asString is true for calls like 'exec'('ls').
    protected function checkNamedFunction(string $function, PhpTokens $tokens, int $index, string $file, ScanResult $result, bool $asString): void
    {
        $line = $tokens->line($index);
        $open = $index + 1;
        $isCall = $tokens->is($open, '(');
        $arguments = $isCall ? $tokens->arguments($open) : [];

        match (true) {
            in_array($function, self::SHELL_FUNCTIONS, true) && $asString => $result->add('php.shell', Severity::High, message: ':function() runs a shell command through a string name.', file: $file, line: $line, replace: ['function' => $function]),
            in_array($function, self::SHELL_FUNCTIONS, true) => $result->add('php.shell', Severity::High, message: ':function() runs a shell command.', file: $file, line: $line, replace: ['function' => $function]),
            $function === 'dl' => $result->add('php.dl', Severity::High, message: 'dl() loads a PHP extension at runtime.', file: $file, line: $line),
            $function === 'create_function' => $result->add('php.create-function', Severity::High, message: 'create_function() runs PHP code built from a string.', file: $file, line: $line),
            $function === 'eval' && $asString => $result->add('php.eval', Severity::High, message: 'eval() is called through a string name.', file: $file, line: $line),
            $function === 'eval' => $result->add('php.eval', Severity::High, message: 'eval() is called.', file: $file, line: $line),
            $function === 'assert' => $this->checkAssert($tokens, $arguments, $file, $line, $result),
            $function === 'preg_replace' || $function === 'preg_filter' => $this->checkPregReplace($tokens, $arguments, $file, $line, $result),
            in_array($function, self::RAW_SQL_FUNCTIONS, true) => $result->add('php.raw-sql', Severity::Medium, message: ':function() runs SQL text directly.', file: $file, line: $line, replace: ['function' => $function]),
            in_array($function, self::ENVIRONMENT_FUNCTIONS, true) => $result->add('php.environment', Severity::Medium, message: ':function() changes PHP settings or the environment.', file: $file, line: $line, replace: ['function' => $function]),
            in_array($function, self::NETWORK_FUNCTIONS, true) => $this->network($result, message: ':function() opens a network connection.', file: $file, line: $line, replace: ['function' => $function]),
            in_array($function, self::CALLER_FUNCTIONS, true) => $this->checkCaller($tokens, $arguments, $file, $line, $result),
            default => null,
        };

        if ($isCall && in_array($function, self::DECODE_FUNCTIONS, true) && $this->rangeCallsAny($tokens, $open, $tokens->closing($open), self::DECODE_FUNCTIONS, $open + 1)) {
            $result->add('php.obfuscated-code', Severity::Medium, message: ':function() decodes data that is itself decoded, a common way to hide code.', file: $file, line: $line, replace: ['function' => $function]);
        }

        $urlRead = in_array($function, self::URL_READ_FUNCTIONS, true) ? $this->literalUrl($tokens, $arguments[0] ?? null) : null;

        if ($urlRead !== null) {
            $this->network($result, message: ':function() reads a URL on :host.', file: $file, line: $line, replace: ['function' => $function, 'host' => $urlRead]);
            $result->nameHost($urlRead, $file, $line);

            return;
        }

        if (isset(self::FILE_WRITE_FUNCTIONS[$function]) && ! ($function === 'fopen' && $this->isReadMode($tokens, $arguments[1] ?? null))) {
            $this->checkWriteTarget($tokens, $arguments[self::FILE_WRITE_FUNCTIONS[$function]] ?? null, "{$function}()", $file, $line, $result);
        }
    }

    protected function checkStaticCall(PhpTokens $tokens, int $index, string $file, ScanResult $result): void
    {
        $class = $tokens->resolve($tokens->text($index));
        $short = class_basename($class);
        $method = strtolower($tokens->text($index + 2));
        $line = $tokens->line($index);
        $arguments = $tokens->arguments($index + 3);
        $facade = fn (string $name): bool => $short === $name && in_array($class, [$name, 'Illuminate\\Support\\Facades\\'.$name], true);

        if ($facade('DB') && in_array($method, self::UNPREPARED_METHODS, true)) {
            $result->add('php.raw-sql', Severity::Medium, message: 'DB:::method() runs SQL text without bindings.', file: $file, line: $line, replace: ['method' => $tokens->text($index + 2)]);
        } elseif ($facade('DB') && $method === 'raw') {
            $result->add('php.raw-sql-fragment', Severity::Low, message: 'DB::raw() puts SQL text into a query.', file: $file, line: $line);
        } elseif ($facade('Http')) {
            $this->network($result, message: 'Http:::method() sends an HTTP request.', file: $file, line: $line, replace: ['method' => $tokens->text($index + 2)]);
        } elseif ($facade('Process')) {
            $result->add('php.shell', Severity::High, message: 'The Process facade runs shell commands.', file: $file, line: $line);
        } elseif ($facade('File') && isset(self::FILE_FACADE_WRITES[$method])) {
            $this->checkWriteTarget($tokens, $arguments[self::FILE_FACADE_WRITES[$method]] ?? null, "File::{$tokens->text($index + 2)}()", $file, $line, $result);
        } elseif ($facade('Storage') && ($method === 'disk' || in_array($method, self::STORAGE_WRITES, true))) {
            if ($method !== 'disk' || $this->chainCallsAny($tokens, $tokens->closing($index + 3), self::STORAGE_WRITES)) {
                $result->useCapability('upload_files', $file, $line);
            }
        } elseif ($facade('Schema') && $method === 'create' && str_contains($file, 'migrations/')) {
            $table = $tokens->argumentLiteral($arguments[0] ?? [0, -1]);

            if ($table !== null) {
                $result->tables[$table] ??= ['file' => $file, 'line' => $line];
            }
        }

        $capability = $this->capabilities->forModelWrite($class, $method) ?? $this->capabilities->forClass($class);

        if ($capability !== null) {
            $result->useCapability($capability->value, $file, $line);
        }
    }

    // Method calls: raw SQL pieces, settings writes and config keys merged by the provider.
    protected function checkMethodCall(PhpTokens $tokens, int $index, string $file, ScanResult $result): void
    {
        if ($tokens->id($index + 1) !== T_STRING || ! $tokens->is($index + 2, '(')) {
            return;
        }

        $method = strtolower($tokens->text($index + 1));
        $line = $tokens->line($index);

        if (in_array($method, self::RAW_SQL_METHODS, true)) {
            $result->add('php.raw-sql-fragment', Severity::Low, message: ':method() puts SQL text into a query.', file: $file, line: $line, replace: ['method' => $tokens->text($index + 1)]);
        } elseif (in_array($method, self::UNPREPARED_METHODS, true)) {
            $result->add('php.raw-sql', Severity::Medium, message: ':method() runs SQL text without bindings.', file: $file, line: $line, replace: ['method' => $tokens->text($index + 1)]);
        } elseif ($method === 'set' && in_array(CapabilityMap::SETTINGS_CLASS, $tokens->imports, true)) {
            $result->useCapability('manage_settings', $file, $line);
        } elseif ($method === 'mergeconfigfrom') {
            $arguments = $tokens->arguments($index + 2);
            $key = $tokens->argumentLiteral($arguments[1] ?? [0, -1]);

            if ($key !== null) {
                $result->configKeys[$key] ??= $file;
            }
        }
    }

    /**
     * assert() with a string runs it as code on old PHP versions.
     *
     * @param  list<array{0: int, 1: int}>  $arguments
     */
    protected function checkAssert(PhpTokens $tokens, array $arguments, string $file, int $line, ScanResult $result): void
    {
        $first = $arguments[0] ?? null;

        if ($first !== null && in_array($tokens->id($first[0]), [T_CONSTANT_ENCAPSED_STRING, T_START_HEREDOC], true) || ($first !== null && $tokens->is($first[0], '"'))) {
            $result->add('php.assert-string', Severity::High, message: 'assert() is given a string, which older PHP runs as code.', file: $file, line: $line);
        }
    }

    /**
     * The /e modifier of preg_replace runs the replacement as PHP code.
     *
     * @param  list<array{0: int, 1: int}>  $arguments
     */
    protected function checkPregReplace(PhpTokens $tokens, array $arguments, string $file, int $line, ScanResult $result): void
    {
        $pattern = $tokens->argumentLiteral($arguments[0] ?? [0, -1]);

        if ($pattern === null || $pattern === '') {
            return;
        }

        $delimiter = $pattern[0];
        $closing = ['(' => ')', '{' => '}', '[' => ']', '<' => '>'][$delimiter] ?? $delimiter;
        $end = strrpos($pattern, $closing);

        if ($end !== false && $end > 0 && str_contains(substr($pattern, $end + 1), 'e')) {
            $result->add('php.preg-replace-eval', Severity::High, message: 'preg_replace() with the /e modifier runs the replacement as code.', file: $file, line: $line);
        }
    }

    /**
     * call_user_func() with a name from a variable hides which function runs.
     *
     * @param  list<array{0: int, 1: int}>  $arguments
     */
    protected function checkCaller(PhpTokens $tokens, array $arguments, string $file, int $line, ScanResult $result): void
    {
        $first = $arguments[0] ?? null;

        if ($first === null) {
            return;
        }

        $name = $tokens->argumentLiteral($first);

        if ($name !== null) {
            $this->checkNamedFunction(strtolower(ltrim($name, '\\')), $tokens, $first[0], $file, $result, true);

            return;
        }

        if ($first[0] === $first[1] && $tokens->id($first[0]) === T_VARIABLE) {
            $result->add('php.dynamic-function', Severity::Low, message: 'Calls a function whose name is stored in :name.', file: $file, line: $line, replace: ['name' => $tokens->text($first[0])]);
        }
    }

    /**
     * Report a file write unless its path clearly stays inside the plugin, storage or the temp folder.
     *
     * @param  array{0: int, 1: int}|null  $range
     */
    protected function checkWriteTarget(PhpTokens $tokens, ?array $range, string $call, string $file, int $line, ScanResult $result): void
    {
        if ($range === null) {
            return;
        }

        $first = $tokens->id($range[0]);
        $firstName = strtolower($tokens->text($range[0]));
        $literal = $tokens->literal($range[0]);

        if ($first === T_DIR || ($first === T_STRING && in_array($firstName, self::SAFE_PATH_FUNCTIONS, true) && ($firstName !== 'dirname' || $tokens->id($range[0] + 2) === T_DIR))) {
            return;
        }

        if ($first === T_STRING && in_array($firstName, self::APP_PATH_FUNCTIONS, true)) {
            $result->add('php.file-write', Severity::High, message: ':call writes inside the application with :function(), outside the plugin folder.', file: $file, line: $line, replace: ['call' => $call, 'function' => $firstName]);

            return;
        }

        if ($literal !== null && (str_starts_with($literal, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $literal) === 1 || str_contains($literal, '..'))) {
            $result->add('php.file-write', Severity::High, message: ':call writes to ":path", outside the plugin folder.', file: $file, line: $line, replace: ['call' => $call, 'path' => $literal]);

            return;
        }

        if ($literal !== null && (preg_match('~^[a-z][a-z0-9+.-]*://~i', $literal) !== 1 || preg_match('~^php://(memory|temp|output|stdout|stderr)~i', $literal) === 1)) {
            return;
        }

        $result->add('php.file-write', Severity::Medium, message: ':call writes to a path built at runtime that may be outside the plugin folder.', file: $file, line: $line, replace: ['call' => $call]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected function network(ScanResult $result, string $message, string $file, int $line, array $replace = []): void
    {
        $result->callNetwork($file, $line);
        $result->add('php.network', Severity::Low, $message, $file, $line, $replace);
    }

    /**
     * Host of a literal URL argument, or null.
     *
     * @param  array{0: int, 1: int}|null  $range
     */
    protected function literalUrl(PhpTokens $tokens, ?array $range): ?string
    {
        $literal = $range !== null ? $tokens->literal($range[0]) : null;

        return $literal !== null && preg_match(self::URL_PATTERN, $literal, $match) === 1 ? strtolower($match[1]) : null;
    }

    /**
     * fopen modes that only read, like "r" or "rb".
     *
     * @param  array{0: int, 1: int}|null  $range
     */
    protected function isReadMode(PhpTokens $tokens, ?array $range): bool
    {
        $mode = $range !== null ? $tokens->argumentLiteral($range) : null;

        return $mode !== null && str_starts_with($mode, 'r') && ! str_contains($mode, '+');
    }

    /**
     * True when a call to one of the functions appears between two tokens.
     *
     * @param  list<string>  $functions
     */
    protected function rangeCallsAny(PhpTokens $tokens, int $from, int $to, array $functions, ?int $start = null): bool
    {
        for ($i = $start ?? $from; $i <= $to; $i++) {
            if (($tokens->id($i) === T_STRING || $tokens->id($i) === T_NAME_FULLY_QUALIFIED) && $tokens->is($i + 1, '(')
                && in_array(strtolower(ltrim($tokens->text($i), '\\')), $functions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the call chain after a ")" goes on with one of the methods, like Storage::disk('x')->put().
     *
     * @param  list<string>  $methods
     */
    protected function chainCallsAny(PhpTokens $tokens, int $close, array $methods): bool
    {
        return $tokens->id($close + 1) === T_OBJECT_OPERATOR && in_array(strtolower($tokens->text($close + 2)), $methods, true);
    }

    // Last token of the expression that starts at $index (stops at ";" or an unmatched ")").
    protected function expressionEnd(PhpTokens $tokens, int $index): int
    {
        $depth = 0;
        $count = $tokens->count();

        for ($i = $index; $i < $count; $i++) {
            if ($tokens->is($i, '(') || $tokens->is($i, '[')) {
                $depth++;
            } elseif ($tokens->is($i, ')') || $tokens->is($i, ']')) {
                if ($depth === 0) {
                    return $i - 1;
                }

                $depth--;
            } elseif ($depth === 0 && ($tokens->is($i, ';') || $tokens->is($i, ','))) {
                return $i - 1;
            }
        }

        return $count - 1;
    }

    // Line of the "use" statement that imports the class, or 1.
    protected function firstLineOf(PhpTokens $tokens, string $class): int
    {
        $count = $tokens->count();

        for ($i = 0; $i < $count; $i++) {
            if ($tokens->isName($i) && ltrim($tokens->text($i), '\\') === $class) {
                return $tokens->line($i);
            }
        }

        return 1;
    }
}

<?php

namespace App\Verso\Extensions\Verification\Scanners;

use ParseError;

// PHP source split into tokens without whitespace and comments, with helpers to walk calls and arguments.
final class PhpTokens
{
    // Tokens that carry no meaning for the scan.
    protected const SKIPPED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG];

    /** @var list<array{0: int|null, 1: string, 2: int}> */
    public array $tokens = [];

    public ?string $parseError = null;

    public ?int $parseErrorLine = null;

    public string $namespace = '';

    /** @var array<string, string> alias in lower case => fully qualified class name */
    public array $imports = [];

    public function __construct(string $code, int $lineOffset = 0)
    {
        try {
            $raw = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $error) {
            $this->parseError = $error->getMessage();
            $this->parseErrorLine = $error->getLine() + $lineOffset;
            $raw = token_get_all($code);
        }

        $line = 1;

        foreach ($raw as $token) {
            if (is_array($token)) {
                $line = $token[2];

                if (! in_array($token[0], self::SKIPPED, true)) {
                    $this->tokens[] = [$token[0], $token[1], $token[2] + $lineOffset];
                }

                $line += substr_count($token[1], "\n");

                continue;
            }

            $this->tokens[] = [null, $token, $line + $lineOffset];
        }

        $this->readNamespaceAndImports();
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function id(int $index): ?int
    {
        return $this->tokens[$index][0] ?? null;
    }

    public function text(int $index): string
    {
        return $this->tokens[$index][1] ?? '';
    }

    public function line(int $index): int
    {
        return $this->tokens[$index][2] ?? 0;
    }

    // True when the token is the given single character, like "(".
    public function is(int $index, string $char): bool
    {
        return isset($this->tokens[$index]) && $this->tokens[$index][0] === null && $this->tokens[$index][1] === $char;
    }

    // True when the token is a name: a plain, qualified or fully qualified identifier.
    public function isName(int $index): bool
    {
        return in_array($this->id($index), [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true);
    }

    // Index of the parenthesis that closes the one at $open, or the last token when it is missing.
    public function closing(int $open): int
    {
        $depth = 0;
        $count = $this->count();

        for ($i = $open; $i < $count; $i++) {
            if ($this->is($i, '(') || $this->is($i, '[') || $this->is($i, '{') || $this->id($i) === T_CURLY_OPEN || $this->id($i) === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($this->is($i, ')') || $this->is($i, ']') || $this->is($i, '}')) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $count - 1;
    }

    /**
     * Token ranges of the arguments of the call whose "(" is at $open.
     *
     * @return list<array{0: int, 1: int}> first and last index of each argument
     */
    public function arguments(int $open): array
    {
        $close = $this->closing($open);
        $arguments = [];
        $start = $open + 1;
        $depth = 0;

        for ($i = $open + 1; $i < $close; $i++) {
            if ($this->is($i, '(') || $this->is($i, '[') || $this->is($i, '{')) {
                $depth++;
            } elseif ($this->is($i, ')') || $this->is($i, ']') || $this->is($i, '}')) {
                $depth--;
            } elseif ($depth === 0 && $this->is($i, ',')) {
                $arguments[] = [$start, $i - 1];
                $start = $i + 1;
            }
        }

        if ($start <= $close - 1) {
            $arguments[] = [$start, $close - 1];
        }

        return $arguments;
    }

    // The value of a single quoted or double quoted string literal without variables, or null.
    public function literal(int $index): ?string
    {
        if ($this->id($index) !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $text = $this->text($index);
        $body = substr($text, 1, -1);

        return $text[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $body) : stripcslashes($body);
    }

    /**
     * The literal when the argument is exactly one string, or null.
     *
     * @param  array{0: int, 1: int}  $range
     */
    public function argumentLiteral(array $range): ?string
    {
        return $range[0] === $range[1] ? $this->literal($range[0]) : null;
    }

    // Turn a class name as written in the file into a fully qualified name.
    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        if (str_starts_with(strtolower($name), 'namespace\\')) {
            return ltrim($this->namespace.'\\'.substr($name, 10), '\\');
        }

        $parts = explode('\\', $name);
        $first = strtolower($parts[0]);

        if (isset($this->imports[$first])) {
            $parts[0] = $this->imports[$first];

            return implode('\\', $parts);
        }

        return ltrim($this->namespace.'\\'.$name, '\\');
    }

    // Find the namespace and the "use" imports at the top level of the file.
    protected function readNamespaceAndImports(): void
    {
        $depth = 0;
        $count = $this->count();

        for ($i = 0; $i < $count; $i++) {
            if ($this->is($i, '{') || $this->id($i) === T_CURLY_OPEN || $this->id($i) === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($this->is($i, '}')) {
                $depth--;
            } elseif ($this->id($i) === T_NAMESPACE && $this->isName($i + 1)) {
                $this->namespace = ltrim($this->text($i + 1), '\\');
            } elseif ($this->id($i) === T_USE && $depth === 0 && ! $this->is($i + 1, '(')) {
                $i = $this->readImport($i + 1);
            }
        }
    }

    // Read one "use" statement, including "as" aliases and group imports. Returns the index of its ";".
    protected function readImport(int $index): int
    {
        if (in_array($this->id($index), [T_FUNCTION, T_CONST], true)) {
            return $this->skipTo($index, ';');
        }

        $prefix = '';
        $count = $this->count();

        for ($i = $index; $i < $count; $i++) {
            if ($this->is($i, ';')) {
                return $i;
            }

            if ($this->isName($i)) {
                $name = ltrim($this->text($i), '\\');

                if ($this->id($i + 1) === T_NS_SEPARATOR && $this->is($i + 2, '{')) {
                    $prefix = $name.'\\';
                    $i += 2;

                    continue;
                }

                $full = $prefix.$name;
                $alias = substr($full, (int) strrpos('\\'.$full, '\\'));

                if ($this->id($i + 1) === T_AS && $this->id($i + 2) === T_STRING) {
                    $alias = $this->text($i + 2);
                    $i += 2;
                }

                $this->imports[strtolower($alias)] = $full;
            }
        }

        return $count;
    }

    protected function skipTo(int $index, string $char): int
    {
        $count = $this->count();

        for ($i = $index; $i < $count; $i++) {
            if ($this->is($i, $char)) {
                return $i;
            }
        }

        return $count;
    }
}

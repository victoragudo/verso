<?php

namespace App\Verso\Core\Support;

use InvalidArgumentException;
use Stringable;

// A semantic version like 1.4.2 or 2.0.0-beta.1, with compare helpers.
final readonly class Version implements Stringable
{
    private const PATTERN = '/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/';

    /**
     * @param  list<string>  $preRelease
     */
    private function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public array $preRelease,
    ) {}

    // Read a version. A leading "v" (like in git tags) and build data are allowed.
    public static function parse(string $value): self
    {
        return self::tryParse($value) ?? throw new InvalidArgumentException("\"{$value}\" is not a valid version.");
    }

    public static function tryParse(string $value): ?self
    {
        if (preg_match(self::PATTERN, trim($value), $matches) !== 1) {
            return null;
        }

        $preRelease = isset($matches[4]) ? explode('.', $matches[4]) : [];

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3], $preRelease);
    }

    public static function isValid(string $value): bool
    {
        return self::tryParse($value) !== null;
    }

    // Compare two version strings: -1, 0 or 1.
    public static function compareStrings(string $a, string $b): int
    {
        return self::parse($a)->compare(self::parse($b));
    }

    // -1 when this version is older, 0 when equal, 1 when newer. Build data is ignored.
    public function compare(self $other): int
    {
        $core = [$this->major, $this->minor, $this->patch] <=> [$other->major, $other->minor, $other->patch];

        if ($core !== 0) {
            return $core;
        }

        return $this->comparePreRelease($other);
    }

    public function isNewerThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function isOlderThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    // Beta, alpha and release candidate versions have a pre-release part.
    public function isPreRelease(): bool
    {
        return $this->preRelease !== [];
    }

    // True when both versions have the same major number.
    public function isSameMajor(self $other): bool
    {
        return $this->major === $other->major;
    }

    // True when both versions have the same major and minor numbers.
    public function isSameMinor(self $other): bool
    {
        return $this->major === $other->major && $this->minor === $other->minor;
    }

    public function __toString(): string
    {
        $core = "{$this->major}.{$this->minor}.{$this->patch}";

        return $this->preRelease === [] ? $core : $core.'-'.implode('.', $this->preRelease);
    }

    // Semver rules: a version without a pre-release part is newer; parts are compared one by one.
    private function comparePreRelease(self $other): int
    {
        if ($this->preRelease === [] || $other->preRelease === []) {
            return ($other->preRelease === [] ? 0 : 1) - ($this->preRelease === [] ? 0 : 1);
        }

        $count = max(count($this->preRelease), count($other->preRelease));

        for ($index = 0; $index < $count; $index++) {
            $left = $this->preRelease[$index] ?? null;
            $right = $other->preRelease[$index] ?? null;

            if ($left === null || $right === null) {
                return $left === null ? -1 : 1;
            }

            $result = $this->compareIdentifier($left, $right);

            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }

    // Numbers compare as numbers and are older than words; words compare as text.
    private function compareIdentifier(string $left, string $right): int
    {
        $leftNumeric = ctype_digit($left);
        $rightNumeric = ctype_digit($right);

        if ($leftNumeric && $rightNumeric) {
            return (int) $left <=> (int) $right;
        }

        if ($leftNumeric !== $rightNumeric) {
            return $leftNumeric ? -1 : 1;
        }

        return strcmp($left, $right) <=> 0;
    }
}

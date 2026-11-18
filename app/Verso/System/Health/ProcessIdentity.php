<?php

namespace App\Verso\System\Health;

// The system user and group PHP runs as.
final readonly class ProcessIdentity
{
    public function __construct(
        public ?int $uid,
        public ?int $gid,
        public string $user,
        public string $group,
    ) {}

    public static function current(): self
    {
        if (! function_exists('posix_geteuid')) {
            return new self(null, null, (string) get_current_user(), '');
        }

        $uid = posix_geteuid();
        $gid = posix_getegid();
        $user = posix_getpwuid($uid);
        $group = posix_getgrgid($gid);

        return new self($uid, $gid, is_array($user) ? (string) $user['name'] : (string) $uid, is_array($group) ? (string) $group['name'] : (string) $gid);
    }

    // True when this process owns the path, so it may chmod it.
    public function owns(string $path): bool
    {
        $owner = @fileowner($path);

        return $this->uid !== null && $owner !== false && $owner === $this->uid;
    }

    // "www-data:www-data", for shell commands.
    public function chownTarget(): string
    {
        return $this->group === '' ? $this->user : $this->user.':'.$this->group;
    }
}

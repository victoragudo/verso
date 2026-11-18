<?php

namespace App\Verso\Easy\Help;

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use InvalidArgumentException;

// One topic of the help panel: a title, a short list of steps, a drawing and the admin pages it belongs to.
final readonly class HelpTopic
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';

    /**
     * @param  list<string>  $steps  short steps in plain words, in order
     * @param  list<string>  $routes  admin route names where this topic comes first, like "admin.site.menus.index"
     * @param  list<string>  $keywords  extra words the search finds, like synonyms
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $summary,
        public array $steps,
        public string $illustration = 'generic',
        public array $routes = [],
        public array $keywords = [],
        public int $order = 100,
        public ?string $video = null,
        public ?Capability $capability = null,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("Help topic keys use a-z, 0-9 and \"-\", [{$key}] does not.");
        }

        if ($steps === []) {
            throw new InvalidArgumentException("Help topic [{$key}] needs at least one step.");
        }
    }

    public function visibleTo(?User $user): bool
    {
        return $user !== null && ($this->capability === null || $user->hasCapability($this->capability));
    }

    /**
     * The data sent to the help panel. A video from config verso.easy.help.videos wins over the topic one.
     *
     * @return array{key: string, title: string, summary: string, steps: list<string>, illustration: string, routes: list<string>, keywords: list<string>, video: string|null}
     */
    public function toArray(): array
    {
        $videos = (array) config('verso.easy.help.videos', []);
        $video = $videos[$this->key] ?? $this->video;

        return [
            'key' => $this->key,
            'title' => $this->title,
            'summary' => $this->summary,
            'steps' => $this->steps,
            'illustration' => $this->illustration,
            'routes' => $this->routes,
            'keywords' => $this->keywords,
            'video' => is_string($video) && $video !== '' ? $video : null,
        ];
    }
}

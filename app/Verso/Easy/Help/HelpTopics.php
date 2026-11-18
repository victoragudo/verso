<?php

namespace App\Verso\Easy\Help;

use App\Models\User;
use App\Verso\Easy\Support\PluginLabels;
use InvalidArgumentException;

// Keeps the topics of the help panel. Core registers its own, plugins and themes can add more.
class HelpTopics
{
    use PluginLabels;

    /** @var array<string, HelpTopic> */
    protected array $topics = [];

    // Adds a topic. One with the same key is replaced.
    public function register(HelpTopic $topic, ?string $plugin = null): void
    {
        $this->topics[$topic->key] = $topic;
        $this->rememberOwner($topic->key, $plugin);
    }

    public function unregister(string $key): void
    {
        unset($this->topics[$key], $this->owners[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->topics[$key]);
    }

    public function get(string $key): HelpTopic
    {
        return $this->topics[$key] ?? throw new InvalidArgumentException("Unknown help topic [{$key}].");
    }

    public function find(string $key): ?HelpTopic
    {
        return $this->topics[$key] ?? null;
    }

    /**
     * Every topic, sorted by order, then by title.
     *
     * @return list<HelpTopic>
     */
    public function all(): array
    {
        $topics = array_values($this->topics);

        usort($topics, fn (HelpTopic $a, HelpTopic $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);

        return $topics;
    }

    /**
     * The topics this person may see, ready for the help panel.
     *
     * @return list<array{key: string, title: string, summary: string, steps: list<string>, illustration: string, routes: list<string>, keywords: list<string>, video: string|null}>
     */
    public function forUser(?User $user): array
    {
        return array_values(array_map(
            fn (HelpTopic $topic): array => [
                ...$topic->toArray(),
                'title' => $this->translated($topic->key, $topic->title),
                'summary' => $this->translated($topic->key, $topic->summary),
                'steps' => array_map(fn (string $step): string => $this->translated($topic->key, $step), $topic->steps),
                'keywords' => $this->translatedKeywords($topic->key, $topic->keywords),
            ],
            array_filter($this->all(), fn (HelpTopic $topic): bool => $topic->visibleTo($user)),
        ));
    }
}

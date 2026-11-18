<?php

namespace App\Verso\Easy\Starters;

// One page (or post) that a starter site creates, made of ready sections.
final readonly class StarterPage
{
    /**
     * @param  list<StarterSection>  $sections
     */
    public function __construct(
        public string $title,
        public string $slug,
        public array $sections,
        public bool $front = false,
        public string $type = 'page',
        public string $excerpt = '',
    ) {}

    /**
     * @return array{title: string, slug: string, type: string, front: bool}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'slug' => $this->slug, 'type' => $this->type, 'front' => $this->front];
    }
}

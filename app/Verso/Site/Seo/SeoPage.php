<?php

namespace App\Verso\Site\Seo;

// Everything the <head> needs for one page: title, meta tags and schema.org data.
final readonly class SeoPage
{
    /**
     * @param  array<string, string>  $openGraph  property => content
     * @param  array<string, string>  $twitter  name => content
     * @param  list<array<string, mixed>>  $schema  schema.org nodes
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $canonical,
        public ?string $robots,
        public array $openGraph,
        public array $twitter,
        public array $schema,
    ) {}

    public function withTitle(string $title): self
    {
        return $this->copy(['title' => $title]);
    }

    public function withDescription(?string $description): self
    {
        return $this->copy(['description' => $description]);
    }

    public function withCanonical(?string $canonical): self
    {
        return $this->copy(['canonical' => $canonical]);
    }

    public function withRobots(?string $robots): self
    {
        return $this->copy(['robots' => $robots]);
    }

    /** @param  array<string, string>  $openGraph */
    public function withOpenGraph(array $openGraph): self
    {
        return $this->copy(['openGraph' => $openGraph]);
    }

    /** @param  array<string, string>  $twitter */
    public function withTwitter(array $twitter): self
    {
        return $this->copy(['twitter' => $twitter]);
    }

    /** @param  list<array<string, mixed>>  $schema */
    public function withSchema(array $schema): self
    {
        return $this->copy(['schema' => $schema]);
    }

    // The schema.org graph as safe JSON for a <script type="application/ld+json"> tag.
    public function jsonLd(): string
    {
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->schema],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT,
        );
    }

    /** @param  array<string, mixed>  $changes */
    protected function copy(array $changes): self
    {
        /** @var array{title: string, description: ?string, canonical: ?string, robots: ?string, openGraph: array<string, string>, twitter: array<string, string>, schema: list<array<string, mixed>>} $values */
        $values = [...get_object_vars($this), ...$changes];

        return new self(...$values);
    }
}

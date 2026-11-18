<?php

namespace App\Verso\Site\Permalinks;

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\TaxonomyRegistry;
use App\Verso\I18n\Translate;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

// Checks new permalink values for broken patterns, reserved paths and clashes with routes, pages and each other.
class PermalinkClashes
{
    // A base is one or more lowercase parts, like "blog" or "shop/products".
    public const BASE_FORMAT = '#^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$#';

    // Characters allowed in a structure outside the tokens.
    protected const PATTERN_FORMAT = '#^/[a-z0-9_./{}-]*$#';

    /**
     * @param  list<string>  $reserved
     */
    public function __construct(
        protected ContentTypeRegistry $types,
        protected TaxonomyRegistry $taxonomies,
        protected Router $router,
        protected array $reserved,
    ) {}

    /**
     * Problems by form field, like "structures.post".
     *
     * @return array<string, string>
     */
    public function check(PermalinkValues $values): array
    {
        $errors = [];
        $taken = [];

        foreach ($this->types->all() as $type) {
            $field = "structures.{$type->name}";
            $pattern = new PermalinkPattern($values->structures[$type->name] ?? PermalinkSettings::defaultPattern($type));
            $error = $this->patternProblem($pattern, $type->hierarchical);

            if ($error === null && ($first = $pattern->firstStaticSegment()) !== null) {
                $error = $this->firstSegmentProblem($first);
            }

            // Two flat types with the same shape cannot be told apart. Hierarchical types (pages) are tried last.
            $shape = $pattern->regex();
            if ($error === null && ! $type->hierarchical && isset($taken[$shape])) {
                $error = Translate::text('This structure is the same as the one of :type.', ['type' => $taken[$shape]]);
            }

            if (! $type->hierarchical) {
                $taken[$shape] ??= Translate::text($type->pluralLabel, plugin: $this->types->pluginOf($type->name));
            }

            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        return $errors + $this->baseProblems($values);
    }

    // A problem with the form of a structure, or null.
    public function patternProblem(PermalinkPattern $pattern, bool $hierarchical): ?string
    {
        if (preg_match(self::PATTERN_FORMAT, $pattern->pattern) !== 1) {
            return __('Use lowercase letters, numbers, dashes, dots, slashes and tokens only.');
        }

        if (($unknown = $pattern->unknownTokens()) !== []) {
            return Translate::text('Unknown token :token. Use :tokens.', [
                'token' => '{'.$unknown[0].'}',
                'tokens' => implode(', ', array_map(fn (string $token): string => '{'.$token.'}', array_keys(PermalinkPattern::TOKENS))),
            ]);
        }

        if (count($pattern->tokens()) !== count(array_unique($pattern->tokens()))) {
            return __('Use each token only once.');
        }

        foreach ($pattern->segments() as $segment) {
            if (substr_count($segment, '{') > 1) {
                return __('Put a slash between two tokens.');
            }
        }

        if ($hierarchical && ! $pattern->has('slug')) {
            return __('Nested entries need the {slug} token.');
        }

        if (! $pattern->has('slug') && ! $pattern->has('id')) {
            return __('The structure needs {slug} or {id}.');
        }

        return null;
    }

    /** @return array<string, string> */
    protected function baseProblems(PermalinkValues $values): array
    {
        $errors = [];
        $seen = [];
        $bases = [];

        foreach ($this->types->all() as $type) {
            if ($type->hasArchive) {
                $bases["archive_bases.{$type->name}"] = trim($values->archiveBases[$type->name] ?? $type->routeBase, '/');
            }
        }

        foreach ($this->taxonomies->all() as $taxonomy) {
            $bases["taxonomy_bases.{$taxonomy->name}"] = trim($values->taxonomyBases[$taxonomy->name] ?? $taxonomy->routeBase, '/');
        }

        foreach ($bases as $field => $base) {
            $isArchive = str_starts_with($field, 'archive_bases.');

            // An empty archive base turns the archive off. Taxonomies always need one.
            if ($base === '' && $isArchive) {
                continue;
            }

            $error = match (true) {
                $base === '' => Translate::text('Enter a base, like "topics".'),
                preg_match(self::BASE_FORMAT, $base) !== 1 => Translate::text('Use lowercase letters, numbers, dashes and slashes only.'),
                isset($seen[$base]) => Translate::text('Another archive or taxonomy already uses /:base.', ['base' => $base]),
                default => $this->firstSegmentProblem(explode('/', $base)[0]) ?? $this->pageProblem($base),
            };

            $seen[$base] = true;

            if ($error !== null) {
                $errors[$field] = $error;
            }
        }

        return $errors;
    }

    // Reserved words and paths that other routes already serve.
    protected function firstSegmentProblem(string $segment): ?string
    {
        if (in_array($segment, $this->reserved, true) || in_array($segment, $this->routedSegments(), true)) {
            return Translate::text('/:segment is used by Verso or a plugin. Pick another word.', ['segment' => $segment]);
        }

        return null;
    }

    // A published top level page with the same slug would be hidden by the base.
    protected function pageProblem(string $base): ?string
    {
        $types = array_values(array_map(fn ($type): string => $type->name, array_filter($this->types->all(), fn ($type): bool => $type->hierarchical)));
        $title = $types === [] ? null : Entry::query()->whereIn('type', $types)->whereNull('parent_id')->where('slug', $base)->value('title');

        return is_string($title) ? Translate::text('The page ":title" already uses /:base.', ['title' => $title, 'base' => $base]) : null;
    }

    /** @return list<string> first parts of fixed route paths, without the public catch-all and home */
    protected function routedSegments(): array
    {
        $segments = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $first = explode('/', trim($route->uri(), '/'))[0];

            if ($first !== '' && ! str_contains($first, '{') && ! str_starts_with((string) $route->getName(), 'theme.')) {
                $segments[$first] = true;
            }
        }

        return array_keys($segments);
    }
}

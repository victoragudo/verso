<?php

namespace App\Verso\Shortcodes;

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Editor\Contracts\InlineExpander;
use App\Verso\I18n\Translate;
use App\Verso\Plugins\ContentRenderer;
use App\Verso\Shortcodes\Enums\ShortcodePlace;
use App\Verso\Shortcodes\Parsing\ShortcodeLimits;
use App\Verso\Shortcodes\Parsing\ShortcodeNode;
use App\Verso\Shortcodes\Parsing\ShortcodeParser;
use App\Verso\Shortcodes\Parsing\TextNode;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

// The one place that turns shortcodes into HTML, for every text place.
// The text must already be safe HTML. Shortcodes become the trusted HTML of their block.
// Broken shortcodes stay as the original text. Admins also see a small warning.
class ShortcodeExpander implements InlineExpander
{
    // How many enclosing shortcodes are being rendered right now. Inner text is parsed again by its paragraph.
    protected int $depth = 0;

    public function __construct(
        protected ShortcodeRegistry $registry,
        protected Container $container,
    ) {}

    public function expand(string $html, ShortcodeContext $context = new ShortcodeContext): string
    {
        return implode('', array_column($this->segmentsFor($html, $context), 'html'));
    }

    public function segments(string $html): array
    {
        return $this->segmentsFor($html, new ShortcodeContext);
    }

    /**
     * @return list<array{html: string, block: bool}>
     */
    public function segmentsFor(string $html, ShortcodeContext $context): array
    {
        if (! $this->active($context) || ! str_contains($html, '[')) {
            return [['html' => $html, 'block' => false]];
        }

        $segments = [];

        foreach ($this->parser()->parse($html, $this->registry->enclosing(...)) as $node) {
            $segments[] = $node instanceof TextNode
                ? ['html' => $node->text, 'block' => false]
                : $this->renderNode($node, $context);
        }

        return $segments;
    }

    /**
     * Renders one embeddable by name, for the Blade component. Values are plain text (not HTML).
     *
     * @param  array<string, mixed>  $attrs
     */
    public function render(string $name, array $attrs, ShortcodeContext $context = new ShortcodeContext(ShortcodePlace::Blade)): string
    {
        $definition = $this->registry->find($name);

        if ($definition === null) {
            return $this->warning(Translate::text('Unknown embed ":name".', ['name' => $name]), '', $context);
        }

        $result = $definition->validate($attrs);

        if ($result['errors'] !== []) {
            return $this->warning(implode(' ', $result['errors']), '', $context);
        }

        return $this->renderBlock($definition, $result['attrs'], '', $context);
    }

    // Shortcodes in a text with no HTML, like an excerpt. The text is escaped first.
    // Quotes stay as they are (safe in element text), so shortcode values can use them.
    public function expandText(string $text, ShortcodeContext $context): string
    {
        return $this->expand(htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'), $context);
    }

    // Used by the @shortcodes Blade directive: plain text and a place name like "excerpt" or "footer".
    public function expandPlace(?string $text, string $place = 'excerpt', ?Entry $entry = null): HtmlString
    {
        return new HtmlString($this->expandText((string) $text, ShortcodeContext::for(ShortcodePlace::tryFrom($place) ?? ShortcodePlace::Excerpt, $entry)));
    }

    public function names(string $html): array
    {
        return str_contains($html, '[') ? array_values(array_unique(array_map(fn (ShortcodeNode $node): string => $node->name, $this->parser()->all($html)))) : [];
    }

    public function parser(): ShortcodeParser
    {
        return new ShortcodeParser(ShortcodeLimits::fromConfig());
    }

    protected function active(ShortcodeContext $context): bool
    {
        return (bool) config('verso.shortcodes.enabled') && $context->place->enabled();
    }

    /**
     * @return array{html: string, block: bool}
     */
    protected function renderNode(ShortcodeNode $node, ShortcodeContext $context): array
    {
        $definition = $this->registry->find($node->name);

        if ($definition === null) {
            return $this->broken($node, Translate::text('Unknown shortcode ":name".', ['name' => $node->name]), $context);
        }

        if (! $node->valid()) {
            return $this->broken($node, (string) $node->error, $context);
        }

        $result = $definition->validate($node->attrs);

        if ($result['errors'] !== []) {
            return $this->broken($node, implode(' ', $result['errors']), $context);
        }

        if ($node->inner !== null && $this->depth >= ShortcodeLimits::fromConfig()->maxDepth) {
            return $this->broken($node, Translate::text('Shortcodes are nested too deeply.'), $context);
        }

        $this->depth++;

        try {
            return ['html' => $this->renderBlock($definition, $result['attrs'], $node->inner ?? '', $context), 'block' => true];
        } finally {
            $this->depth--;
        }
    }

    /**
     * The same path as a block in the entry: plugin render, then the BlockHtmlFilter.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function renderBlock(ShortcodeDefinition $definition, array $attrs, string $inner, ShortcodeContext $context): string
    {
        $block = [
            'id' => 'shortcode-'.Str::lower(Str::random(8)),
            'type' => $definition->name(),
            'attrs' => $attrs,
            'content' => '',
            'children' => $inner === '' ? [] : [['id' => 'shortcode-inner', 'type' => 'paragraph', 'content' => $inner]],
        ];

        return $this->container->make(ContentRenderer::class)->renderBlock($block, $context->entry);
    }

    /**
     * Visitors see the original text. Admins see it with a warning.
     *
     * @return array{html: string, block: bool}
     */
    protected function broken(ShortcodeNode $node, string $message, ShortcodeContext $context): array
    {
        return ['html' => $this->warning($message, $node->raw, $context), 'block' => false];
    }

    protected function warning(string $message, string $original, ShortcodeContext $context): string
    {
        if (! ($context->warnings ?? $this->viewerSeesWarnings())) {
            return $original;
        }

        return $original.'<span class="verso-shortcode-warning" role="note" data-verso-no-cache style="display:inline-block;margin:0 .25em;padding:.1em .5em;border:1px solid #b45309;border-radius:4px;background:#fffbeb;color:#78350f;font:500 .8rem/1.4 system-ui,sans-serif">'
            .e(Translate::text('Shortcode problem (only admins see this): :message', ['message' => $message])).'</span>';
    }

    protected function viewerSeesWarnings(): bool
    {
        $user = Auth::user();
        $capability = Capability::tryFrom((string) config('verso.shortcodes.warning_capability'));

        return $user instanceof User && $capability !== null && $user->hasCapability($capability);
    }
}

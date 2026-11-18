<?php

namespace App\Verso\Importer\Content;

// Turns WordPress post content (Gutenberg or classic) into Verso blocks.
class ContentConverter
{
    public function __construct(
        protected BlockCommentParser $parser,
        protected GutenbergConverter $gutenberg,
        protected ClassicConverter $classic,
    ) {}

    // The seed keeps block ids the same when the same post is imported again.
    /** @return list<array<string, mixed>> */
    public function convert(string $content, string $seed, MediaMap $media): array
    {
        $builder = new BlockBuilder(new BlockIds($seed), $media);

        if (! $this->parser->hasBlocks($content)) {
            return $this->classic->convert($content, $builder);
        }

        return $this->gutenberg->convert($this->parser->parse($content), $builder);
    }
}

<?php

namespace App\Verso\Editor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\Builder\EditorAccess;
use App\Verso\Importer\Content\ContentConverter;
use App\Verso\Importer\Content\MediaMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Turns pasted HTML or Gutenberg markup into blocks for the code view, with the importer converter.
// Nothing is saved here; the blocks are checked again when the page is saved.
class ConvertHtmlController extends Controller
{
    public function __invoke(Request $request, EditorAccess $access, ContentConverter $converter): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->can('viewAny', Entry::class) && $access->enabled('code_editor', $user),
            403,
        );

        $data = $request->validate([
            'html' => ['required', 'string', 'max:'.(int) config('verso.builder.convert_max_length')],
        ]);

        return response()->json([
            'blocks' => $converter->convert($this->splitBlocks((string) $data['html']), Str::random(12), new MediaMap),
        ]);
    }

    // Pasted HTML often has its paragraphs and headings on one line. A blank line after each one
    // lets the converter make one block per element. WordPress block markup is left as it is.
    protected function splitBlocks(string $html): string
    {
        if (str_contains($html, '<!-- wp:')) {
            return $html;
        }

        return (string) preg_replace('/(<\/(?:p|h[1-6]|ul|ol|blockquote|figure|table|pre)>)\s*(?=<)/i', "$1\n\n", $html);
    }
}

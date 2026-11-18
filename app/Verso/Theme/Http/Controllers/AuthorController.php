<?php

namespace App\Verso\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Support\ArchiveContext;
use App\Verso\Theme\Support\EntryListing;
use App\Verso\Theme\Support\ThemeResponse;
use Illuminate\Http\Response;

// The author page: bio, photo, social links and the published posts of one person, like "/author/clara-moreno".
// People without published posts have no page, so the list of users stays private.
class AuthorController extends Controller
{
    public function __invoke(EntryListing $listing, ThemeResponse $theme, ContentTypeRegistry $types, string $author): Response
    {
        $type = (string) config('verso.themes.home_content_type');
        $user = User::query()->where('slug', $author)->with('avatar')->first();
        $posts = Entry::query()->ofType($type)->where('author_id', $user?->id);

        abort_if($user === null || ! (clone $posts)->published()->exists(), 404);

        return $theme->author($user, [
            'entries' => $listing->paginate($posts, ArchiveContext::author($user, $types->find($type))),
        ]);
    }
}

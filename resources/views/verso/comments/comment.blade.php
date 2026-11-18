{{-- One comment and its replies. Dates use the site time zone. --}}
@inject('siteTime', 'App\Verso\Core\Support\SiteTime')
<li id="comment-{{ $comment->id }}" class="verso-comment verso-comment--depth-{{ $depth }}">
    <article class="verso-comment__body">
        <footer class="verso-comment__meta">
            @if ($comment->safeAuthorUrl())
                <a class="verso-comment__author" href="{{ $comment->safeAuthorUrl() }}" rel="nofollow ugc noopener">{{ $comment->author_name }}</a>
            @else
                <span class="verso-comment__author">{{ $comment->author_name }}</span>
            @endif
            @if ($comment->created_at)
                <time class="verso-comment__date" datetime="{{ $siteTime->iso($comment->created_at) }}">{{ $siteTime->local($comment->created_at)->locale(app()->getLocale())->isoFormat('ddd, ll') }}</time>
            @endif
        </footer>
        <div class="verso-comment__content">{!! $comment->bodyHtml() !!}</div>
        @if ($open)
            <a class="verso-comment__reply" href="{{ url()->current() }}?reply_to={{ $comment->id }}#respond">{{ __('Reply') }}</a>
        @endif
    </article>

    @if ($comment->replies->isNotEmpty())
        <ol class="verso-comment__replies">
            @foreach ($comment->replies as $reply)
                @include('verso-comments::comment', ['comment' => $reply, 'depth' => $depth + 1])
            @endforeach
        </ol>
    @endif
</li>

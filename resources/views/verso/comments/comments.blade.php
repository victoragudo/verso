{{-- Comments of one entry: notice, threaded list and form. Themes style the verso-comments classes. --}}
<section id="comments" class="verso-comments">
    <h2 class="verso-comments__title">
        {{ $heading }}
    </h2>

    @if ($notice)
        <p class="verso-comments__notice verso-comments__notice--{{ $notice['status'] }}" role="status">{{ $notice['message'] }}</p>
    @endif

    @if ($comments->isNotEmpty())
        <ol class="verso-comments__list">
            @foreach ($comments as $comment)
                @include('verso-comments::comment', ['comment' => $comment, 'depth' => 1])
            @endforeach
        </ol>
    @endif

    @if ($open)
        <div id="respond" class="verso-comments__respond">
            <h3 class="verso-comments__form-title">
                @if ($replyTo)
                    {{ __('Reply to :name', ['name' => $replyTo->author_name]) }}
                    <a class="verso-comments__cancel" href="{{ url()->current() }}#respond">{{ __('Cancel reply') }}</a>
                @else
                    {{ __('Leave a comment') }}
                @endif
            </h3>

            @php($formErrors = $errors->getBag('comment'))

            @if ($formErrors->has('form'))
                <p class="verso-comments__error" role="alert">{{ $formErrors->first('form') }}</p>
            @endif

            <form id="verso-comment-form" class="verso-comments__form" method="post" action="{{ $action }}" data-token-url="{{ $tokenUrl }}" novalidate>
                @csrf
                <input type="hidden" name="{{ $timerField }}" value="{{ $timerToken }}">
                @if ($replyTo)
                    <input type="hidden" name="parent_id" value="{{ $replyTo->id }}">
                @endif

                <div class="verso-comments__honeypot" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                    <label for="verso-{{ $honeypotField }}">{{ __('Leave this field empty') }}</label>
                    <input type="text" id="verso-{{ $honeypotField }}" name="{{ $honeypotField }}" value="" tabindex="-1" autocomplete="off">
                </div>

                @if ($user)
                    <p class="verso-comments__signed-in">{{ __('Signed in as :name.', ['name' => $user->name]) }}</p>
                @else
                    <p class="verso-comments__field">
                        <label for="verso-comment-name">{{ __('Name') }}</label>
                        <input type="text" id="verso-comment-name" name="author_name" value="{{ old('author_name') }}" required maxlength="255" autocomplete="name" @if ($formErrors->has('author_name')) aria-invalid="true" aria-describedby="verso-comment-name-error" @endif>
                        @if ($formErrors->has('author_name'))<span class="verso-comments__error" id="verso-comment-name-error">{{ $formErrors->first('author_name') }}</span>@endif
                    </p>
                    <p class="verso-comments__field">
                        <label for="verso-comment-email">{{ __('Email') }} <small>{{ __('(not shown)') }}</small></label>
                        <input type="email" id="verso-comment-email" name="author_email" value="{{ old('author_email') }}" required maxlength="255" autocomplete="email" @if ($formErrors->has('author_email')) aria-invalid="true" aria-describedby="verso-comment-email-error" @endif>
                        @if ($formErrors->has('author_email'))<span class="verso-comments__error" id="verso-comment-email-error">{{ $formErrors->first('author_email') }}</span>@endif
                    </p>
                    <p class="verso-comments__field">
                        <label for="verso-comment-url">{{ __('Website') }} <small>{{ __('(optional)') }}</small></label>
                        <input type="url" id="verso-comment-url" name="author_url" value="{{ old('author_url') }}" maxlength="255" autocomplete="url" @if ($formErrors->has('author_url')) aria-invalid="true" aria-describedby="verso-comment-url-error" @endif>
                        @if ($formErrors->has('author_url'))<span class="verso-comments__error" id="verso-comment-url-error">{{ $formErrors->first('author_url') }}</span>@endif
                    </p>
                @endif

                <p class="verso-comments__field">
                    <label for="verso-comment-body">{{ __('Comment') }}</label>
                    <textarea id="verso-comment-body" name="body" rows="6" required maxlength="{{ $maxLength }}" @if ($formErrors->has('body')) aria-invalid="true" aria-describedby="verso-comment-body-error" @endif>{{ old('body') }}</textarea>
                    @if ($formErrors->has('body'))<span class="verso-comments__error" id="verso-comment-body-error">{{ $formErrors->first('body') }}</span>@endif
                </p>

                <p class="verso-comments__submit">
                    <button type="submit">{{ __('Post comment') }}</button>
                </p>
            </form>
            {{-- Cached pages hold a stale CSRF token, so this script asks for a fresh one just before sending; CSRF stays fully on. --}}
            <script>
                (function () {
                    var form = document.getElementById('verso-comment-form');
                    if (!form || !window.fetch) { return; }
                    form.addEventListener('submit', function (event) {
                        if (form.getAttribute('data-token-ready')) { return; }
                        event.preventDefault();
                        fetch(form.getAttribute('data-token-url'), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
                            .then(function (response) { return response.ok ? response.json() : null; })
                            .then(function (data) {
                                var input = form.querySelector('input[name="_token"]');
                                if (data && data.token && input) { input.value = data.token; }
                            })
                            .catch(function () {})
                            .then(function () {
                                form.setAttribute('data-token-ready', '1');
                                form.submit();
                            });
                    });
                })();
            </script>
        </div>
    @else
        <p class="verso-comments__closed">{{ __('Comments are closed.') }}</p>
    @endif
</section>

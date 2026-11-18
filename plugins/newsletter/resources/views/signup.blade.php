{{-- The signup block. The flash message shows after a signup. --}}
<form class="verso-newsletter" id="{{ $formId }}" method="post" action="{{ route('newsletter.subscribe') }}">
    @csrf
    @if ($title !== '')
        <{{ $headingTag }} class="verso-newsletter__title">{{ $title }}</{{ $headingTag }}>
    @endif
    @if ($text !== '')
        <p class="verso-newsletter__text">{{ $text }}</p>
    @endif
    @if (session('newsletter'))
        <p class="verso-newsletter__notice" role="status">{{ session('newsletter') }}</p>
    @endif
    @if ($list !== '')
        <input type="hidden" name="list" value="{{ $list }}">
    @endif
    <p class="verso-newsletter__trap" aria-hidden="true" style="position:absolute;left:-9999px">
        <label>{{ __('newsletter::Leave this empty') }} <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </p>
    <p class="verso-newsletter__fields">
        @if ($askName)
            <label>{{ __('newsletter::Name') }} <input type="text" name="name" autocomplete="name" maxlength="120"></label>
        @endif
        <label>{{ __('newsletter::Email') }} <input type="email" name="email" autocomplete="email" required maxlength="190"></label>
        <button type="submit">{{ $button }}</button>
    </p>
</form>

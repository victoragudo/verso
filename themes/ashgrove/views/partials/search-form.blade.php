{{-- Search box. Works without JavaScript. --}}
<form class="search-form" role="search" method="get" action="{{ route('theme.search') }}">
    <label class="visually-hidden" for="{{ $inputId }}">Search the site</label>
    <input id="{{ $inputId }}" type="search" name="q" value="{{ $search }}" placeholder="Search posts and pages" required minlength="{{ config('verso.themes.search_min_length') }}" maxlength="{{ config('verso.themes.search_max_length') }}">
    <button type="submit">Search</button>
</form>

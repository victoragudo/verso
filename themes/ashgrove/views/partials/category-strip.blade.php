{{-- Links to the biggest categories, with the number of posts in each. --}}
@inject('blog', 'App\Verso\Theme\Support\BlogQueries')
@php($stripTerms = $blog->popularTerms('category', (int) config('verso.themes.category_strip')))
@if ($stripTerms->isNotEmpty())
    <nav class="category-strip" aria-label="{{ __('ashgrove::Categories') }}">
        <ul role="list">
            @foreach ($stripTerms as $stripTerm)
                <li><a href="{{ $stripTerm->url() }}" @if (isset($term) && $term->is($stripTerm)) aria-current="page" @endif>{{ $stripTerm->name }} <span class="count">{{ $stripTerm->entries_count }}</span></a></li>
            @endforeach
        </ul>
    </nav>
@endif

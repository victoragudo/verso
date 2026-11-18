@foreach ($entries as $entry)
    <article class="card">
        <h2><a href="{{ $entry->url() }}">{{ $entry->title }}</a></h2>
        @if ($entry->excerpt)
            <p>{{ $entry->excerpt }}</p>
        @endif
    </article>
@endforeach

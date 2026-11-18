{{-- A paginated list of entries, or a short message when it is empty. Only the first image loads right away. --}}
@if ($entries->isEmpty())
    <p class="empty">{{ $empty ?? 'Nothing has been published here yet.' }}</p>
@else
    <ol class="entry-list" role="list">
        @foreach ($entries as $entry)
            @include('theme::partials.entry-card', ['entry' => $entry, 'eager' => $loop->first])
        @endforeach
    </ol>
    @include('theme::partials.pagination', ['paginator' => $entries])
@endif

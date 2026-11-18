{{-- A grid of posts with pagination, or a short message when it is empty. Only the first image loads right away. --}}
@if ($entries->isEmpty())
    <p class="empty">{{ $empty ?? 'Nothing has been published here yet.' }}</p>
@else
    @php($entries->getCollection()->loadMissing('terms'))
    <ol class="post-grid" role="list">
        @foreach ($entries as $entry)
            <li>@include('theme::partials.post-card', ['entry' => $entry, 'eager' => $loop->first && ($eagerFirst ?? true)])</li>
        @endforeach
    </ol>
    @include('theme::partials.pagination', ['paginator' => $entries])
@endif

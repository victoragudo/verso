{{-- Home page: the newest post as a large lead story, the category strip, then a grid of recent posts. --}}
@extends('theme::layouts.app')

@section('content')
    <h1 class="visually-hidden">{{ $site['name'] }}</h1>

    @if ($entries->onFirstPage() && $entries->isNotEmpty())
        @php($entries->getCollection()->loadMissing('terms'))
        @php($lead = $entries->getCollection()->shift())
        <section class="lead-story wrap" aria-label="Latest post">
            @include('theme::partials.post-card', ['entry' => $lead, 'variant' => 'lead', 'eager' => true])
        </section>
    @endif

    <div class="wrap">
        @include('theme::partials.category-strip')
    </div>

    @if (! isset($lead) || $entries->isNotEmpty() || $entries->hasPages())
        <section class="wrap recent" aria-labelledby="recent-title">
            <h2 id="recent-title" class="section-title">{{ $entries->onFirstPage() ? 'Recent posts' : 'More posts' }}</h2>
            @include('theme::partials.post-grid', ['entries' => $entries, 'eagerFirst' => ! isset($lead)])
        </section>
    @endif
@endsection

{{-- Search form and results. --}}
@extends('theme::layouts.app')

@section('title', $searching ? __('default::Search results for “:search”', ['search' => $search]) : __('default::Search'))

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <header class="page-header">
        <h1 class="page-title">
            @if ($searching)
                {{ __('default::Results for “:search”', ['search' => $search]) }}
            @else
                {{ __('default::Search') }}
            @endif
        </h1>
        @if ($searching)
            <p class="page-intro" role="status">
                @php($resultCount = App\Verso\I18n\Translate::icu('default::{count, plural, =0 {No entries found.} one {One entry found.} other {# entries found.}}', ['count' => $entries->total()]))
                {{ $resultCount }}
            </p>
        @endif
    </header>

    @include('theme::partials.search-form', ['search' => $search, 'inputId' => 'page-search'])

    @if ($searching && $entries->isNotEmpty())
        <div class="results">
            @include('theme::partials.entry-list', ['entries' => $entries])
        </div>
    @endif
@endsection

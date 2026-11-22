{{-- The page of one author: photo, bio, social links and their posts. --}}
@extends('theme::layouts.app')

@section('title', $author->name)
@section('description', $author->bio ? Str::limit($author->bio, 155) : __('ashgrove::Posts by :name', ['name' => $author->name]))

@section('content')
    @php($avatar = $author->avatar)
    <header @class(['page-header wrap author-header', 'has-avatar' => $avatar && $avatar->isResizable()])>
        @if ($avatar && $avatar->isResizable())
            <img class="author-avatar author-avatar--large" src="{{ $avatar->imageUrl(['w' => 240, 'h' => 240, 'fit' => 'crop']) }}" alt="" width="120" height="120" decoding="async">
        @endif
        <div>
            <p class="page-kicker">{{ __('ashgrove::Author') }}</p>
            <h1 class="page-title">{{ $author->name }}</h1>
            @if ($author->bio)
                <p class="page-intro">{{ $author->bio }}</p>
            @endif
            @include('theme::partials.author-links', ['author' => $author])
        </div>
    </header>

    <div class="wrap">
        @include('theme::partials.post-grid', ['entries' => $entries, 'empty' => __('ashgrove::No posts by :name yet.', ['name' => $author->name])])
    </div>
@endsection

{{-- The page of one author: photo, bio, social links and their posts. --}}
@extends('theme::layouts.app')

@section('title', $author->name)
@section('description', $author->bio ? Str::limit($author->bio, 155) : __('default::Posts by :name', ['name' => $author->name]))

@section('content')
    @php($avatar = $author->avatar)
    <header @class(['page-header author-header', 'has-avatar' => $avatar && $avatar->isResizable()])>
        @if ($avatar && $avatar->isResizable())
            <img class="author-avatar" src="{{ $avatar->imageUrl(['w' => 192, 'h' => 192, 'fit' => 'crop']) }}" alt="" width="96" height="96" decoding="async">
        @endif
        <div>
            <p class="eyebrow">{{ __('default::Author') }}</p>
            <h1 class="page-title">{{ $author->name }}</h1>
            @if ($author->bio)
                <p class="page-intro">{{ $author->bio }}</p>
            @endif
            @include('theme::partials.author-links', ['author' => $author])
        </div>
    </header>

    @include('theme::partials.entry-list', ['entries' => $entries])
@endsection

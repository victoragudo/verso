{{-- SEO tags printed by <x-verso-seo />. --}}
<title>{{ $seo->title }}</title>
@if ($seo->description !== null)
<meta name="description" content="{{ $seo->description }}">
@endif
@if ($seo->robots !== null)
<meta name="robots" content="{{ $seo->robots }}">
@endif
@if ($seo->canonical !== null)
<link rel="canonical" href="{{ $seo->canonical }}">
@endif
@foreach ($verification ?? [] as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach ($seo->openGraph as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@foreach ($seo->twitter as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
<script type="application/ld+json">
{!! $seo->jsonLd() !!}
</script>

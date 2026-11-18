{{-- The featured image of an entry, with resized versions in a srcset. --}}
<img
    {{ $attributes->merge(['class' => 'verso-featured-image']) }}
    src="{{ $image['src'] }}"
    @if ($image['srcset'] !== '') srcset="{{ $image['srcset'] }}" sizes="{{ $image['sizes'] }}" @endif
    alt="{{ $alt }}"
    @if ($image['width']) width="{{ $image['width'] }}" @endif
    @if ($image['height']) height="{{ $image['height'] }}" @endif
    loading="{{ $eager ? 'eager' : 'lazy' }}"
    @if ($eager) fetchpriority="high" @endif
    decoding="async"
>

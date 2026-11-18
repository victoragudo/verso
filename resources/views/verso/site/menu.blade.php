{{-- Menu printed by <x-verso-menu location="..." />. Extra attributes go on the <nav> tag. --}}
<nav {{ $attributes->merge(['class' => 'verso-menu verso-menu--'.$location]) }} aria-label="{{ $label !== '' ? $label : $location }}">
    @include('verso-site::menu-items', ['links' => $links, 'depth' => 0])
</nav>

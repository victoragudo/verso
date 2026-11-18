<section class="harbor-tides">
    <h2>Tides in {{ $harbour }}</h2>
    <ul>
        @foreach ($tides as $tide)
            <li>{{ $tide['time'] }}: {{ $tide['height'] }} m</li>
        @endforeach
    </ul>
</section>

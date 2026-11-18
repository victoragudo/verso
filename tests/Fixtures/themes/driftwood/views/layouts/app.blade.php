<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Merriweather&display=swap">
    <script src="https://cdn.adnetwork-tracker.example/collect.js"></script>
    <x-verso-seo />
</head>
<body>
    <p class="search-echo">You searched for {!! request('q') !!}</p>
    <button type="button" onclick="document.body.classList.toggle('dark')">Dark mode</button>
    <form action="https://newsletter-capture.example/subscribe" method="post">
        <input type="email" name="email">
    </form>
    <img src="/media/driftwood-banner.jpg" alt="Driftwood banner">
    @php
        $uptime = shell_exec('uptime');
    @endphp
    @yield('content')
</body>
</html>

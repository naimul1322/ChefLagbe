<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Find professional chefs for home cooking and events in Bangladesh.">
    <title>{{ $title ?? 'ChefLagbe' }} | ChefLagbe</title>
    <link rel="icon" href="/images/logo.png">
    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="/css/{{ in_array($page, ['home', 'about', 'services', 'chefs', 'contact', 'login', 'signup']) ? $page : 'home' }}.css">
    <link rel="stylesheet" href="/css/shared.css">
</head>
<body>
    @include('header')

    <main id="main" tabindex="-1">
        @include('pages.' . $page)
    </main>

    @include('footer')

    <dialog id="modal" class="modal">
        <div id="modal-content"></div>
        <button class="btn btn-secondary" id="modal-close" type="button">Close</button>
    </dialog>
    <div id="toast" class="toast" role="status" hidden></div>

    <script>
        window.CHEFLAGBE = {
            mode: 'api',
            page: @json($page),
            params: @json($params ?? [])
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js" defer></script>
    <script src="/js/app.js" defer></script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Laravel App' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">

    {{-- Navbar --}}
    @include('partials.navbar')

    {{-- Content --}}
    <main class="max-w-6xl mx-auto px-4 py-8">
        @yield('content')
    </main>

</body>
</html>

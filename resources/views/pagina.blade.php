<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Backups · {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
    <main class="max-w-6xl mx-auto px-4 py-10">
        <h1 class="text-2xl font-bold mb-6">Backups</h1>
        @include('backups::panel')
    </main>
</body>
</html>

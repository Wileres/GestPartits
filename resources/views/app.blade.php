<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }}</title>
    @viteReactRefresh
    @vite('resources/frontend/src/main.tsx')
</head>
<body class="bg-background text-foreground antialiased">
    <div id="root"></div>
    <noscript>Cal activar JavaScript per utilitzar GestPartits.</noscript>
</body>
</html>

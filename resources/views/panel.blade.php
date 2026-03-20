<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @inertiaHead
    @php($flashboardStyles = app(\Pepperfm\Flashboard\UI\Assets\PublishedAssetManager::class)->styles())
    @php($flashboardScript = app(\Pepperfm\Flashboard\UI\Assets\PublishedAssetManager::class)->script())
    @foreach ($flashboardStyles as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    @if ($flashboardScript)
        <script type="module" src="{{ $flashboardScript }}"></script>
    @endif
</head>
<body>
    @inertia
</body>
</html>

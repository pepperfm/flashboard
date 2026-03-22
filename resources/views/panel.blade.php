<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            color-scheme: light;
            font-family: "Inter", "Segoe UI", sans-serif;
            --ui-radius: 0.25rem;
            background: rgb(250 250 250);
        }

        html.dark {
            color-scheme: dark;
            background: rgb(10 10 11);
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: inherit;
            color: rgb(24 24 27);
        }

        html.dark body {
            color: rgb(244 244 245);
        }

        #app,
        [data-page] {
            min-height: 100vh;
        }
    </style>
    <script>
        (() => {
            const themeStorageKey = 'flashboard-theme'
            const radiusStorageKey = 'flashboard-theme-radius'
            const root = document.documentElement
            const storedMode = window.localStorage.getItem(themeStorageKey)
            const mode = storedMode === 'light' || storedMode === 'dark' || storedMode === 'system'
                ? storedMode
                : 'system'
            const shouldUseDark = mode === 'dark'
                || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
            const storedRadius = window.localStorage.getItem(radiusStorageKey)
            const parsedRadius = storedRadius === null ? 0.25 : Number(storedRadius)
            const radius = Number.isFinite(parsedRadius) ? parsedRadius : 0.25

            root.classList.toggle('dark', shouldUseDark)
            root.style.colorScheme = shouldUseDark ? 'dark' : 'light'
            root.style.setProperty('--ui-radius', `${radius}rem`)
        })()
    </script>
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

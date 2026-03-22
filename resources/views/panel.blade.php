<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @inertiaHead
    <script>
        (() => {
            const root = document.documentElement
            const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]
            const blackScale = {
                50: '#fafafa',
                100: '#f5f5f5',
                200: '#e5e5e5',
                300: '#d4d4d4',
                400: '#a3a3a3',
                500: '#737373',
                600: '#525252',
                700: '#404040',
                800: '#262626',
                900: '#171717',
                950: '#0a0a0a',
            }

            try {
                const mode = window.localStorage.getItem('flashboard-theme')
                const primary = window.localStorage.getItem('flashboard-theme-primary')
                const neutral = window.localStorage.getItem('flashboard-theme-neutral')
                const radius = window.localStorage.getItem('flashboard-theme-radius')
                const useDark = mode === 'dark'
                    || (mode !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)

                const applyScale = (prefix, color) => {
                    if (!color) {
                        return
                    }

                    for (const shade of shades) {
                        root.style.setProperty(`--ui-color-${prefix}-${shade}`, `var(--color-${color}-${shade})`)
                    }
                }

                const applyBlackPrimaryScale = () => {
                    for (const shade of shades) {
                        root.style.setProperty(`--ui-color-primary-${shade}`, blackScale[shade])
                    }
                }

                if (primary === 'black') {
                    applyBlackPrimaryScale()
                } else {
                    applyScale('primary', primary)
                }

                applyScale('neutral', neutral)

                if (primary) {
                    root.style.setProperty('--ui-primary', `var(--ui-color-primary-${useDark ? 400 : 500})`)
                }

                if (radius !== null && radius !== '') {
                    const parsedRadius = Number(radius)

                    if (!Number.isNaN(parsedRadius)) {
                        root.style.setProperty('--ui-radius', `${parsedRadius}rem`)
                    }
                }

                root.classList.toggle('dark', useDark)
                root.style.colorScheme = useDark ? 'dark' : 'light'
            } catch (_error) {
                // Ignore local theme bootstrap failures and let the client app recover.
            }
        })()
    </script>
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

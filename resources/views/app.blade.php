<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'CG-Net Admin') }}</title>
        <script>
            (function () {
                function hexToHsl(raw) {
                    var hex = (raw || '').replace('#', '');
                    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
                    if (!/^[0-9a-fA-F]{6}$/.test(hex)) return null;
                    var r = parseInt(hex.slice(0, 2), 16) / 255;
                    var g = parseInt(hex.slice(2, 4), 16) / 255;
                    var b = parseInt(hex.slice(4, 6), 16) / 255;
                    var max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, h = 0, s = 0;
                    if (max !== min) {
                        var d = max - min;
                        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                        if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
                        else if (max === g) h = (b - r) / d + 2;
                        else h = (r - g) / d + 4;
                        h /= 6;
                    }
                    return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
                }

                try {
                    if (localStorage.getItem('isp-admin-theme') === 'dark') {
                        document.documentElement.classList.add('dark');
                    }

                    var raw = localStorage.getItem('isp-admin-theme-settings');
                    if (!raw) return;
                    var settings = JSON.parse(raw);
                    var root = document.documentElement;
                    var primary = hexToHsl(settings.primaryColor);
                    if (primary) {
                        var hover = Math.min(primary.l + 4, 96);
                        var darkL = primary.l < 40 ? Math.min(primary.l + 46, 64) : primary.l < 55 ? Math.min(Math.max(primary.l, 52), 68) : Math.max(42, Math.min(primary.l, 62));
                        var fg = primary.l < 55 ? '0 0% 100%' : '222 47% 11%';
                        var darkFg = darkL < 55 ? '0 0% 100%' : '222 47% 11%';
                        root.style.setProperty('--theme-primary', primary.h + ' ' + primary.s + '% ' + primary.l + '%');
                        root.style.setProperty('--theme-primary-hover', primary.h + ' ' + primary.s + '% ' + hover + '%');
                        root.style.setProperty('--theme-primary-foreground', fg);
                        root.style.setProperty('--theme-primary-dark', primary.h + ' ' + primary.s + '% ' + darkL + '%');
                        root.style.setProperty('--theme-primary-dark-hover', primary.h + ' ' + primary.s + '% ' + Math.min(darkL + 4, 90) + '%');
                        root.style.setProperty('--theme-primary-dark-foreground', darkFg);
                    }
                } catch (e) {}
            })();
        </script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-dvh bg-background font-sans antialiased">
        @inertia
    </body>
</html>

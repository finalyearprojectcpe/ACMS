<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta property="og:image" content="{{ config('app.url') }}/ogimage.jpg">
        <meta name="description" content="Grademanager is a unified platform for students, staff, departmental heads, and faculty to manage academic grades, track dues, and streamline payments in a fast, clear and consistent interface.">
        <meta property="og:url" content="{{ config('app.url') }}/">
        <meta property="og:type" content="website">
        <meta property="og:title" content="Grademanager">
        <meta property="og:description" content="Grademanager is a unified platform for students, staff, departmental heads, and faculty to manage academic grades, track dues, and streamline payments in a fast, clear and consistent interface.">
        <meta property="og:image" content="{{ config('app.url') }}/ogimage.jpg">

        <meta name="twitter:card" content="summary_large_image">
        <meta property="twitter:domain" content="orbitsynctraders.com">
        <meta property="twitter:url" content="{{ config('app.url') }}/">
        <meta name="twitter:title" content="Grademanager">
        <meta name="twitter:description" content="Grademanager is a unified platform for students, staff, departmental heads, and faculty to manage academic grades, track dues, and streamline payments in a fast, clear and consistent interface.">
        <meta name="twitter:image" content="{{ config('app.url') }}/ogimage.jpg">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        <script>
            (() => {
                try {
                    const theme = window.localStorage.getItem('grademanager-theme') ?? 'system';
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    document.documentElement.classList.toggle(
                        'dark',
                        theme === 'dark' || (theme === 'system' && prefersDark),
                    );
                } catch {
                    //
                }
            })();
        </script>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'GradeManager') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased background-graph">
        <x-inertia::app />
    </body>
</html>

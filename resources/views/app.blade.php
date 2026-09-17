<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="description" content="GradeManager helps course advisers manage student course registrations, academic records, dues, and payments in one clear workspace.">
        <meta property="og:url" content="{{ request()->url() }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="GradeManager">
        <meta property="og:description" content="GradeManager helps course advisers manage student course registrations, academic records, dues, and payments in one clear workspace.">
        <meta property="og:image" content="{{ url('/ogimage.jpg') }}">

        <meta name="twitter:card" content="summary_large_image">
        <meta property="twitter:domain" content="{{ request()->getHost() }}">
        <meta property="twitter:url" content="{{ request()->url() }}">
        <meta name="twitter:title" content="GradeManager">
        <meta name="twitter:description" content="GradeManager helps course advisers manage student course registrations, academic records, dues, and payments in one clear workspace.">
        <meta name="twitter:image" content="{{ url('/ogimage.jpg') }}">

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

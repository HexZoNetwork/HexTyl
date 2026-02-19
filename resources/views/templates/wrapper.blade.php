<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'Pterodactyl') }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
            <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
            <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
            <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
            <link rel="manifest" href="/favicons/manifest.json">
            <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
            <link rel="shortcut icon" href="/favicons/favicon.ico">
            <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            <meta name="theme-color" content="#06b0d1">
        @show
        <style>
            /* Login Page Logo Fix (Bypass React overlap) */
            div.md\:flex.w-full.bg-white { flex-direction: column !important; align-items: center !important; border-top: 4px solid #06b0d1 !important; border-radius: 8px !important; }
            div.md\:flex.w-full.bg-white > div.flex-none { 
                margin-bottom: 1.5rem !important; 
                margin-top: -4.5rem !important;
                background: #000 !important;
                border: 3px solid #06b0d1 !important;
                padding: 10px !important;
                border-radius: 9999px !important;
                width: 140px !important;
                height: 140px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                overflow: hidden !important;
                box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
                z-index: 10;
            }
            div.md\:flex.w-full.bg-white > div.flex-none img { 
                max-width: 100% !important; 
                height: auto !important; 
                width: auto !important;
                margin: 0 !important;
            }
            /* Hide the redundant Pterodactyl text if any */
            div.md\:flex.w-full.bg-white > div.flex-1 { width: 100% !important; padding: 1rem 2rem 2rem !important; }
        </style>

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif
        @show

        @yield('assets')

        @include('layouts.scripts')
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-50' }}">
        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            {!! $asset->js('main.js') !!}
        @show
    </body>
</html>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'HexTyl') }} — Root Panel — @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#ffd700">

        @include('layouts.scripts')

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
            <style>
                :root {
                    --root-gold: #ffd700;
                    --root-gold-soft: rgba(255, 215, 0, 0.18);
                    --root-surface: #111820;
                    --root-surface-soft: #171f2b;
                    --root-border: #2a2000;
                    --root-text-muted: #9aaa8a;
                }
                /* ── Root Panel — Dark Gold Theme ── */
                body.skin-blue { background: #0a0d10 !important; }
                body.panel-polish::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    pointer-events: none;
                    z-index: 0;
                    background:
                        radial-gradient(900px 420px at -10% -10%, rgba(255, 215, 0, 0.11), transparent 62%),
                        radial-gradient(900px 440px at 110% -15%, rgba(182, 145, 24, 0.12), transparent 64%);
                }
                body.panel-polish .wrapper {
                    position: relative;
                    z-index: 1;
                }
                .skin-blue .main-header .navbar,
                .skin-blue .main-header .navbar .nav>li>a { background-color: #1a1200 !important; border-bottom: 2px solid #ffd700 !important; }
                .skin-blue .main-header .logo { background-color: #2a1e00 !important; border-bottom: 2px solid #ffd700 !important; }
                .skin-blue .main-header .logo:hover { background-color: #3a2900 !important; }
                .skin-blue .main-header { box-shadow: 0 2px 12px rgba(255,215,0,.3); }
                .skin-blue .main-header .navbar .nav>li>a {
                    transition: background-color 170ms ease, box-shadow 170ms ease, color 170ms ease;
                }
                .skin-blue .main-header .navbar .nav>li>a:hover {
                    box-shadow: inset 0 -2px 0 var(--root-gold);
                }
                .main-header .logo .logo-mini img,
                .main-header .logo .logo-lg img { filter: none; }

                /* ── Root Sidebar (dark navy with gold accents) ── */
                .skin-blue .main-sidebar { background-color: #07090d !important; border-right: 1px solid #2a2000; }
                .skin-blue .sidebar-menu>li.header { color: #ffd700 !important; background: #050609 !important; font-size: 10px; letter-spacing: 1.5px; }
                .skin-blue .sidebar-menu>li>a { border-left: 3px solid transparent !important; color: #9aaa8a !important; }
                .skin-blue .sidebar-menu>li>a:hover,
                .skin-blue .sidebar-menu>li.active>a {
                    border-left-color: #ffd700 !important;
                    background: #12100a !important;
                    color: #fff !important;
                }
                .skin-blue .sidebar-menu>li>a {
                    transition: border-color 160ms ease, background-color 160ms ease, color 160ms ease;
                }
                .skin-blue .sidebar-menu>li.active>a { color: #ffd700 !important; }
                .skin-blue .sidebar-menu>li>a>.fa { color: #6a5a30; }
                .skin-blue .sidebar-menu>li.active>a>.fa,
                .skin-blue .sidebar-menu>li>a:hover>.fa { color: #ffd700 !important; }

                /* ── Root user badge ── */
                .root-badge { background: #ffd700; color: #000; font-size: 9px; padding: 2px 5px; border-radius: 3px; font-weight: 700; vertical-align: middle; margin-left: 4px; }

                /* ── Content ── */
                .wrapper, .content-wrapper { background-color: #0d1117 !important; }
                .content-header h1 { color: #ffd700; }
                .content-header h1 small { color: #7a6a20; }
                .breadcrumb>li+li::before { color: #5a5030; }
                .breadcrumb a { color: #ffd700; }

                /* ── Boxes ── */
                .box {
                    border-radius: 8px;
                    box-shadow: 0 9px 24px rgba(0,0,0,.34);
                    background: #111820;
                    border-color: #1e2530;
                    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
                }
                .box:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 14px 30px rgba(0,0,0,.4);
                    border-color: #3b3320;
                }
                .box.box-primary { border-top-color: #ffd700 !important; }
                .box.box-danger { border-top-color: #e74c3c !important; }
                .box-header.with-border { border-bottom-color: #1e2530; }
                .box-title { font-weight: 600; color: #ffd700; }
                .main-footer { border-top: 1px solid #2a2000; color: #6a5a30; background: #0a0c10; }

                /* ── Tables ── */
                .table>thead>tr>th { background: #12100a; color: #ffd700; border-bottom: 2px solid #ffd700 !important; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
                .table-hover tbody tr:hover { background-color: #12100a !important; }
                .table>tbody>tr>td { vertical-align: middle; color: #c5c5a0; border-color: #1e2020; }
                table { background: #111820 !important; }
                .table-hover tbody tr {
                    transition: background-color 150ms ease, transform 150ms ease;
                }
                .table-hover tbody tr:hover {
                    transform: translateX(1px);
                }

                /* ── Buttons ── */
                .btn {
                    border-radius: 7px !important;
                    transition: transform 150ms ease, box-shadow 180ms ease, background-color 150ms ease, border-color 150ms ease;
                }
                .btn:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 8px 16px rgba(0,0,0,.25);
                }
                .btn:focus,
                .btn:focus-visible {
                    box-shadow: 0 0 0 3px var(--root-gold-soft) !important;
                }
                .btn-primary { background-color: #ffd700 !important; border-color: #b09700 !important; color: #000 !important; font-weight: 600; }
                .btn-primary:hover { background-color: #e0bc00 !important; }
                .btn-success { background-color: #00a65a !important; border-color: #008d4c !important; }
                .btn-warning { background-color: #f39c12 !important; border-color: #e08e0b !important; }
                .btn-danger  { background-color: #dd4b39 !important; border-color: #d73925 !important; }
                .btn-default { background: #1e2530; color: #c5c5a0; border-color: #2e3540; }
                .btn-default:hover { border-color: #ffd700; color: #ffd700; }
                .btn-info {
                    color: #16110a !important;
                    font-weight: 600;
                    background: linear-gradient(180deg, #f4cd45 0%, #e7b10c 100%) !important;
                    border-color: #c69300 !important;
                }
                .btn-info:hover {
                    background: linear-gradient(180deg, #f6d454 0%, #ecbc19 100%) !important;
                }

                /* ── Forms ── */
                .form-control { background: #1a1e26; border-color: #2a3040; color: #c5c5a0; }
                .form-control:focus { border-color: #ffd700 !important; box-shadow: 0 0 0 3px rgba(255,215,0,.15) !important; color: #fff; }
                .control-label { font-weight: 600; color: #ffd700; font-size: 12px; }
                .form-control, input, select, textarea {
                    transition: border-color 150ms ease, box-shadow 180ms ease, background-color 150ms ease;
                }
                .content-wrapper {
                    animation: rootFadeIn 240ms ease;
                }
                @keyframes rootFadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(6px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                a:focus-visible,
                button:focus-visible,
                input:focus-visible,
                select:focus-visible,
                textarea:focus-visible {
                    outline: 2px solid rgba(255,215,0,0.82);
                    outline-offset: 1px;
                }

                /* ── Alerts ── */
                .alert-success { background-color: #0d2e1c; border-color: #00a65a; color: #7adf9a; }
                .alert-danger  { background-color: #2e0d0d; border-color: #dd4b39; color: #f0a0a0; }
                .alert-info    { background-color: #0d1e2e; border-color: #ffd700; color: #ffe77a; }
                @media (prefers-reduced-motion: reduce) {
                    * {
                        animation: none !important;
                        transition: none !important;
                    }
                }
            </style>
        @show
    </head>
    <body class="skin-blue sidebar-mini panel-polish">
        <div class="wrapper">
            {{-- Header --}}
            <header class="main-header">
                <a href="{{ route('root.dashboard') }}" class="logo">
                    <span class="logo-mini">
                        <i class="fa fa-star" style="color:#ffd700;"></i>
                    </span>
                    <span class="logo-lg">
                        <strong style="color:#ffd700; letter-spacing:1px;">⭐ ROOT PANEL</strong>
                        <span class="root-badge">ROOT</span>
                    </span>
                </a>
                <nav class="navbar navbar-static-top">
                    <a href="#" class="sidebar-toggle" data-toggle="push-menu"></a>
                    <div class="navbar-custom-menu">
                        <ul class="nav navbar-nav">
                            <li class="user-menu">
                                <a href="{{ route('account') }}">
                                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(Auth::user()->email)) }}?s=160" class="user-image" alt="Root User">
                                    <span class="hidden-xs" style="color:#ffd700;">{{ Auth::user()->name_first }} <span class="root-badge">ROOT</span></span>
                                </a>
                            </li>
                            <li><a href="{{ route('admin.index') }}" data-toggle="tooltip" data-placement="bottom" title="Admin Panel"><i class="fa fa-shield"></i></a></li>
                            <li><a href="{{ route('index') }}" data-toggle="tooltip" data-placement="bottom" title="User Panel"><i class="fa fa-server"></i></a></li>
                            <li><a href="{{ route('auth.logout') }}" data-toggle="tooltip" data-placement="bottom" title="Logout"><i class="fa fa-sign-out"></i></a></li>
                        </ul>
                    </div>
                </nav>
            </header>

            {{-- Sidebar --}}
            <aside class="main-sidebar">
                <section class="sidebar">
                    <div class="user-panel" style="padding:12px 15px; border-bottom:1px solid #2a2000;">
                        <div class="image">
                            <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(Auth::user()->email)) }}?s=160" class="img-circle" style="border:2px solid #ffd700;" alt="Root User">
                        </div>
                        <div class="info" style="padding-left:50px; padding-top:4px;">
                            <p style="color:#ffd700; font-weight:700; margin:0;">{{ Auth::user()->username }}</p>
                            <span class="root-badge" style="font-size:8px;">ROOT ACCESS</span>
                        </div>
                    </div>
                    <ul class="sidebar-menu" data-widget="tree">
                        <li class="header">ROOT PANEL</li>
                        <li class="{{ Route::currentRouteName() === 'root.dashboard' ? 'active' : '' }}">
                            <a href="{{ route('root.dashboard') }}">
                                <i class="fa fa-star"></i> <span>Root Dashboard</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.users') ? 'active' : '' }}">
                            <a href="{{ route('root.users') }}">
                                <i class="fa fa-users"></i> <span>All Users</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.servers') ? 'active' : '' }}">
                            <a href="{{ route('root.servers') }}">
                                <i class="fa fa-server"></i> <span>All Servers</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.nodes') ? 'active' : '' }}">
                            <a href="{{ route('root.nodes') }}">
                                <i class="fa fa-sitemap"></i> <span>All Nodes</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.api') ? 'active' : '' }}">
                            <a href="{{ route('root.api_keys') }}">
                                <i class="fa fa-key"></i> <span>All API Keys</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.security') ? 'active' : '' }}">
                            <a href="{{ route('root.security') }}">
                                <i class="fa fa-shield"></i> <span>Security Control</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.threat_intelligence') ? 'active' : '' }}">
                            <a href="{{ route('root.threat_intelligence') }}">
                                <i class="fa fa-line-chart"></i> <span>Threat Intel</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.audit_timeline') ? 'active' : '' }}">
                            <a href="{{ route('root.audit_timeline') }}">
                                <i class="fa fa-history"></i> <span>Audit Timeline</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'root.health_center') ? 'active' : '' }}">
                            <a href="{{ route('root.health_center') }}">
                                <i class="fa fa-heartbeat"></i> <span>Health Center</span>
                            </a>
                        </li>
                        <li class="{{ Route::currentRouteName() === 'admin.api.root' ? 'active' : '' }}">
                            <a href="{{ route('admin.api.root') }}" style="color:#e05454 !important;">
                                <i class="fa fa-key" style="color:#e05454 !important;"></i>
                                <span>Root API Key <span class="label label-danger" style="font-size:9px;">ptlr_</span></span>
                            </a>
                        </li>
                        <li class="header">ADMIN PANEL</li>
                        <li>
                            <a href="{{ route('admin.index') }}">
                                <i class="fa fa-shield"></i> <span>Go to Admin Panel</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </aside>

            {{-- Content --}}
            <div class="content-wrapper">
                <section class="content-header">
                    @yield('content-header')
                </section>
                <section class="content">
                    <div class="row">
                        <div class="col-xs-12">
                            @if (count($errors) > 0)
                                <div class="alert alert-danger" style="border-left:4px solid #dd4b39;">
                                    <strong><i class="fa fa-times-circle"></i> Validation Error</strong><br><br>
                                    <ul style="margin-bottom:0;">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissable" style="border-left:4px solid #00a65a;">
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                    <i class="fa fa-check-circle"></i> <strong>Success</strong> &mdash; {{ session('success') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    @yield('content')
                </section>
            </div>

            <footer class="main-footer">
                <div class="pull-right small" style="margin-right:10px; color:#5a4a20;">
                    <strong><i class="fa fa-shield" style="color:#ffd700;"></i> ROOT ACCESS ACTIVE</strong>
                </div>
                <span style="color:#5a4a20;">
                    &copy; {{ date('Y') }}
                    <a href="https://pterodactyl.io/" style="color:#ffd700;">Pterodactyl</a> &amp;
                    <strong style="color:#ffd700;">HexZo</strong> &mdash;
                    <i class="fa fa-shield" style="color:#ffd700;"></i>
                    <span style="color:#ffd700;">Protected by HexZo</span>
                </span>
            </footer>
        </div>

        @section('footer-scripts')
            <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
            <script>keyboardeventKeyPolyfill.polyfill();</script>
            {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
            {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
        @show
    </body>
</html>

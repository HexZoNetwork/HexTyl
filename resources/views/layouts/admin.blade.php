<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'Pterodactyl') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="manifest" href="/favicons/manifest.json">
        <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
        <link rel="shortcut icon" href="/favicons/favicon.ico">
        <meta name="msapplication-config" content="/favicons/browserconfig.xml">
        <meta name="theme-color" content="#06b0d1">

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

            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
            <![endif]-->
            <style>
                /* =============================================
                   Midnight Deep Dark — Admin Panel Overhaul
                   ============================================= */
                :root {
                    --admin-accent: #58a6ff;
                    --admin-accent-soft: rgba(88, 166, 255, 0.2);
                    --admin-border: #30363d;
                    --admin-surface: #161b22;
                    --admin-surface-soft: #1a2029;
                    --admin-text-muted: #8b949e;
                }

                /* ── Base Styles ── */
                body, .wrapper, .content-wrapper, .main-footer {
                    background-color: #0d1117 !important;
                    color: #c9d1d9 !important;
                }
                body.panel-polish::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    pointer-events: none;
                    z-index: -1;
                    background:
                        radial-gradient(1100px 460px at -10% -10%, rgba(28, 111, 181, 0.16), transparent 60%),
                        radial-gradient(900px 420px at 105% -5%, rgba(32, 73, 128, 0.16), transparent 62%);
                }
                body.panel-polish .wrapper {
                    position: relative;
                    z-index: auto;
                }
                .modal {
                    z-index: 1060 !important;
                }
                .modal-backdrop {
                    z-index: 1050 !important;
                }

                /* ── Header & Logo ── */
                .skin-blue .main-header .navbar,
                .skin-blue .main-header .navbar .nav>li>a {
                    background-color: #161b22 !important;
                    border-bottom: 1px solid #30363d !important;
                }
                .skin-blue .main-header .logo {
                    background-color: #010409 !important;
                    border-bottom: 1px solid #30363d !important;
                    border-right: 1px solid #30363d !important;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .skin-blue .main-header .logo:hover { background-color: #161b22 !important; }
                .skin-blue .main-header .navbar .sidebar-toggle:hover { background-color: #21262d !important; }
                .skin-blue .main-header .navbar .nav>li>a:hover { background: #21262d !important; }
                .skin-blue .main-header { box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
                .skin-blue .main-header .navbar .nav>li>a {
                    transition: background-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
                }
                .skin-blue .main-header .navbar .nav>li>a:hover {
                    box-shadow: inset 0 -2px 0 var(--admin-accent);
                }

                /* ── Sidebar ── */
                .skin-blue .main-sidebar {
                    background-color: #010409 !important;
                    border-right: 1px solid #30363d !important;
                }
                .skin-blue .sidebar-menu>li.header {
                    color: #8b949e !important;
                    background: #0d1117 !important;
                    font-size: 10px;
                    letter-spacing: 1.5px;
                    padding: 15px 15px 10px;
                }
                .skin-blue .sidebar-menu>li>a {
                    border-left: 3px solid transparent !important;
                    color: #8b949e !important;
                    transition: border-color 160ms ease, background 160ms ease, color 160ms ease;
                }
                .skin-blue .sidebar-menu>li>a:hover,
                .skin-blue .sidebar-menu>li.active>a {
                    border-left-color: #58a6ff !important;
                    background: #161b22 !important;
                    color: #ffffff !important;
                }
                .skin-blue .sidebar-menu>li.active>a { color: #58a6ff !important; }
                .skin-blue .sidebar-menu>li>a>.fa { color: #484f58; }
                .skin-blue .sidebar-menu>li.active>a>.fa,
                .skin-blue .sidebar-menu>li>a:hover>.fa { color: #58a6ff !important; }

                /* ── Content Area ── */
                .content-header h1 { color: #f0f6fc !important; }
                .content-header h1 small { color: #8b949e !important; }
                .breadcrumb { background: transparent !important; }
                .breadcrumb>li+li::before { color: #484f58 !important; }
                .breadcrumb a { color: #58a6ff !important; }

                /* ── Boxes ── */
                .box {
                    background: #161b22 !important;
                    border: 1px solid #30363d !important;
                    border-top: 3px solid #30363d !important;
                    border-radius: 8px;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.22) !important;
                    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
                }
                .box:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 14px 30px rgba(0,0,0,0.32) !important;
                    border-color: #3a4655 !important;
                }
                .box.box-primary { border-top-color: #58a6ff !important; }
                .box.box-success { border-top-color: #238636 !important; }
                .box.box-warning { border-top-color: #d29922 !important; }
                .box.box-danger  { border-top-color: #f85149 !important; }
                .box-header.with-border { border-bottom: 1px solid #30363d !important; }
                .box-title { color: #f0f6fc !important; font-weight: 600; }
                .box-header { color: #f0f6fc !important; }

                /* ── Tables ── */
                .table { color: #c9d1d9 !important; }
                .table>thead>tr>th {
                    background: #21262d !important;
                    color: #f0f6fc !important;
                    border-bottom: 2px solid #30363d !important;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: .5px;
                }
                .table-hover tbody tr:hover { background-color: #161b22 !important; }
                .table>tbody>tr>td { border-top: 1px solid #30363d !important; vertical-align: middle; }
                .table-bordered, .table-bordered>thead>tr>th, .table-bordered>tbody>tr>td { border: 1px solid #30363d !important; }
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
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.22);
                }
                .btn:focus,
                .btn:focus-visible {
                    box-shadow: 0 0 0 3px var(--admin-accent-soft) !important;
                }
                .btn-primary { background-color: #238636 !important; border-color: rgba(240,246,252,0.1) !important; color: #fff !important; }
                .btn-primary:hover { background-color: #2ea043 !important; }
                .btn-success { background-color: #238636 !important; border-color: rgba(240,246,252,0.1) !important; }
                .btn-warning { background-color: #d29922 !important; border-color: rgba(240,246,252,0.1) !important; color: #000 !important; }
                .btn-danger  { background-color: #da3633 !important; border-color: rgba(240,246,252,0.1) !important; }
                .btn-info    { background-color: #1f6feb !important; border-color: rgba(240,246,252,0.1) !important; }
                .btn-default { background-color: #21262d !important; color: #c9d1d9 !important; border-color: #30363d !important; }
                .btn-default:hover { background-color: #30363d !important; color: #f0f6fc !important; }

                /* ── Form Inputs ── */
                .form-control, input, select, textarea {
                    background-color: #0d1117 !important;
                    border: 1px solid #30363d !important;
                    color: #c9d1d9 !important;
                    border-radius: 6px !important;
                    transition: border-color 150ms ease, box-shadow 180ms ease, background-color 150ms ease;
                }
                .form-control:focus {
                    border-color: #58a6ff !important;
                    box-shadow: 0 0 0 3px rgba(88,166,255,0.15) !important;
                }
                select.form-control option {
                    background: #0d1117 !important;
                    color: #c9d1d9 !important;
                }
                .control-label { color: #f0f6fc !important; font-weight: 600; }
                .input-group-addon { background-color: #21262d !important; border-color: #30363d !important; color: #8b949e !important; }

                /* ── Selects (Select2) ── */
                .select2-container--default .select2-selection--single { background-color: #0d1117 !important; border-color: #30363d !important; }
                .select2-container--default .select2-selection--single .select2-selection__rendered { color: #c9d1d9 !important; }
                .select2-dropdown { background-color: #0d1117 !important; border: 1px solid #30363d !important; }
                .select2-results__option { color: #8b949e !important; }
                .select2-container--default .select2-results__option--highlighted { background-color: #1f6feb !important; color: #fff !important; }

                /* ── Alerts ── */
                .alert { border-radius: 6px !important; border-left-width: 5px !important; }
                .alert-info { background-color: #161b22 !important; border-color: #1f6feb !important; color: #58a6ff !important; }
                .alert-success { background-color: #161b22 !important; border-color: #238636 !important; color: #3fb950 !important; }
                .alert-danger { background-color: #161b22 !important; border-color: #f85149 !important; color: #f85149 !important; }
                .alert-warning { background-color: #161b22 !important; border-color: #d29922 !important; color: #e3b341 !important; }

                /* ── Pagination ── */
                .pagination>li>a, .pagination>li>span { background-color: #161b22 !important; border-color: #30363d !important; color: #58a6ff !important; }
                .pagination>.active>a, .pagination>.active>span { background-color: #1f6feb !important; border-color: #1f6feb !important; color: #fff !important; }
                .pagination>li>a:hover { background-color: #21262d !important; }

                /* ── Nav Tabs ── */
                .nav-tabs-custom { background: #161b22 !important; border: 1px solid #30363d !important; border-radius: 6px; }
                .nav-tabs-custom>.nav-tabs>li>a { color: #8b949e !important; }
                .nav-tabs-custom>.nav-tabs>li.active { border-top-color: #58a6ff !important; }
                .nav-tabs-custom>.nav-tabs>li.active>a { background: #161b22 !important; color: #fff !important; }
                .nav-tabs-custom>.tab-content { background: transparent !important; color: #c9d1d9 !important; }

                /* ── Footer ── */
                .main-footer { border-top: 1px solid #30363d !important; color: #8b949e !important; background: #010409 !important; }
                .user-image {
                    object-fit: cover;
                }

                /* ── Scrollbars ── */
                ::-webkit-scrollbar { width: 8px; height: 8px; }
                ::-webkit-scrollbar-track { background: #0d1117; }
                ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
                ::-webkit-scrollbar-thumb:hover { background: #484f58; }

                /* ── Scope-Locked & UI Elements ── */
                .locked-nav-item > a { opacity: 0.3 !important; filter: grayscale(1) !important; }
                .progress, .progress .progress-bar { border-radius: 10px !important; }
                .small-box { border-radius: 8px !important; }

                .main-sidebar, .sidebar { height: 100vh; overflow-y: auto; }
                .content-wrapper .content { max-width: 1440px; margin: 0 auto; }
                .content-header { padding-bottom: 6px; }
                .content-wrapper {
                    animation: panelFadeIn 220ms ease;
                }
                @keyframes panelFadeIn {
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
                    outline: 2px solid rgba(88, 166, 255, 0.8);
                    outline-offset: 1px;
                }
                @media (prefers-reduced-motion: reduce) {
                    * {
                        animation: none !important;
                        transition: none !important;
                    }
                }

                @media (max-width: 991px) {
                    .content-header { padding: 12px 12px 0 !important; }
                    .content { padding: 12px !important; }
                    .content-wrapper .content { max-width: 100%; }
                    .main-header .logo {
                        width: 56px !important;
                        min-width: 56px !important;
                        overflow: hidden;
                    }
                    .main-header .navbar { margin-left: 56px !important; }
                    .navbar-custom-menu > .navbar-nav > li > a { padding: 15px 10px !important; }
                    .navbar-custom-menu .user-menu .hidden-xs { display: none !important; }
                    .main-footer .pull-right { float: none !important; margin: 0 0 8px 0 !important; }
                    .table-responsive { border: 0 !important; }
                }
            </style>
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini panel-polish">
        <div class="wrapper">
            <header class="main-header">
                <a href="{{ route('index') }}" class="logo">
                    <span><img src="/favicons/logo.png" alt="{{ config('app.name', 'Pterodactyl') }}" style="height: 34px; width: auto;"></span>
                </a>
                <nav class="navbar navbar-static-top">
                    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>
                    <div class="navbar-custom-menu">
                        <ul class="nav navbar-nav">
                            <li class="user-menu">
                                <a href="{{ route('account') }}">
                                    <img src="{{ Auth::user()->avatar_url }}" class="user-image" alt="User Image">
                                    <span class="hidden-xs">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('index') }}" data-toggle="tooltip" data-placement="bottom" title="Exit Admin Control"><i class="fa fa-server"></i></a>
                            </li>
                            @if(Auth::user()->isRoot())
                            <li>
                                <a href="{{ route('root.dashboard') }}" data-toggle="tooltip" data-placement="bottom" title="Root Panel &mdash; Full System Control"
                                   style="position:relative;">
                                    <i class="fa fa-star" style="color:#ffd700;"></i>
                                    <span class="label label-danger" style="position:absolute;top:2px;right:2px;font-size:7px;padding:1px 3px;">R</span>
                                </a>
                            </li>
                            @endif
                            <li>
                                <a href="{{ route('auth.logout') }}" id="logoutButton" data-toggle="tooltip" data-placement="bottom" title="Logout"><i class="fa fa-sign-out"></i></a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </header>
            <aside class="main-sidebar">
                <section class="sidebar">
                    <ul class="sidebar-menu">
                        @php($actor = Auth::user())
                        @php($canReadNodes = $actor->isRoot() || $actor->hasScope('node.read'))
                        @php($canReadServers = $actor->isRoot() || $actor->hasScope('server.read'))
                        @php($canReadUsers = $actor->isRoot() || $actor->hasScope('user.read'))
                        @php($canReadDatabases = $actor->isRoot() || $actor->hasScope('database.read'))
                        @php($canReadInfra = $actor->isRoot() || $actor->hasScope('node.read'))
                        <li class="header">BASIC ADMINISTRATION</li>
                        <li class="{{ Route::currentRouteName() !== 'admin.index' ?: 'active' }}">
                            <a href="{{ route('admin.index') }}">
                                <i class="fa fa-home"></i> <span>Overview</span>
                            </a>
                        </li>
                        <li class="{{ starts_with(Route::currentRouteName(), 'admin.security.timeline') ? 'active' : '' }} {{ $canReadUsers ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadUsers ? route('admin.security.timeline') : '#' }}" {{ $canReadUsers ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-history"></i> <span>Security Timeline</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.settings') ?: 'active' }} {{ $canReadInfra ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadInfra ? route('admin.settings') : '#' }}" {{ $canReadInfra ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-wrench"></i> <span>Settings</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.api') ?: 'active' }}">
                            <a href="{{ route('admin.api.index')}}">
                                <i class="fa fa-gamepad"></i> <span>Application API</span>
                            </a>
                        </li>
                        @if(Auth::user()->isRoot())
                        <li class="{{ Route::currentRouteName() === 'admin.api.root' ? 'active' : '' }}">
                            <a href="{{ route('admin.api.root') }}" style="color: #e05454 !important;">
                                <i class="fa fa-key" style="color:#e05454 !important;"></i>
                                <span>Root API Key <span class="label label-danger" style="font-size:9px; vertical-align:middle; margin-left:2px;">ROOT</span></span>
                            </a>
                        </li>
                        @endif
                        <li class="header">MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.databases') ?: 'active' }} {{ $canReadDatabases ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadDatabases ? route('admin.databases') : '#' }}" {{ $canReadDatabases ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-database"></i> <span>Databases</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.locations') ?: 'active' }} {{ $canReadInfra ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadInfra ? route('admin.locations') : '#' }}" {{ $canReadInfra ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-globe"></i> <span>Locations</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nodes') ?: 'active' }} {{ $canReadNodes ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadNodes ? route('admin.nodes') : '#' }}" {{ $canReadNodes ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-sitemap"></i> <span>Nodes</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.servers') ?: 'active' }} {{ $canReadServers ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadServers ? route('admin.servers') : '#' }}" {{ $canReadServers ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-server"></i> <span>Servers</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.users') ?: 'active' }} {{ $canReadUsers ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadUsers ? route('admin.users') : '#' }}" {{ $canReadUsers ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-users"></i> <span>Users</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.roles') ?: 'active' }} {{ $canReadUsers ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadUsers ? route('admin.roles') : '#' }}" {{ $canReadUsers ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-shield"></i> <span>Roles</span>
                            </a>
                        </li>
                        <li class="header">SERVICE MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.mounts') ?: 'active' }} {{ $canReadServers ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadServers ? route('admin.mounts') : '#' }}" {{ $canReadServers ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-magic"></i> <span>Mounts</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nests') ?: 'active' }} {{ $canReadServers ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canReadServers ? route('admin.nests') : '#' }}" {{ $canReadServers ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-th-large"></i> <span>Nests</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </aside>
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
                            @foreach (Alert::getMessages() as $type => $messages)
                                @foreach ($messages as $message)
                                    <div class="alert alert-{{ $type }} alert-dismissable" role="alert" style="border-left:4px solid {{ $type === 'danger' ? '#dd4b39' : ($type === 'success' ? '#00a65a' : ($type === 'warning' ? '#f39c12' : '#06b0d1')) }};">
                                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                                        <strong>
                                            @if($type === 'danger') <i class="fa fa-ban"></i> Error
                                            @elseif($type === 'success') <i class="fa fa-check-circle"></i> Success
                                            @elseif($type === 'warning') <i class="fa fa-exclamation-triangle"></i> Warning
                                            @else <i class="fa fa-info-circle"></i> Info @endif
                                        </strong> &mdash;
                                        {!! $message !!}
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    @yield('content')
                </section>
            </div>
            <footer class="main-footer">
                <div class="pull-right small text-gray" style="margin-right:10px;margin-top:-7px;">
                    <strong><i class="fa fa-fw {{ $appIsGit ? 'fa-git-square' : 'fa-code-fork' }}"></i></strong> {{ $appVersion }}<br />
                    <strong><i class="fa fa-fw fa-clock-o"></i></strong> {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </div>
                <span style="color:#8ab0be;">
                    &copy; {{ date('Y') }}
                    <a href="https://pterodactyl.io/" style="color:#06b0d1;">Pterodactyl</a> &amp;
                    <strong style="color:#06b0d1;">HexZo</strong> &mdash;
                    <i class="fa fa-shield" style="color:#06b0d1;"></i> <span style="color:#06b0d1;">Protected by HexZo</span>
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
            <script src="/js/autocomplete.js" type="application/javascript"></script>

            @if(Auth::user()->root_admin)
                <script>
                    $('#logoutButton').on('click', function (event) {
                        event.preventDefault();

                        var that = this;
                        swal({
                            title: 'Do you want to log out?',
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d9534f',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Log out'
                        }, function () {
                             $.ajax({
                                type: 'POST',
                                url: '{{ route('auth.logout') }}',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },complete: function () {
                                    window.location.href = '{{route('auth.login')}}';
                                }
                        });
                    });
                });
                </script>
            @endif

            <script>
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip();
                })
            </script>
        @show
    </body>
</html>

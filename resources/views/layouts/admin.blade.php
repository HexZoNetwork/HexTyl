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
                   Tech Electric Blue — AdminLTE Full Override
                   ============================================= */

                /* ── Header & Logo ── */
                .skin-blue .main-header .navbar,
                .skin-blue .main-header .navbar .nav>li>a { background-color: #06b0d1 !important; }
                .skin-blue .main-header .logo { background-color: #057a91 !important; }
                .skin-blue .main-header .logo:hover { background-color: #06b0d1 !important; }
                .skin-blue .main-header .navbar .sidebar-toggle:hover { background-color: #057a91 !important; }
                .skin-blue .main-header .navbar .nav>li>a:hover { background: rgba(0,0,0,.15) !important; }
                .skin-blue .main-header { box-shadow: 0 2px 8px rgba(6,176,209,.35); }

                /* ── Sidebar ── */
                .skin-blue .main-sidebar { background-color: #0d1117 !important; border-right: 1px solid #1e2d38; }
                .skin-blue .sidebar-menu>li.header { color: #4ce0f2 !important; background: #0a0e14 !important; font-size: 10px; letter-spacing: 1.5px; }
                .skin-blue .sidebar-menu>li>a { border-left: 3px solid transparent !important; color: #a0b4bf !important; }
                .skin-blue .sidebar-menu>li>a:hover,
                .skin-blue .sidebar-menu>li.active>a {
                    border-left-color: #06b0d1 !important;
                    background: #1a2530 !important;
                    color: #fff !important;
                }
                .skin-blue .sidebar-menu>li.active>a { color: #4ce0f2 !important; }
                .skin-blue .sidebar-menu>li>a>.fa { color: #4a7a8a; }
                .skin-blue .sidebar-menu>li.active>a>.fa,
                .skin-blue .sidebar-menu>li>a:hover>.fa { color: #06b0d1 !important; }

                /* ── Content Area ── */
                .wrapper, .content-wrapper { background-color: #f0f4f7 !important; }
                .content-header h1 { color: #1a2a35; }
                .content-header h1 small { color: #5c7a87; }
                .breadcrumb>li+li::before { color: #8ab0be; }
                .breadcrumb a { color: #06b0d1; }

                /* ── Boxes ── */
                .box { border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
                .box.box-primary { border-top-color: #06b0d1 !important; }
                .box.box-success { border-top-color: #00a65a !important; }
                .box.box-warning { border-top-color: #f39c12 !important; }
                .box.box-danger  { border-top-color: #dd4b39 !important; }
                .box.box-info    { border-top-color: #06b0d1 !important; }
                .box-header.with-border { border-bottom-color: #e4edf1; }
                .box-title { font-weight: 600; color: #1a2a35; }

                /* ── Tables ── */
                .table>thead>tr>th { background: #e8f4f8; color: #1a2a35; border-bottom: 2px solid #06b0d1 !important; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
                .table-hover tbody tr:hover { background-color: #eef7fa !important; }
                .table>tbody>tr>td { vertical-align: middle; }

                /* ── Buttons ── */
                .btn-primary { background-color: #06b0d1 !important; border-color: #057a91 !important; }
                .btn-primary:hover,
                .btn-primary:active,
                .btn-primary:focus { background-color: #057a91 !important; border-color: #046880 !important; }
                .btn-success { background-color: #00a65a !important; border-color: #008d4c !important; }
                .btn-warning { background-color: #f39c12 !important; border-color: #e08e0b !important; }
                .btn-danger  { background-color: #dd4b39 !important; border-color: #d73925 !important; }
                .btn-info    { background-color: #06b0d1 !important; border-color: #057a91 !important; }
                .btn-default:hover { border-color: #06b0d1; color: #06b0d1; }

                /* ── Form Inputs ── */
                .form-control:focus { border-color: #06b0d1 !important; box-shadow: 0 0 0 3px rgba(6,176,209,.15) !important; }
                .control-label { font-weight: 600; color: #1a2a35; font-size: 12px; }
                .field-optional { font-size: 11px; color: #888; }

                /* ── Selects (Select2) ── */
                .select2-container--default .select2-selection--single:focus,
                .select2-container--default.select2-container--focus .select2-selection--single { border-color: #06b0d1 !important; box-shadow: 0 0 0 3px rgba(6,176,209,.15) !important; }
                .select2-container--default .select2-results__option--highlighted { background-color: #06b0d1 !important; }

                /* ── Status Labels / Badges ── */
                .label-success, .bg-green  { background-color: #00a65a !important; }
                .label-warning, .bg-yellow { background-color: #f39c12 !important; }
                .label-danger,  .bg-red    { background-color: #dd4b39 !important; }
                .label-info,    .bg-light-blue { background-color: #06b0d1 !important; }
                .bg-maroon { background-color: #7c2c2c !important; }
                .text-green { color: #00a65a !important; }
                .text-red   { color: #dd4b39 !important; }
                .text-yellow { color: #f39c12 !important; }

                /* ── Alerts ── */
                .alert-info { background-color: #d4f1f9; border-color: #06b0d1; color: #065c6e; }
                .alert-success { background-color: #d6f5e6; border-color: #00a65a; }

                /* ── Pagination ── */
                .pagination>.active>a, .pagination>.active>span,
                .pagination>.active>a:hover, .pagination>.active>span:hover {
                    background-color: #06b0d1 !important; border-color: #06b0d1 !important;
                }
                .pagination>li>a:hover { border-color: #06b0d1; color: #06b0d1; }

                /* ── Footer ── */
                .main-footer { border-top: 1px solid #d8e8ee; color: #8ab0be; background: #f8fbfc; }

                /* ── Scope-Locked Sidebar Items ── */
                .locked-nav-item > a {
                    opacity: 0.38 !important;
                    cursor: not-allowed !important;
                    pointer-events: none !important;
                    filter: grayscale(0.9) !important;
                }
                .locked-nav-item > a::after {
                    content: ' 🔒';
                    font-size: 10px;
                    opacity: 0.6;
                }
            </style>
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini">
        <div class="wrapper">
            <header class="main-header">
                <a href="{{ route('index') }}" class="logo">
                    <span><img src="/assets/logo.png" alt="{{ config('app.name', 'Pterodactyl') }}" style="height: 30px;"></span>
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
                                    <img src="https://www.gravatar.com/avatar/{{ md5(strtolower(Auth::user()->email)) }}?s=160" class="user-image" alt="User Image">
                                    <span class="hidden-xs">{{ Auth::user()->name_first }} {{ Auth::user()->name_last }}</span>
                                </a>
                            </li>
                            <li>
                                <li><a href="{{ route('index') }}" data-toggle="tooltip" data-placement="bottom" title="Exit Admin Control"><i class="fa fa-server"></i></a></li>
                            </li>
                            @if(Auth::user()->isRoot())
                            <li>
                                <li>
                                    <a href="{{ route('root.dashboard') }}" data-toggle="tooltip" data-placement="bottom" title="Root Panel &mdash; Full System Control"
                                       style="position:relative;">
                                        <i class="fa fa-star" style="color:#ffd700;"></i>
                                        <span class="label label-danger" style="position:absolute;top:2px;right:2px;font-size:7px;padding:1px 3px;">R</span>
                                    </a>
                                </li>
                            </li>
                            @endif
                            <li>
                                <li><a href="{{ route('auth.logout') }}" id="logoutButton" data-toggle="tooltip" data-placement="bottom" title="Logout"><i class="fa fa-sign-out"></i></a></li>
                            </li>
                        </ul>
                    </div>
                </nav>
            </header>
            <aside class="main-sidebar">
                <section class="sidebar">
                    <ul class="sidebar-menu">
                        <li class="header">BASIC ADMINISTRATION</li>
                        <li class="{{ Route::currentRouteName() !== 'admin.index' ?: 'active' }}">
                            <a href="{{ route('admin.index') }}">
                                <i class="fa fa-home"></i> <span>Overview</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.settings') ?: 'active' }}">
                            <a href="{{ route('admin.settings')}}">
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
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.databases') ?: 'active' }}">
                            <a href="{{ route('admin.databases') }}">
                                <i class="fa fa-database"></i> <span>Databases</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.locations') ?: 'active' }}">
                            <a href="{{ route('admin.locations') }}">
                                <i class="fa fa-globe"></i> <span>Locations</span>
                            </a>
                        </li>
                        @php($canViewNodes = Auth::user()->isRoot() || Auth::user()->hasScope('node.read'))
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nodes') ?: 'active' }} {{ $canViewNodes ? '' : 'locked-nav-item' }}">
                            <a href="{{ $canViewNodes ? route('admin.nodes') : '#' }}" {{ $canViewNodes ? '' : 'tabindex="-1" aria-disabled="true"' }}>
                                <i class="fa fa-sitemap"></i> <span>Nodes</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.servers') ?: 'active' }}">
                            <a href="{{ route('admin.servers') }}">
                                <i class="fa fa-server"></i> <span>Servers</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.users') ?: 'active' }}">
                            <a href="{{ route('admin.users') }}">
                                <i class="fa fa-users"></i> <span>Users</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.roles') ?: 'active' }}">
                            <a href="{{ route('admin.roles') }}">
                                <i class="fa fa-shield"></i> <span>Roles</span>
                            </a>
                        </li>
                        <li class="header">SERVICE MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.mounts') ?: 'active' }}">
                            <a href="{{ route('admin.mounts') }}">
                                <i class="fa fa-magic"></i> <span>Mounts</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nests') ?: 'active' }}">
                            <a href="{{ route('admin.nests') }}">
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

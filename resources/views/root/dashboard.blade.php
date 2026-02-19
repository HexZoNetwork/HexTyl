@extends('layouts.root')

@section('title')
    Root Panel
@endsection

@section('content-header')
    <h1><i class="fa fa-star" style="color:#ffd700;"></i> Root Panel <small>Full system control &mdash; root access only.</small></h1>
@endsection

@section('content')
<div class="row">
    {{-- System Stats --}}
    <div class="col-xs-12">
        <div class="row">
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#06b0d1;"><i class="fa fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Users</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['users'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#00a65a;"><i class="fa fa-server"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Servers</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['servers'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#f39c12;"><i class="fa fa-sitemap"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Nodes</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['nodes'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#dd4b39;"><i class="fa fa-key"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">API Keys</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['api_keys'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#6c5ce7;"><i class="fa fa-globe"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Public Servers</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['public_servers'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-4 col-xs-6">
                <div class="info-box" style="background:#1e3040;">
                    <span class="info-box-icon" style="background:#e17055;"><i class="fa fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text" style="color:#9ab;">Suspended</span>
                        <span class="info-box-number" style="color:#fff;">{{ $stats['suspended'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-bolt text-yellow"></i> Root Quick Actions</h3>
            </div>
            <div class="box-body">
                <a href="{{ route('root.users') }}" class="btn btn-primary btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-users"></i> Manage All Users
                </a>
                <a href="{{ route('root.servers') }}" class="btn btn-success btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-server"></i> Manage All Servers
                </a>
                <a href="{{ route('root.nodes') }}" class="btn btn-warning btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-sitemap"></i> Manage All Nodes
                </a>
                <a href="{{ route('root.api_keys') }}" class="btn btn-danger btn-block btn-lg" style="margin-bottom:8px;">
                    <i class="fa fa-key"></i> Manage All API Keys
                </a>
                <a href="{{ route('admin.index') }}" class="btn btn-default btn-block btn-lg">
                    <i class="fa fa-shield"></i> Go to Admin Panel
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-star" style="color:#ffd700;"></i> Root Privileges</h3>
            </div>
            <div class="box-body">
                <ul style="color:#333; line-height:2;">
                    <li><i class="fa fa-check text-green"></i> Bypasses <strong>all scope checks</strong></li>
                    <li><i class="fa fa-check text-green"></i> Access to <strong>every admin endpoint</strong></li>
                    <li><i class="fa fa-check text-green"></i> Can generate <strong>root API keys</strong> (<code>ptlr_</code>)</li>
                    <li><i class="fa fa-check text-green"></i> Can <strong>suspend / unsuspend</strong> any user</li>
                    <li><i class="fa fa-check text-green"></i> Can <strong>delete any server</strong></li>
                    <li><i class="fa fa-check text-green"></i> Can <strong>revoke any API key</strong></li>
                    <li><i class="fa fa-check text-green"></i> Can view <strong>all public &amp; private servers</strong></li>
                </ul>
                <hr>
                <p class="text-muted small text-center">
                    <i class="fa fa-shield"></i> <strong>Protected by HexZo</strong> &middot;
                    Powered by <a href="https://pterodactyl.io" target="_blank">Pterodactyl</a> &amp; HexZo
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

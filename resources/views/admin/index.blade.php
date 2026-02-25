@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
<style>
    .admin-overview-links .btn {
        width: 100%;
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .admin-overview-links .btn small {
        opacity: .9;
        font-size: 10px;
    }
</style>
<div class="row">
    {{-- Version Status Box --}}
    <div class="col-xs-12">
        <div class="box @if($version->isLatestPanel()) box-success @else box-danger @endif">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-server fa-fw"></i> System Information</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    <i class="fa fa-check-circle text-green"></i>
                    You are running Panel version <code>{{ config('app.version') }}</code>. Up-to-date!
                @else
                    <i class="fa fa-exclamation-triangle text-red"></i>
                    Your panel is <strong>out of date!</strong> Latest: <a href="https://github.com/Pterodactyl/Panel/releases/v{{ $version->getPanel() }}" target="_blank"><code>{{ $version->getPanel() }}</code></a> — You are on <code>{{ config('app.version') }}</code>.
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 admin-overview-links">
        <div class="box box-primary">
            <div class="box-body">
                <div class="row">
                    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
                        <a href="{{ $version->getDiscord() }}" target="_blank" class="btn btn-warning">
                            <i class="fa fa-fw fa-support"></i> Support <small>(Discord)</small>
                        </a>
                    </div>
                    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
                        <a href="https://pterodactyl.io" target="_blank" class="btn btn-primary">
                            <i class="fa fa-fw fa-book"></i> Documentation
                        </a>
                    </div>
                    <div class="clearfix visible-xs-block">&nbsp;</div>
                    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
                        <a href="https://github.com/pterodactyl/panel" target="_blank" class="btn btn-primary">
                            <i class="fa fa-fw fa-github"></i> GitHub
                        </a>
                    </div>
                    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
                        <a href="{{ $version->getDonations() }}" target="_blank" class="btn btn-success">
                            <i class="fa fa-fw fa-heart"></i> Support the Project
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

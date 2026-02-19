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
    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
        <a href="{{ $version->getDiscord() }}" target="_blank">
            <button class="btn btn-warning" style="width:100%;">
                <i class="fa fa-fw fa-support"></i> Support <small>(Discord)</small>
            </button>
        </a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
        <a href="https://pterodactyl.io" target="_blank">
            <button class="btn btn-primary" style="width:100%;">
                <i class="fa fa-fw fa-book"></i> Documentation
            </button>
        </a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
        <a href="https://github.com/pterodactyl/panel" target="_blank">
            <button class="btn btn-primary" style="width:100%;">
                <i class="fa fa-fw fa-github"></i> GitHub
            </button>
        </a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center" style="margin-bottom:8px;">
        <a href="{{ $version->getDonations() }}" target="_blank">
            <button class="btn btn-success" style="width:100%;">
                <i class="fa fa-fw fa-heart"></i> Support the Project
            </button>
        </a>
    </div>
</div>
@endsection

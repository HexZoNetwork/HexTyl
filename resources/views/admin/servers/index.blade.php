@extends('layouts.admin')

@section('title')
    List Servers
@endsection

@section('content-header')
    <h1>Servers<small>All servers available on the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Servers</li>
    </ol>
@endsection

@section('content')
@php($canCreateServer = (Auth::user()->isRoot() || Auth::user()->hasScope('server.create')) && !($hideServerCreation ?? false))
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Server List</h3>
                @if($hideServerCreation ?? false)
                    <span class="label label-warning" style="margin-left:8px;">Server creation hidden by emergency policy</span>
                @endif
                <div class="box-tools search01" style="width: 100%; max-width: 520px;">
                    <form action="{{ route('admin.servers') }}" method="GET">
                        <div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; align-items:center;">
                            <input
                                type="text"
                                name="filter[*]"
                                class="form-control"
                                value="{{ request()->input()['filter']['*'] ?? '' }}"
                                placeholder="Search Servers"
                                style="min-width: 220px; flex: 1 1 220px;"
                            >
                            <select name="state" class="form-control" style="width: 140px; flex: 0 0 140px;">
                                <option value="" {{ empty($state) ? 'selected' : '' }}>All Power</option>
                                <option value="on" {{ ($state ?? '') === 'on' || ($state ?? '') === 'online' ? 'selected' : '' }}>On</option>
                                <option value="off" {{ ($state ?? '') === 'off' || ($state ?? '') === 'offline' ? 'selected' : '' }}>Off</option>
                            </select>
                            <button type="submit" class="btn btn-default" style="flex: 0 0 auto;">
                                <i class="fa fa-search"></i>
                            </button>
                            @if($canCreateServer)
                                <a href="{{ route('admin.servers.new') }}" class="btn btn-primary" style="flex: 0 0 auto;">
                                    Create New
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Server Name</th>
                            <th>UUID</th>
                            <th>Owner</th>
                            <th>Node</th>
                            <th>Connection</th>
                            <th class="text-center">Power</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servers as $server)
                            <tr data-server="{{ $server->uuidShort }}">
                                <td><a href="{{ route('admin.servers.view', $server->id) }}"><strong>{{ $server->name }}</strong></a></td>
                                <td><code title="{{ $server->uuid }}" style="font-size:11px;">{{ substr($server->uuid, 0, 8) }}...</code></td>
                                <td><a href="{{ route('admin.users.view', $server->user->id) }}">{{ $server->user->username }}</a></td>
                                <td><a href="{{ route('admin.nodes.view', $server->node->id) }}">{{ $server->node->name }}</a></td>
                                <td>
                                    <code>{{ $server->allocation->alias ?? $server->allocation->ip }}:{{ $server->allocation->port }}</code>
                                </td>
                                <td class="text-center">
                                    @if(is_null($server->status))
                                        <span class="label label-success"><i class="fa fa-plug"></i> ON</span>
                                    @else
                                        <span class="label label-danger"><i class="fa fa-power-off"></i> OFF</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($server->isSuspended())
                                        <span class="label bg-maroon">Suspended</span>
                                    @elseif(! $server->isInstalled())
                                        <span class="label label-warning">Installing</span>
                                    @else
                                        <span class="label label-success">Active</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-xs btn-primary" href="/server/{{ $server->uuidShort }}"><i class="fa fa-terminal"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($servers->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $servers->appends(['filter' => Request::input('filter'), 'state' => request('state')])->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('.console-popout').on('click', function (event) {
            event.preventDefault();
            window.open($(this).attr('href'), 'Pterodactyl Console', 'width=800,height=400');
        });
    </script>
@endsection

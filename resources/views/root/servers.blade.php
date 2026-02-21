@extends('layouts.root')

@section('title') Root — All Servers @endsection
@section('content-header')
    <h1>All Servers <small>{{ $servers->total() }} total</small></h1>
@endsection

@section('content')
<style>
    .root-servers-filter-form {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .root-servers-filter-form .form-group {
        margin: 0;
    }
    .root-servers-inline-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 0;
        color: #d2dbc6;
        font-weight: 600;
    }
    .root-servers-check {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        width: 16px !important;
        height: 16px !important;
        border: 1px solid #5e6b80 !important;
        border-radius: 4px !important;
        background: #0b1220 !important;
        cursor: pointer !important;
        vertical-align: middle !important;
        margin: 0 !important;
        position: relative !important;
        top: 0 !important;
        opacity: 1 !important;
    }
    .root-servers-check:checked {
        border-color: #f3b22a !important;
        background: #f3b22a !important;
        box-shadow: 0 0 0 2px rgba(243, 178, 42, 0.22) !important;
    }
    .root-servers-check:checked::after {
        content: '' !important;
        position: absolute !important;
        left: 4px !important;
        top: 1px !important;
        width: 5px !important;
        height: 9px !important;
        border: solid #0f172a !important;
        border-width: 0 2px 2px 0 !important;
        transform: rotate(45deg) !important;
    }
    .root-servers-check:disabled {
        opacity: 0.45 !important;
        cursor: not-allowed !important;
    }
    @media (max-width: 768px) {
        .root-servers-filter-form {
            align-items: stretch;
        }
        .root-servers-filter-form .form-group,
        .root-servers-filter-form .btn,
        .root-servers-filter-form .root-servers-inline-check {
            width: 100%;
        }
    }
</style>
<div class="row">
    <div class="col-xs-12">
        <div class="box box-default">
            <div class="box-body">
                <form method="GET" class="root-servers-filter-form">
                    <div class="form-group">
                        <label for="min_trust" style="margin-right:8px;">Min Trust</label>
                        <input id="min_trust" class="form-control" type="number" min="0" max="100" name="min_trust" value="{{ request('min_trust', 0) }}">
                    </div>
                    <div class="form-group">
                        <label for="power" style="margin-right:8px;">Power</label>
                        <select id="power" class="form-control" name="power">
                            <option value="" {{ ($power ?? '') === '' ? 'selected' : '' }}>All</option>
                            <option value="online" {{ ($power ?? '') === 'online' ? 'selected' : '' }}>Online</option>
                            <option value="offline" {{ ($power ?? '') === 'offline' ? 'selected' : '' }}>Offline</option>
                        </select>
                    </div>
                    <label class="root-servers-inline-check"><input type="checkbox" class="root-servers-check" name="public_only" value="1" {{ request()->boolean('public_only') ? 'checked' : '' }}> Public only</label>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply Filter</button>
                    <a href="{{ route('root.servers') }}" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
                    @if(($offlineCount ?? 0) > 0)
                        <button
                            type="submit"
                            id="delete-selected-offline-btn"
                            form="delete-selected-offline-servers-form"
                            class="btn btn-warning"
                            style="margin-left:8px;"
                            onclick="return confirm('Delete selected offline servers permanently?')"
                            disabled
                        >
                            <i class="fa fa-trash-o"></i> Delete Selected Offline (0)
                        </button>
                        <button
                            type="submit"
                            form="delete-offline-servers-form"
                            class="btn btn-danger"
                            style="margin-left:8px;"
                            onclick="return confirm('Delete ALL offline servers ({{ $offlineCount }}) permanently?')"
                        >
                            <i class="fa fa-trash"></i> Delete Offline ({{ $offlineCount }})
                        </button>
                    @endif
                </form>
                <form id="delete-offline-servers-form" method="POST" action="{{ route('root.servers.delete_offline') }}" style="display:none;">
                    {{ csrf_field() }}
                </form>
                <form id="delete-selected-offline-servers-form" method="POST" action="{{ route('root.servers.delete_selected_offline') }}" style="display:none;">
                    {{ csrf_field() }}
                </form>
            </div>
        </div>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Servers &nbsp;<span class="badge" style="background:#06b0d1;">{{ $servers->total() }}</span></h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="root-servers-check" id="toggle-select-offline" title="Select all offline in this page">
                            </th>
                            <th>ID</th><th>Name</th><th>Owner</th><th>Node</th><th>Nest/Egg</th><th>Visibility</th><th>Reputation</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($servers as $server)
                        @php($isOffline = !is_null($server->status))
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    class="root-servers-check offline-selector"
                                    name="selected_ids[]"
                                    value="{{ $server->id }}"
                                    form="delete-selected-offline-servers-form"
                                    {{ $isOffline ? '' : 'disabled' }}
                                    title="{{ $isOffline ? 'Select for offline delete' : 'Only offline servers can be selected' }}"
                                >
                            </td>
                            <td><code style="font-size:10px;">{{ substr($server->uuid, 0, 8) }}</code></td>
                            <td><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></td>
                            <td>{{ $server->user->username ?? '—' }}</td>
                            <td>{{ $server->node->name ?? '—' }}</td>
                            <td>{{ $server->nest->name ?? '—' }} / {{ $server->egg->name ?? '—' }}</td>
                            <td>
                                @if(($server->visibility ?? 'private') === 'public')
                                    <span class="label label-info"><i class="fa fa-globe"></i> Public</span>
                                @else
                                    <span class="label label-default"><i class="fa fa-lock"></i> Private</span>
                                @endif
                            </td>
                            <td>
                                @php($rep = $server->reputation)
                                @if($rep)
                                    <div class="small">
                                        <span class="label label-default">Stab {{ $rep->stability_score }}</span>
                                        <span class="label label-default">Up {{ $rep->uptime_score }}</span>
                                        <span class="label label-default">Abuse {{ $rep->abuse_score }}</span>
                                        <span class="label {{ $rep->trust_score >= 80 ? 'label-success' : ($rep->trust_score < 40 ? 'label-danger' : 'label-warning') }}">
                                            Trust {{ $rep->trust_score }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                @if($server->status === 'suspended')
                                    <span class="label label-danger">Suspended</span>
                                @elseif($server->status === 'installing')
                                    <span class="label label-warning">Installing</span>
                                @elseif(is_null($server->status))
                                    <span class="label label-success">Online</span>
                                @else
                                    <span class="label label-default">Offline</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.servers.view', $server->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></a>
                                <form method="POST" action="{{ route('root.servers.delete', $server->id) }}" style="display:inline;">
                                    {{ csrf_field() }}{{ method_field('DELETE') }}
                                    <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Permanently delete server {{ $server->name }}?')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $servers->links() }}</div>
        </div>
    </div>
</div>
<script>
    (function () {
        var selectors = Array.prototype.slice.call(document.querySelectorAll('.offline-selector:not([disabled])'));
        var toggleAll = document.getElementById('toggle-select-offline');
        var actionBtn = document.getElementById('delete-selected-offline-btn');

        if (!actionBtn || selectors.length === 0) {
            if (toggleAll) toggleAll.disabled = true;
            return;
        }

        var syncState = function () {
            var checked = selectors.filter(function (el) { return el.checked; }).length;
            actionBtn.disabled = checked === 0;
            actionBtn.innerHTML = '<i class="fa fa-trash-o"></i> Delete Selected Offline (' + checked + ')';
            if (toggleAll) {
                toggleAll.checked = checked > 0 && checked === selectors.length;
            }
        };

        selectors.forEach(function (el) {
            el.addEventListener('change', syncState);
        });

        if (toggleAll) {
            toggleAll.addEventListener('change', function () {
                selectors.forEach(function (el) { el.checked = toggleAll.checked; });
                syncState();
            });
        }

        syncState();
    })();
</script>
@endsection

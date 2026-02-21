@extends('layouts.root')

@section('title')
    Audit Timeline
@endsection

@section('content-header')
    <h1><i class="fa fa-history text-yellow"></i> Global Audit Timeline <small>root-level event replay and security forensics</small></h1>
@endsection

@section('content')
<style>
    .root-audit-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .root-audit-filter-form .form-control,
    .root-audit-filter-form .btn {
        min-height: 34px;
    }
    .risk-pill {
        display: inline-block;
        min-width: 66px;
        text-align: center;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
    }
    .risk-pill.risk-critical { background: #4a1313; color: #ffd2d2; border: 1px solid #8e2d2d; }
    .risk-pill.risk-high { background: #4a2f11; color: #ffe4c7; border: 1px solid #9c6120; }
    .risk-pill.risk-medium { background: #12384a; color: #c8eeff; border: 1px solid #1b6d8f; }
    .risk-pill.risk-low { background: #1d3c1f; color: #d7f9d9; border: 1px solid #2d7f36; }
    .risk-pill.risk-info { background: #1f2835; color: #d6dde8; border: 1px solid #42506a; }
    .audit-meta-pill {
        display: inline-block;
        margin: 0 4px 4px 0;
        padding: 2px 8px;
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: top;
        border: 1px solid #2f3a4a;
        border-radius: 4px;
        background: #1b2533;
        color: #d6dde8;
        font-size: 11px;
        line-height: 1.4;
    }
    @media (max-width: 768px) {
        .root-audit-filter-form .form-control,
        .root-audit-filter-form .btn {
            width: 100%;
        }
    }
</style>
@php
    $renderMetaPairs = function ($meta) {
        if (is_array($meta)) {
            return $meta;
        }
        if (is_object($meta)) {
            return (array) $meta;
        }
        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : ['raw' => $meta];
        }

        return [];
    };
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Filters</h3></div>
            <div class="box-body">
                <form method="GET" class="root-audit-filter-form">
                    <input type="text" class="form-control" style="width:120px;" name="user_id" value="{{ $filters['user_id'] ?? '' }}" placeholder="User ID">
                    <input type="text" class="form-control" style="width:120px;" name="server_id" value="{{ $filters['server_id'] ?? '' }}" placeholder="Server ID">
                    <input type="text" class="form-control" style="min-width:170px; flex:1 1 170px;" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event Type">
                    <select class="form-control" style="width:140px;" name="risk_level">
                        <option value="">Any Risk</option>
                        @foreach(['info','low','medium','high','critical'] as $risk)
                            <option value="{{ $risk }}" {{ ($filters['risk_level'] ?? '') === $risk ? 'selected' : '' }}>{{ strtoupper($risk) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> Apply</button>
                    <a href="{{ route('root.audit.timeline') }}" class="btn btn-default"><i class="fa fa-refresh"></i> Reset</a>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">Timeline Events</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>Time</th><th>Event</th><th>Risk</th><th>User</th><th>Server</th><th>IP</th><th>Meta</th></tr></thead>
                    <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ $event->created_at ? $event->created_at->toDateTimeString() : '-' }}</td>
                            <td><code>{{ $event->event_type }}</code></td>
                            <td>
                                @php($risk = strtolower((string) ($event->risk_level ?? 'info')))
                                <span class="risk-pill risk-{{ in_array($risk, ['critical', 'high', 'medium', 'low', 'info']) ? $risk : 'info' }}">
                                    {{ strtoupper($risk) }}
                                </span>
                            </td>
                            <td>{{ $event->actor?->username ?? '-' }}</td>
                            <td>{{ $event->server?->name ?? '-' }}</td>
                            <td>{{ $event->ip ?? '-' }}</td>
                            <td>
                                @php($metaPairs = $renderMetaPairs($event->meta))
                                @if(empty($metaPairs))
                                    <span class="text-muted">-</span>
                                @else
                                    @foreach($metaPairs as $metaKey => $metaValue)
                                        <span class="audit-meta-pill">
                                            {{ $metaKey }}: {{ is_scalar($metaValue) ? (string) $metaValue : json_encode($metaValue) }}
                                        </span>
                                    @endforeach
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No events found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                {{ $events->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

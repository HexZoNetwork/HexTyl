@extends('layouts.root')

@section('title')
    Audit Timeline
@endsection

@section('content-header')
    <h1><i class="fa fa-history text-yellow"></i> Global Audit Timeline <small>root-level event replay and security forensics</small></h1>
@endsection

@section('content')
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
                <form method="GET" class="form-inline">
                    <input type="text" class="form-control" name="user_id" value="{{ $filters['user_id'] ?? '' }}" placeholder="User ID">
                    <input type="text" class="form-control" name="server_id" value="{{ $filters['server_id'] ?? '' }}" placeholder="Server ID">
                    <input type="text" class="form-control" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event Type">
                    <select class="form-control" name="risk_level">
                        <option value="">Any Risk</option>
                        @foreach(['info','low','medium','high','critical'] as $risk)
                            <option value="{{ $risk }}" {{ ($filters['risk_level'] ?? '') === $risk ? 'selected' : '' }}>{{ strtoupper($risk) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> Apply</button>
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
                            <td><span class="label {{ $event->risk_level === 'critical' ? 'label-danger' : ($event->risk_level === 'high' ? 'label-warning' : 'label-info') }}">{{ strtoupper($event->risk_level) }}</span></td>
                            <td>{{ $event->actor?->username ?? '-' }}</td>
                            <td>{{ $event->server?->name ?? '-' }}</td>
                            <td>{{ $event->ip ?? '-' }}</td>
                            <td>
                                @php($metaPairs = $renderMetaPairs($event->meta))
                                @if(empty($metaPairs))
                                    <span class="text-muted">-</span>
                                @else
                                    @foreach($metaPairs as $metaKey => $metaValue)
                                        <span class="label label-default" style="display:inline-block; margin:0 4px 4px 0; max-width: 320px; overflow:hidden; text-overflow:ellipsis; vertical-align:top;">
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

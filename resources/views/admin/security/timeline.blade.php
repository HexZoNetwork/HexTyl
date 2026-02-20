@extends('layouts.admin')

@section('title')
    Security Timeline
@endsection

@section('content-header')
    <h1>Security Timeline <small>Unified observability for blocked requests, DDoS triggers, and abuse signals.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Security Timeline</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Filters</h3>
            </div>
            <div class="box-body">
                <form method="GET" class="form-inline">
                    <input type="number" class="form-control" name="user_id" value="{{ $filters['user_id'] ?? '' }}" placeholder="User ID">
                    <input type="number" class="form-control" name="server_id" value="{{ $filters['server_id'] ?? '' }}" placeholder="Server ID">
                    <input type="text" class="form-control" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event Type">
                    <select class="form-control" name="risk_level">
                        <option value="">Any Risk</option>
                        @foreach(['info', 'low', 'medium', 'high', 'critical'] as $risk)
                            <option value="{{ $risk }}" {{ ($filters['risk_level'] ?? '') === $risk ? 'selected' : '' }}>{{ strtoupper($risk) }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control" min="5" max="10080" name="window_minutes" value="{{ $windowMinutes }}" placeholder="Window (minutes)">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Apply</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Severity Summary</h3>
            </div>
            <div class="box-body">
                @foreach(['critical', 'high', 'medium', 'low', 'info'] as $risk)
                    <p style="margin: 0 0 6px;">
                        <strong>{{ strtoupper($risk) }}:</strong> {{ (int) ($severity[$risk] ?? 0) }}
                    </p>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">Per-Server Breakdown</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr><th>Server</th><th>UUID</th><th>Total Events</th></tr>
                    </thead>
                    <tbody>
                    @forelse($perServer as $row)
                        <tr>
                            <td>{{ $row->server?->name ?? ('Server #' . (int) $row->server_id) }}</td>
                            <td><code>{{ $row->server?->uuid ?? '-' }}</code></td>
                            <td>{{ (int) $row->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No server events in selected window.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Attack Fingerprints</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr><th>Fingerprint</th><th>Event</th><th>IP</th><th>Reason/Path</th><th>Count</th></tr>
                    </thead>
                    <tbody>
                    @forelse($fingerprints as $fingerprint)
                        <tr>
                            <td><code>{{ $fingerprint['fingerprint'] }}</code></td>
                            <td><code>{{ $fingerprint['event_type'] }}</code></td>
                            <td>{{ $fingerprint['ip'] ?? '-' }}</td>
                            <td>{{ $fingerprint['reason'] ?? '-' }}</td>
                            <td>{{ (int) $fingerprint['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No fingerprints available.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Timeline Events</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr><th>Time</th><th>Event</th><th>Risk</th><th>User</th><th>Server</th><th>IP</th><th>Meta</th></tr>
                    </thead>
                    <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ $event->created_at?->toDateTimeString() ?? '-' }}</td>
                            <td><code>{{ $event->event_type }}</code></td>
                            <td>{{ strtoupper($event->risk_level) }}</td>
                            <td>{{ $event->actor?->username ?? '-' }}</td>
                            <td>{{ $event->server?->name ?? '-' }}</td>
                            <td>{{ $event->ip ?? '-' }}</td>
                            <td><small>{{ $event->meta ? json_encode($event->meta) : '-' }}</small></td>
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

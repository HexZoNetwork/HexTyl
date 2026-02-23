@extends('layouts.root')

@section('title')
    Health Center
@endsection

@section('content-header')
    <h1><i class="fa fa-heartbeat" style="color:#ffd700;"></i> Health Center <small>server stability and smart node balancer insights</small></h1>
@endsection

@section('content')
<style>
    .health-rework .box {
        border-top: 0 !important;
        border: 1px solid #263c52;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1a2a 0%, #111d2d 100%);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
    }
    .health-rework .box-header {
        border-bottom: 1px solid #23384d;
        background: rgba(18, 31, 47, 0.92);
    }
    .health-rework .box-title {
        color: #d6e8f7;
        font-weight: 700;
    }
    .health-rework .table > thead > tr > th {
        color: #91aec7;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #294058;
        background: #12253a;
    }
    .health-rework .table > tbody > tr > td {
        border-top: 1px solid #20364b;
        color: #d2dfeb;
        vertical-align: middle;
    }
    .health-rework .table > tbody > tr:hover {
        background: rgba(48, 130, 218, 0.08);
    }
    .health-rework .btn-recalc {
        border-radius: 8px;
        font-weight: 700;
        padding: 10px 14px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
    }
</style>
<div class="row health-rework">
    <div class="col-xs-12" style="margin-bottom:12px;">
        <a href="{{ route('root.health_center', ['recalculate' => 1]) }}" class="btn btn-warning btn-recalc">
            <i class="fa fa-refresh"></i> Recalculate Health Data
        </a>
    </div>
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Server Stability Index</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>Server</th><th>Status</th><th>Index</th><th>Penalty</th><th>Reason</th><th>Updated</th></tr></thead>
                    <tbody>
                    @forelse($serverHealth as $row)
                        <tr>
                            <td>{{ $row->server?->name ?? ('#' . $row->server_id) }}</td>
                            <td>{{ $row->server?->status ?? '-' }}</td>
                            <td><span class="label {{ $row->stability_index < 50 ? 'label-danger' : ($row->stability_index < 75 ? 'label-warning' : 'label-success') }}">{{ $row->stability_index }}</span></td>
                            <td>{{ $row->crash_penalty + $row->restart_penalty + $row->snapshot_penalty }}</td>
                            <td>{{ $row->last_reason ?? '-' }}</td>
                            <td>{{ $row->last_calculated_at ? $row->last_calculated_at->diffForHumans() : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No server health data. Click <strong>Recalculate Health Data</strong>.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $serverHealth->appends(request()->query())->links() }}</div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Node Reliability & Placement Score</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>Node</th><th>Health</th><th>Reliability</th><th>Placement</th></tr></thead>
                    <tbody>
                    @forelse($nodeHealth as $node)
                        <tr>
                            <td>{{ $node->node?->name ?? ('#' . $node->node_id) }}</td>
                            <td>{{ $node->health_score }}</td>
                            <td>{{ $node->reliability_rating }}</td>
                            <td>{{ $node->placement_score }}</td>
                        </tr>
                        @if($node->migration_recommendation)
                            <tr><td colspan="4"><small class="text-muted"><i class="fa fa-lightbulb-o"></i> {{ $node->migration_recommendation }}</small></td></tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                No node health data. Click <strong>Recalculate Health Data</strong>.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $nodeHealth->appends(request()->query())->links() }}</div>
        </div>
    </div>
</div>
@endsection

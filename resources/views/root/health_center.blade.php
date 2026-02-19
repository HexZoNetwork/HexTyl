@extends('layouts.root')

@section('title')
    Health Center
@endsection

@section('content-header')
    <h1><i class="fa fa-heartbeat text-green"></i> Health Center <small>server stability and smart node balancer insights</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="box box-success">
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
                        <tr><td colspan="6" class="text-center text-muted">No server health data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $serverHealth->appends(request()->query())->links() }}</div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="box box-info">
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
                        <tr><td colspan="4" class="text-center text-muted">No node health data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $nodeHealth->appends(request()->query())->links() }}</div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.root')

@section('title')
    Threat Intelligence
@endsection

@section('content-header')
    <h1><i class="fa fa-line-chart text-red"></i> Threat Intelligence <small>IP risk heatmap, anomaly, and trend analytics</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-3"><div class="small-box bg-green"><div class="inner"><h3>{{ $data['risk_distribution']['normal'] }}</h3><p>Normal</p></div><div class="icon"><i class="fa fa-check"></i></div></div></div>
    <div class="col-sm-3"><div class="small-box bg-yellow"><div class="inner"><h3>{{ $data['risk_distribution']['elevated'] }}</h3><p>Elevated</p></div><div class="icon"><i class="fa fa-warning"></i></div></div></div>
    <div class="col-sm-3"><div class="small-box bg-orange"><div class="inner"><h3>{{ $data['risk_distribution']['high'] }}</h3><p>High</p></div><div class="icon"><i class="fa fa-bolt"></i></div></div></div>
    <div class="col-sm-3"><div class="small-box bg-red"><div class="inner"><h3>{{ $data['risk_distribution']['critical'] }}</h3><p>Critical</p></div><div class="icon"><i class="fa fa-fire"></i></div></div></div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="box box-danger">
            <div class="box-header with-border"><h3 class="box-title">Top Risky IPs</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>IP</th><th>Score</th><th>Mode</th><th>Geo</th><th>Last Seen</th></tr></thead>
                    <tbody>
                    @forelse($data['top_risky_ips'] as $row)
                        <tr>
                            <td><code>{{ $row->identifier }}</code></td>
                            <td><span class="label {{ $row->risk_score >= 80 ? 'label-danger' : ($row->risk_score >= 50 ? 'label-warning' : 'label-info') }}">{{ $row->risk_score }}</span></td>
                            <td>{{ strtoupper($row->risk_mode) }}</td>
                            <td>{{ $row->geo_country ?: 'UNK' }}</td>
                            <td>{{ $row->last_seen_at ? $row->last_seen_at->diffForHumans() : 'never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No risk snapshots yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Geo Risk Heatmap (Top Countries)</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>Country</th><th>Risk Sources</th></tr></thead>
                    <tbody>
                    @forelse($data['geo_heatmap'] as $geo)
                        <tr>
                            <td>{{ $geo->geo_country ?: 'UNK' }}</td>
                            <td>{{ $geo->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted">No geo data yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title">Risk Trend (7 Days)</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead><tr><th>Date</th><th>Events</th></tr></thead>
                    <tbody>
                    @forelse($data['risk_trend'] as $trend)
                        <tr><td>{{ $trend->day }}</td><td>{{ $trend->total }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted">No trend data yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.root')

@section('title') Root — All Nodes @endsection
@section('content-header')
    <h1>All Nodes <small>{{ $nodes->total() }} total</small></h1>
@endsection

@section('content')
<style>
    .root-nodes-rework .box {
        border-top: 0 !important;
        border: 1px solid #263b51;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1a2a 0%, #101b2a 100%);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
        animation: rootNodesFade 220ms ease both;
    }
    .root-nodes-rework .box-header {
        border-bottom: 1px solid #20384e;
        background: rgba(17, 30, 46, 0.92);
    }
    .root-nodes-rework .box-title {
        color: #d9e8f6;
        font-weight: 700;
    }
    .root-nodes-rework .badge {
        border-radius: 999px;
        padding: 4px 8px;
    }
    .root-nodes-rework .table > thead > tr > th {
        color: #93afc6;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #294057;
        background: #12253a;
    }
    .root-nodes-rework .table > tbody > tr > td {
        border-top: 1px solid #1e3448;
        color: #d0deea;
        vertical-align: middle;
    }
    .root-nodes-rework .table > tbody > tr:hover {
        background: rgba(48, 130, 218, 0.08);
    }
    @keyframes rootNodesFade {
        from { opacity: 0; transform: translateY(7px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<div class="row root-nodes-rework">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Nodes &nbsp;<span class="badge" style="background:#06b0d1;">{{ $nodes->total() }}</span></h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Location</th><th>FQDN</th><th>Scheme</th><th>Servers</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nodes as $node)
                        <tr>
                            <td>{{ $node->id }}</td>
                            <td><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></td>
                            <td>
                                @if($node->location)
                                    {{ $node->location->long ?: $node->location->short }}
                                @elseif(!empty($node->location_id))
                                    <span class="text-muted">#{{ $node->location_id }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td><code>{{ $node->fqdn }}</code></td>
                            <td>
                                @if($node->scheme === 'https')
                                    <span class="label label-success">SSL</span>
                                @else
                                    <span class="label label-warning">HTTP</span>
                                @endif
                            </td>
                            <td><span class="badge" style="background:#06b0d1;">{{ $node->servers_count }}</span></td>
                            <td>
                                <a href="{{ route('admin.nodes.view', $node->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></a>
                                <a href="{{ route('admin.nodes.view.settings', $node->id) }}" class="btn btn-xs btn-warning"><i class="fa fa-wrench"></i></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $nodes->links() }}</div>
        </div>
    </div>
</div>
@endsection

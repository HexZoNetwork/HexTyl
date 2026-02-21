@extends('layouts.root')

@section('title') Root — All Nodes @endsection
@section('content-header')
    <h1>All Nodes <small>{{ $nodes->total() }} total</small></h1>
@endsection

@section('content')
<div class="row">
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

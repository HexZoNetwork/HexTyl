@extends('layouts.root')

@section('title') Root — All Servers @endsection
@section('content-header')
    <h1>All Servers <small>{{ $servers->total() }} total</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Servers &nbsp;<span class="badge" style="background:#06b0d1;">{{ $servers->total() }}</span></h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Owner</th><th>Node</th><th>Nest/Egg</th><th>Visibility</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($servers as $server)
                        <tr>
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
                                @if($server->status === 'suspended')
                                    <span class="label label-danger">Suspended</span>
                                @elseif($server->status === 'installing')
                                    <span class="label label-warning">Installing</span>
                                @else
                                    <span class="label label-success">Active</span>
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
@endsection

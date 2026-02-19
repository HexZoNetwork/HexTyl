@extends('layouts.root')

@section('title') Root — All Users @endsection
@section('content-header')
    <h1>All Users <small>{{ $users->total() }} total</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">User Accounts &nbsp;<span class="badge" style="background:#06b0d1;">{{ $users->total() }}</span></h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Servers</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>{{ $user->id }} @if($user->isRoot()) <span class="label label-danger">ROOT</span>@endif</td>
                            <td><a href="{{ route('admin.users.view', $user->id) }}">{{ $user->username }}</a></td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->root_admin ? 'Admin' : 'User' }}</td>
                            <td><span class="badge" style="background:#06b0d1;">{{ $user->servers_count }}</span></td>
                            <td>
                                @if($user->suspended)
                                    <span class="label label-danger">Suspended</span>
                                @else
                                    <span class="label label-success">Active</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.users.view', $user->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></a>
                                @if(!$user->isRoot())
                                <form method="POST" action="{{ route('root.users.toggle_suspension', $user->id) }}" style="display:inline;">
                                    {{ csrf_field() }}
                                    <button type="submit" class="btn btn-xs {{ $user->suspended ? 'btn-success' : 'btn-warning' }}"
                                            onclick="return confirm('Toggle suspension for {{ $user->username }}?')">
                                        <i class="fa fa-{{ $user->suspended ? 'check' : 'ban' }}"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $users->links() }}</div>
        </div>
    </div>
</div>
@endsection

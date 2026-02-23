@extends('layouts.root')

@section('title') Root — All Users @endsection
@section('content-header')
    <h1>All Users <small>{{ $users->total() }} total</small></h1>
@endsection

@section('content')
<style>
    .root-users-rework .box {
        border-top: 0 !important;
        border: 1px solid #263b51;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1a2a 0%, #101b2a 100%);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
        animation: rootUsersFade 220ms ease both;
    }
    .root-users-rework .box-header {
        border-bottom: 1px solid #20384e;
        background: rgba(17, 30, 46, 0.92);
    }
    .root-users-rework .box-title {
        color: #d9e8f6;
        font-weight: 700;
    }
    .root-users-rework .badge {
        border-radius: 999px;
        padding: 4px 8px;
    }
    .root-users-rework .table > thead > tr > th {
        color: #93afc6;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #294057;
        background: #12253a;
    }
    .root-users-rework .table > tbody > tr > td {
        border-top: 1px solid #1e3448;
        color: #d0deea;
        vertical-align: middle;
    }
    .root-users-rework .table > tbody > tr:hover {
        background: rgba(48, 130, 218, 0.08);
    }
    @keyframes rootUsersFade {
        from { opacity: 0; transform: translateY(7px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
<div class="row root-users-rework">
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

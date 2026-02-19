@extends('layouts.admin')

@section('title')
    Roles
@endsection

@section('content-header')
    <h1>Roles<small>Manage roles and their permission scopes.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Roles</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Role List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.roles.new') }}">
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="fa fa-plus"></i> Create New Role
                        </button>
                    </a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width:40px;">ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center">Scopes</th>
                            <th class="text-center">Users</th>
                            <th class="text-center">System</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                            <tr>
                                <td><code>{{ $role->id }}</code></td>
                                <td>
                                    <a href="{{ route('admin.roles.view', $role->id) }}"><strong>{{ $role->name }}</strong></a>
                                </td>
                                <td class="text-muted">{{ $role->description ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="label label-info">{{ $role->scopes->count() }}</span>
                                </td>
                                <td class="text-center">{{ $role->users_count }}</td>
                                <td class="text-center">
                                    @if($role->is_system_role)
                                        <i class="fa fa-lock text-yellow" title="System Role — protected"></i>
                                    @else
                                        <i class="fa fa-unlock text-muted" title="Custom Role"></i>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.roles.view', $role->id) }}" class="btn btn-xs btn-primary">
                                        <i class="fa fa-edit"></i> Edit
                                    </a>
                                    @if(!$role->is_system_role)
                                        <form action="{{ route('admin.roles.delete', $role->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete role \'{{ $role->name }}\'?')">
                                            {!! csrf_field() !!}
                                            {!! method_field('DELETE') !!}
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

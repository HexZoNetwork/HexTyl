@extends('layouts.admin')

@section('title')
    Role: {{ $role->name }}
@endsection

@section('content-header')
    <h1>{{ $role->name }}<small>{{ $role->description }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.roles') }}">Roles</a></li>
        <li class="active">{{ $role->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    {{-- ── Role Details ── --}}
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Role Details</h3>
                @if($role->is_system_role)
                    <span class="label label-warning pull-right" style="margin-top:3px;"><i class="fa fa-lock"></i> System Role</span>
                @endif
            </div>
            @if($role->is_system_role)
                <div class="box-body">
                    <div class="alert alert-info no-margin-bottom">
                        <i class="fa fa-info-circle"></i> This is a system-protected role. You can manage its scopes, but not rename or delete it.
                    </div>
                </div>
            @else
                <form action="{{ route('admin.roles.update', $role->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Role Name</label>
                            <input type="text" name="name" value="{{ $role->name }}" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label class="control-label">Description</label>
                            <input type="text" name="description" value="{{ $role->description }}" class="form-control" />
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Scopes Management ── --}}
    <div class="col-md-6">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-key"></i> Permission Scopes</h3>
                <span class="label label-success pull-right" style="margin-top:3px;">{{ $role->scopes->count() }} scopes</span>
            </div>
            <div class="box-body">
                @if($role->scopes->isEmpty())
                    <p class="text-muted text-center" style="padding:10px 0;">No scopes assigned. This role has no permissions.</p>
                @else
                    <table class="table table-condensed" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Scope</th>
                                <th class="text-center" style="width:80px;">Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($role->scopes as $scope)
                                <tr>
                                    <td><code>{{ $scope->scope }}</code></td>
                                    <td class="text-center">
                                        <form action="{{ route('admin.roles.scopes.remove', [$role->id, $scope->id]) }}" method="POST">
                                            {!! csrf_field() !!}
                                            {!! method_field('DELETE') !!}
                                            <button type="submit" class="btn btn-xs btn-danger" title="Remove scope"><i class="fa fa-times"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.roles.scopes.add', $role->id) }}" method="POST" class="form-inline">
                    {!! csrf_field() !!}
                    <div class="input-group input-group-sm" style="width:100%;">
                        <input type="text" name="scope" class="form-control" placeholder="e.g. server.create or *" required />
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Add Scope</button>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ── Danger Zone ── --}}
@if(!$role->is_system_role)
<div class="row">
    <div class="col-xs-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Delete Role</h3>
            </div>
            <div class="box-body">
                <p>Deleting this role will unassign it from all users. This action cannot be undone.</p>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.roles.delete', $role->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete role \'{{ $role->name }}\'?')">
                    {!! csrf_field() !!}
                    {!! method_field('DELETE') !!}
                    <button type="submit" class="btn btn-danger btn-sm pull-right"><i class="fa fa-trash"></i> Delete Role</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

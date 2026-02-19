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
@php($canManageRoles = Auth::user()->isRoot() || Auth::user()->hasScope('user.update'))
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Role List</h3>
                @if($canManageRoles)
                    <div class="box-tools">
                        <a href="{{ route('admin.roles.new') }}">
                            <button type="button" class="btn btn-sm btn-primary">
                                <i class="fa fa-plus"></i> Create New Role
                            </button>
                        </a>
                    </div>
                @endif
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
                                        <i class="fa fa-edit"></i> View
                                    </a>
                                    @if($canManageRoles && !$role->is_system_role)
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
    <div class="col-xs-12">
        <div class="box box-info" style="background: #161b22; border-top-color: #58a6ff !important;">
            <div class="box-header with-border">
                <h3 class="box-title" style="color: #58a6ff;"><i class="fa fa-book"></i> Role Scopes Tutorial</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-sm-6">
                        <h4 style="color: #c9d1d9;">Understanding Scopes</h4>
                        <p class="text-muted">Scopes are the specific permissions assigned to a role. In HexTyl, we use a <strong>Write-Locked UI</strong> logic:</p>
                        <ul class="text-muted" style="line-height: 1.8;">
                            <li><i class="fa fa-eye text-blue"></i> <strong>Read Scope:</strong> Allows admins to view data but all "Save" and "Delete" buttons will be hidden or disabled.</li>
                            <li><i class="fa fa-pencil text-green"></i> <strong>Write Scope:</strong> Unlocks all management buttons and full editing power for that specific area.</li>
                            <li><i class="fa fa-asterisk text-yellow"></i> <strong>Wildcard (*):</strong> Granting <code>*</code> provides total access to all children of that scope.</li>
                        </ul>
                    </div>
                    <div class="col-sm-6">
                        <h4 style="color: #c9d1d9;">Smart UI Integration</h4>
                        <p class="text-muted">Our admin panel automatically detects missing scopes and protects the system:</p>
                        <ul class="text-muted" style="line-height: 1.8;">
                            <li><i class="fa fa-lock"></i> Navigation items for unauthorized areas are dimmed out.</li>
                            <li><i class="fa fa-shield"></i> Unauthorized API calls are blocked at the middleware level.</li>
                            <li><i class="fa fa-info-circle"></i> Hover over disabled buttons to see which scope you are missing!</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="box-footer" style="background: rgba(88, 166, 255, 0.05); border-top: 1px solid #30363d;">
                <p class="text-center no-margin">
                    <small style="color: #8b949e;">Need help? Contact the <span class="label label-danger">System Root</span> account for permission adjustments.</small>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

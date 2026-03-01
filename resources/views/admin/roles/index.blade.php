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
<style>
    .role-rework .box {
        border-top: 0 !important;
        border: 1px solid #233447;
        border-radius: 10px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1b2a 0%, #101a28 100%);
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.24);
    }
    .role-rework .box-header {
        border-bottom: 1px solid #203247;
        padding: 14px 16px;
        background: rgba(17, 30, 46, 0.92);
    }
    .role-rework .box-title {
        color: #d6e2f0;
        font-weight: 700;
    }
    .role-rework .table > thead > tr > th {
        font-size: 12px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #96afc6;
        border-bottom: 1px solid #25364b;
        background: #13253a;
    }
    .role-rework .table > tbody > tr > td {
        border-top: 1px solid #1e3044;
        color: #d4dfeb;
        vertical-align: middle;
    }
    .role-rework .table > tbody > tr:hover {
        background: rgba(43, 125, 208, 0.08);
    }
    .role-rework .role-id {
        background: #1a2d42;
        color: #89d4ff;
        border: 1px solid #274462;
        border-radius: 5px;
        padding: 2px 7px;
        font-size: 12px;
    }
    .role-rework .role-name {
        font-weight: 700;
        color: #6ac3ff;
    }
    .role-rework .label-soft {
        display: inline-block;
        min-width: 24px;
        border-radius: 999px;
        font-size: 11px;
        padding: 3px 8px;
        background: #125f8f;
        color: #d9f3ff;
        font-weight: 700;
    }
    .role-rework .scope-card {
        min-height: 240px;
        border: 1px solid #24405d;
        border-radius: 10px;
        padding: 16px;
        background: linear-gradient(145deg, #11253b 0%, #101b2a 100%);
    }
    .role-rework .scope-card h4 {
        margin-top: 0;
        font-weight: 700;
        color: #d7e9fb;
    }
    .role-rework .scope-card ul {
        color: #c0d0df;
        margin: 0;
        padding-left: 18px;
        line-height: 1.85;
    }
    .role-rework .scope-footer {
        margin-top: 14px;
        border-top: 1px solid #203247;
        padding-top: 10px;
        color: #90a7bc;
    }
    .role-rework .role-guide {
        border: 1px solid #27425f;
        border-radius: 10px;
        padding: 12px;
        background: linear-gradient(145deg, #102238 0%, #0f1d2f 100%);
        margin-bottom: 14px;
    }
    .role-rework .role-guide h4 {
        margin: 0 0 8px 0;
        color: #d7e9fb;
        font-weight: 700;
    }
    .role-rework .role-guide ul {
        margin: 0;
        padding-left: 18px;
        color: #bfd2e5;
        line-height: 1.7;
    }
</style>
<div class="row role-rework">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Role List</h3>
                @if($canManageRoles)
                    <div class="box-tools">
                        <a href="{{ route('admin.roles.new') }}" class="btn btn-sm btn-success">
                            <i class="fa fa-plus"></i> Create New Role
                        </a>
                    </div>
                @endif
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th style="width:48px;">ID</th>
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
                            <td><code class="role-id">{{ $role->id }}</code></td>
                            <td><a class="role-name" href="{{ route('admin.roles.view', $role->id) }}">{{ $role->name }}</a></td>
                            <td class="text-muted">{{ $role->description ?? 'No description.' }}</td>
                            <td class="text-center"><span class="label-soft">{{ $role->scopes->count() }}</span></td>
                            <td class="text-center">{{ $role->users_count }}</td>
                            <td class="text-center">
                                @if($role->is_system_role)
                                    <i class="fa fa-lock text-warning" title="System Role"></i>
                                @else
                                    <i class="fa fa-unlock text-muted" title="Custom Role"></i>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.roles.view', $role->id) }}" class="btn btn-xs btn-primary">
                                    <i class="fa fa-eye"></i> View
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
    </div>

    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-book"></i> Role Scopes Tutorial</h3>
            </div>
            <div class="box-body">
                <div class="role-guide">
                    <h4>Role Quick Map (recommended)</h4>
                    <ul>
                        <li><strong>Root:</strong> Full control including root-only settings and root account mutation.</li>
                        <li><strong>Admin:</strong> Manage users/servers based on admin scopes, but not root-only engine settings.</li>
                        <li><strong>User:</strong> Client access only (no admin panel capabilities).</li>
                        <li><strong>IDE/API note:</strong> IDE session token is temporary and separate from PTLR root API token.</li>
                    </ul>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="scope-card">
                            <h4>Understanding Scopes</h4>
                            <ul>
                                <li><strong>Read Scope:</strong> Admin can view data, while mutation buttons stay disabled.</li>
                                <li><strong>Write Scope:</strong> Unlocks full action controls for that domain.</li>
                                <li><strong>Wildcard (*):</strong> Gives full access to all child scopes.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="scope-card">
                            <h4>Smart UI Integration</h4>
                            <ul>
                                <li>Unauthorized menus are visually dimmed.</li>
                                <li>Middleware blocks unauthorized API access.</li>
                                <li>Disabled actions explain missing scope on hover.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="text-center scope-footer">
                    Need adjustment? Contact <span class="label label-danger">System Root</span> to update your role map.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

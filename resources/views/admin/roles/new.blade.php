@extends('layouts.admin')

@section('title')
    Create Role
@endsection

@section('content-header')
    <h1>Create Role<small>Add a new permission role to the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.roles') }}">Roles</a></li>
        <li class="active">Create</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Role Details</h3>
            </div>
            <form method="POST" action="{{ route('admin.roles.store') }}">
                {!! csrf_field() !!}
                <div class="box-body">
                    <div class="form-group">
                        <label for="name" class="control-label">Role Name <span class="field-required">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control" placeholder="e.g. Moderator" required />
                        <p class="text-muted small">Unique name for this role. Will be shown in user management.</p>
                    </div>
                    <div class="form-group">
                        <label for="description" class="control-label">Description</label>
                        <input type="text" name="description" id="description" value="{{ old('description') }}" class="form-control" placeholder="Optional description" />
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> Create Role</button>
                    <a href="{{ route('admin.roles') }}" class="btn btn-default btn-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> About Roles &amp; Scopes</h3>
            </div>
            <div class="box-body">
                <p>Roles group <strong>permission scopes</strong> into named collections. Users assigned a role inherit all its scopes.</p>
                <p>System roles (<em>Root, Admin, User</em>) are protected and cannot be deleted. You can add or remove scopes on any role after creation.</p>
                <h5>Available Scope Examples</h5>
                <ul class="small">
                    <li><code>user.create</code> — Create new users</li>
                    <li><code>user.read</code> — List and view users</li>
                    <li><code>user.update</code> — Update user details</li>
                    <li><code>user.admin.create</code> — Promote users to admin</li>
                    <li><code>server.create</code> — Create servers</li>
                    <li><code>*</code> — Wildcard: all permissions</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

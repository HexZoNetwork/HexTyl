@extends('layouts.admin')

@section('title')
    Root API Key
@endsection

@section('content-header')
    <h1>Root API Key <small>Master key &mdash; bypasses all scopes &amp; works on every endpoint.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.api.index') }}">Application API</a></li>
        <li class="active">Root API Key</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-danger" style="border-left: 4px solid #dd4b39;">
            <strong><i class="fa fa-shield"></i> Root Master Key</strong> &mdash;
            This key has <strong>full read/write access to every API endpoint</strong> (application <em>and</em> client).
            Keep it secret. Do <strong>not</strong> share it. Treat it like a password.
            The full key is shown <strong>once</strong> at generation time.
        </div>
    </div>
</div>

<div class="row">
    {{-- Generate new key --}}
    <div class="col-sm-4 col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-key"></i> Generate Root Key</h3>
            </div>
            <form method="POST" action="{{ route('admin.api.root.store') }}">
                {{ csrf_field() }}
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label" for="memoField">Description <span class="field-required"></span></label>
                        <input id="memoField" type="text" name="memo" class="form-control" placeholder="e.g. CI/CD pipeline key" required>
                        <p class="text-muted small">A short reminder of what this key is used for.</p>
                    </div>
                    <p class="text-muted small">
                        The complete key (<code>ptlr_&hellip;</code>) is displayed <strong>once</strong> after creation.
                        You <em>cannot</em> retrieve it again.
                    </p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-danger btn-sm pull-right">
                        <i class="fa fa-key"></i> Generate Root Key
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Existing root keys --}}
    <div class="col-sm-8 col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list"></i> Active Root Keys</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Identifier</th>
                            <th>Description</th>
                            <th>Last Used</th>
                            <th>Created</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($keys as $key)
                            <tr>
                                <td>
                                    <code style="color:#06b0d1;">{{ $key->identifier }}</code>
                                    <span class="label label-danger" style="font-size:10px; margin-left:4px;">ROOT</span>
                                </td>
                                <td>{{ $key->memo }}</td>
                                <td>
                                    @if($key->last_used_at)
                                        @datetimeHuman($key->last_used_at)
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td>@datetimeHuman($key->created_at)</td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.api.root.delete', $key->identifier) }}" style="display:inline;">
                                        {{ csrf_field() }}
                                        {{ method_field('DELETE') }}
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Revoke this root key? This cannot be undone.')">
                                            <i class="fa fa-trash-o"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding:24px;">
                                    No root API keys exist. Generate one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> Using the Root Key</h3>
                <div class="box-tools">
                    <a href="{{ route('docs.index') }}" class="btn btn-xs btn-default" target="_blank">Open /doc</a>
                </div>
            </div>
            <div class="box-body">
                <p>Pass the key as a Bearer token on any request:</p>
                <pre style="background:#1a2530;color:#4ce0f2;border-radius:4px;padding:12px;">Authorization: Bearer ptlr_&lt;identifier&gt;&lt;token&gt;</pre>
                <ul class="text-muted" style="font-size:13px;">
                    <li>Works on <strong>Application API</strong> (<code>/api/application/*</code>)</li>
                    <li>Works on <strong>Client API</strong> (<code>/api/client/*</code>)</li>
                    <li>Works on <strong>Root Application API</strong> (<code>/api/rootapplication/*</code>)</li>
                    <li>Bypasses all permission scopes and role checks</li>
                    <li>Can create/delete users, nodes, servers, and more</li>
                </ul>
                <hr>
                <p><strong>New RootApplication endpoints:</strong></p>
                <pre style="background:#1a2530;color:#4ce0f2;border-radius:4px;padding:12px;white-space:pre-wrap;">GET  /api/rootapplication/overview
GET  /api/rootapplication/servers/offline
GET  /api/rootapplication/servers/quarantined
GET  /api/rootapplication/servers/reputations?min_trust=60
GET  /api/rootapplication/security/settings
POST /api/rootapplication/security/settings
GET  /api/rootapplication/security/mode
GET  /api/rootapplication/threat/intel
GET  /api/rootapplication/audit/timeline
GET  /api/rootapplication/health/servers
GET  /api/rootapplication/health/nodes
GET  /api/rootapplication/vault/status</pre>
            </div>
        </div>
    </div>
</div>
@endsection

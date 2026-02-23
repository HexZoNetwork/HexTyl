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
<style>
    .root-api-rework .panel-box {
        border: 1px solid #26384c;
        border-radius: 10px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1a2a 0%, #101a28 100%);
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.26);
        margin-bottom: 16px;
    }
    .root-api-rework .panel-head {
        padding: 13px 16px;
        border-bottom: 1px solid #22374b;
        background: rgba(18, 29, 45, 0.95);
    }
    .root-api-rework .panel-title {
        margin: 0;
        color: #d8e8f6;
        font-weight: 700;
    }
    .root-api-rework .panel-body {
        padding: 14px 16px;
        color: #c9d6e3;
    }
    .root-api-rework .alert-root {
        border: 1px solid #83343e;
        border-left: 4px solid #f04f5f;
        border-radius: 10px;
        background: rgba(103, 21, 30, 0.38);
        color: #ffd8dc;
        padding: 14px 16px;
    }
    .root-api-rework .table > thead > tr > th {
        color: #94adc4;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #2a3d51;
        background: #13243a;
    }
    .root-api-rework .table > tbody > tr > td {
        border-top: 1px solid #1f3044;
        color: #d2deeb;
        vertical-align: middle;
    }
    .root-api-rework .mono-box {
        background: #111d2c;
        color: #74ddff;
        border: 1px solid #28435f;
        border-radius: 8px;
        padding: 11px 12px;
        white-space: pre-wrap;
    }
</style>
<div class="root-api-rework">
<div class="row">
    <div class="col-xs-12">
        <div class="alert-root">
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
        <div class="panel-box">
            <div class="panel-head"><h3 class="panel-title"><i class="fa fa-key"></i> Generate Elevated Key</h3></div>
            <form method="POST" action="{{ route('admin.api.root.store') }}">
                {{ csrf_field() }}
                <div class="panel-body">
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
                <div class="panel-body" style="border-top:1px solid #22374b; padding-top:10px; padding-bottom:10px;">
                    <button type="submit" class="btn btn-danger btn-sm pull-right">
                        <i class="fa fa-key"></i> Generate Key
                    </button>
                    <div class="clearfix"></div>
                </div>
            </form>
        </div>
    </div>

    {{-- Existing root keys --}}
    <div class="col-sm-8 col-xs-12">
        <div class="panel-box">
            <div class="panel-head"><h3 class="panel-title"><i class="fa fa-list"></i> Active Root Keys</h3></div>
            <div class="panel-body table-responsive no-padding">
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
                                    @if($key->key_type === \Pterodactyl\Models\ApiKey::TYPE_ROOT)
                                        <span class="label label-danger" style="font-size:10px; margin-left:6px;">ROOT</span>
                                    @endif
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
                                    No elevated API keys exist. Generate one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-box">
            <div class="panel-head">
                <h3 class="panel-title"><i class="fa fa-info-circle"></i> Using the Root Key</h3>
                <div class="pull-right">
                    <a href="{{ route('docs.index') }}" class="btn btn-xs btn-default" target="_blank">Open /doc</a>
                </div>
                <div class="clearfix"></div>
            </div>
            <div class="panel-body">
                <p>Pass the key as a Bearer token on any request:</p>
                <pre class="mono-box">Authorization: Bearer ptlr_&lt;identifier&gt;&lt;token&gt;</pre>
                <ul class="text-muted" style="font-size:13px;">
                    <li>Works on <strong>Application API</strong> (<code>/api/application/*</code>)</li>
                    <li>Works on <strong>Client API</strong> (<code>/api/client/*</code>)</li>
                    <li><code>ptlr_</code> works on <strong>Root Application API</strong> (<code>/api/rootapplication/*</code>)</li>
                    <li><code>ptlr_</code> bypasses all permission scopes and role checks</li>
                </ul>
                <hr>
                <p><strong>New RootApplication endpoints:</strong></p>
                <pre class="mono-box">GET  /api/rootapplication/overview
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
</div>
@endsection

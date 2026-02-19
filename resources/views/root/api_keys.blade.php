@extends('layouts.root')

@section('title') Root — All API Keys @endsection
@section('content-header')
    <h1>All API Keys <small>System-wide key management</small></h1>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    API Keys &nbsp;<span class="badge" style="background:#06b0d1;">{{ $keys->total() }}</span>
                </h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Identifier</th><th>Type</th><th>Owner</th><th>Description</th><th>Last Used</th><th>Created</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($keys as $key)
                        <tr>
                            <td>
                                <code style="font-size:11px;color:{{ $key->isRootKey() ? '#e05454' : '#06b0d1' }};">{{ $key->identifier }}</code>
                                @if($key->isRootKey()) <span class="label label-danger" style="font-size:9px;">ROOT</span>@endif
                            </td>
                            <td>
                                @if($key->key_type === \Pterodactyl\Models\ApiKey::TYPE_ROOT)
                                    <span class="label label-danger">Root</span>
                                @elseif($key->key_type === \Pterodactyl\Models\ApiKey::TYPE_APPLICATION)
                                    <span class="label label-primary">Application</span>
                                @else
                                    <span class="label label-default">Client</span>
                                @endif
                            </td>
                            <td>{{ $key->user->username ?? '—' }}</td>
                            <td>{{ $key->memo ?? '—' }}</td>
                            <td>{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : '—' }}</td>
                            <td>{{ $key->created_at->toDateString() }}</td>
                            <td>
                                <form method="POST" action="{{ route('root.api_keys.revoke', $key->identifier) }}" style="display:inline;">
                                    {{ csrf_field() }}{{ method_field('DELETE') }}
                                    <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Revoke key {{ $key->identifier }}?')">
                                        <i class="fa fa-trash-o"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $keys->links() }}</div>
        </div>
    </div>
</div>
@endsection

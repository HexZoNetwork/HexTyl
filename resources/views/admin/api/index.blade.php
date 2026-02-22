@extends('layouts.admin')

@section('title')
    Application API
@endsection

@section('content-header')
    <h1>Application API<small>Control access credentials for managing this Panel via the API.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Application API</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-info" style="border-left: 4px solid #06b0d1;">
                <strong><i class="fa fa-search"></i> PTLA Quick Finder:</strong>
                cari server OFFLINE pakai endpoint
                <code>GET /api/application/servers/offline</code>
                atau
                <code>GET /api/application/servers?state=off</code>.
            </div>
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Credentials List</h3>
                    <div class="box-tools">
                        <a href="{{ route('docs.index') }}" class="btn btn-sm btn-default" target="_blank">Open /doc</a>
                        <a href="{{ route('admin.api.new') }}" class="btn btn-sm btn-primary">Create New</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Memo</th>
                                <th>Last Used</th>
                                <th>Created</th>
                                <th>Created by</th>
                                <th>Access Profile</th>
                                <th style="width:40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($keys as $key)
                            <tr>
                                <td><code>
                                    @if (Auth::user()->is($key->user))
                                        {{ $key->identifier . decrypt($key->token) }}
                                    @else
                                        {{ $key->identifier . '****' }}
                                    @endif
                                </code></td>
                                <td>{{ $key->memo }}</td>
                                <td>
                                    @if(!is_null($key->last_used_at))
                                        @datetimeHuman($key->last_used_at)
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td>@datetimeHuman($key->created_at)</td>
                                <td>
                                    <a href="{{ route('admin.users.view', $key->user->id) }}">{{ $key->user->username }}</a>
                                </td>
                                <td>
                                    @php
                                        $profiles = collect(\Pterodactyl\Services\Acl\Api\AdminAcl::getResourceList())
                                            ->map(function (string $resource) use ($key) {
                                                $permission = (int) data_get($key, 'r_' . $resource, \Pterodactyl\Services\Acl\Api\AdminAcl::NONE);
                                                if ($permission === \Pterodactyl\Services\Acl\Api\AdminAcl::READ_WRITE) {
                                                    return [
                                                        'style' => 'label-primary',
                                                        'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $resource)) . ' W',
                                                    ];
                                                }

                                                if ($permission === \Pterodactyl\Services\Acl\Api\AdminAcl::READ) {
                                                    return [
                                                        'style' => 'label-info',
                                                        'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $resource)) . ' R',
                                                    ];
                                                }

                                                return null;
                                            })
                                            ->filter()
                                            ->values();
                                    @endphp

                                    @if($profiles->isEmpty())
                                        <span class="label label-default">No Access</span>
                                    @else
                                        @foreach($profiles as $profile)
                                            <span class="label {{ $profile['style'] }}" style="display:inline-block; margin:2px 4px 2px 0;">
                                                {{ $profile['label'] }}
                                            </span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="#" data-action="revoke-key" data-attr="{{ $key->identifier }}">
                                        <i class="fa fa-trash-o text-danger"></i>
                                    </a>
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

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function() {
            $('[data-action="revoke-key"]').click(function (event) {
                var self = $(this);
                event.preventDefault();
                swal({
                    type: 'error',
                    title: 'Revoke API Key',
                    text: 'Once this API key is revoked any applications currently using it will stop working.',
                    showCancelButton: true,
                    allowOutsideClick: true,
                    closeOnConfirm: false,
                    confirmButtonText: 'Revoke',
                    confirmButtonColor: '#d9534f',
                    showLoaderOnConfirm: true
                }, function () {
                    $.ajax({
                        method: 'DELETE',
                        url: '/admin/api/revoke/' + self.data('attr'),
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).done(function () {
                        swal({
                            type: 'success',
                            title: '',
                            text: 'API Key has been revoked.'
                        });
                        self.parent().parent().slideUp();
                    }).fail(function (jqXHR) {
                        console.error(jqXHR);
                        swal({
                            type: 'error',
                            title: 'Whoops!',
                            text: 'An error occurred while attempting to revoke this key.'
                        });
                    });
                });
            });
        });
    </script>
@endsection

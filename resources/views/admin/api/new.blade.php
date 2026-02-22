@extends('layouts.admin')

@section('title')
    Application API
@endsection

@section('content-header')
    <h1>Application API<small>Create a new application API key.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.api.index') }}">Application API</a></li>
        <li class="active">New Credentials</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <form method="POST" action="{{ route('admin.api.new') }}" id="ptlaCreateForm">
            <div class="col-sm-8 col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Select Scope Grants</h3>
                        <div class="box-tools">
                            <button type="button" class="btn btn-xs btn-primary" id="set-all-scopes">
                                <i class="fa fa-check-square-o"></i> Select All Allowed
                            </button>
                            <button type="button" class="btn btn-xs btn-default" id="clear-all-scopes">
                                <i class="fa fa-square-o"></i> Clear
                            </button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <div style="padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,.04);">
                            <span class="label label-success">Role-Allowed</span>
                            <span class="label label-default" style="margin-left:6px;">Not in Your Role</span>
                            <span class="label label-primary" style="margin-left:6px;">Write Grant</span>
                            <span class="label label-info" style="margin-left:6px;">Read Grant</span>
                        </div>
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th style="width: 60px;">Pick</th>
                                <th style="width: 280px;">Scope</th>
                                <th>API Grants</th>
                                <th style="width: 140px;">Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($scopeCatalog as $scope)
                                @php($isAssignable = (bool) ($scope['assignable'] ?? false))
                                <tr class="{{ $isAssignable ? '' : 'text-muted' }}">
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="ptla-scope-checkbox"
                                            name="scopes[]"
                                            value="{{ $scope['scope'] }}"
                                            {{ $isAssignable ? '' : 'disabled' }}
                                        >
                                    </td>
                                    <td>
                                        <code>{{ $scope['scope'] }}</code>
                                        <div class="small text-muted">{{ $scope['label'] ?? $scope['scope'] }}</div>
                                    </td>
                                    <td>
                                        @foreach(($scope['grants'] ?? []) as $grant)
                                            @php($isWrite = (int) ($grant['permission'] ?? 0) === \Pterodactyl\Services\Acl\Api\AdminAcl::WRITE)
                                            <span class="label {{ $isWrite ? 'label-primary' : 'label-info' }}" style="display:inline-block; margin:2px 4px 2px 0;">
                                                {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($grant['resource'] ?? 'resource'))) }}
                                                {{ $isWrite ? 'Write' : 'Read' }}
                                            </span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if($isAssignable)
                                            <span class="label label-success">Allowed</span>
                                        @else
                                            <span class="label label-default">Role Missing</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No scope catalog available.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 col-xs-12">
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="alert alert-info" style="margin-bottom: 12px;">
                            <i class="fa fa-shield"></i>
                            Key permissions are generated from selected scopes and capped by your current role.
                        </div>
                        <div class="form-group">
                            <label class="control-label" for="memoField">Description <span class="field-required"></span></label>
                            <input id="memoField" type="text" name="memo" class="form-control">
                        </div>
                        <p class="text-muted" style="margin-bottom: 0;">
                            Selected scopes: <strong id="selected-scope-count">0</strong>
                        </p>
                        @if(!$canCreateAny)
                            <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0;">
                                <i class="fa fa-exclamation-triangle"></i>
                                Your current role does not allow assigning PTLA scopes.
                            </div>
                        @endif
                    </div>
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-success btn-sm pull-right" {{ !$canCreateAny ? 'disabled' : '' }}>
                            Create Credentials
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            const checkboxes = Array.from(document.querySelectorAll('.ptla-scope-checkbox'));
            const countNode = document.getElementById('selected-scope-count');
            const selectAllButton = document.getElementById('set-all-scopes');
            const clearAllButton = document.getElementById('clear-all-scopes');

            const updateScopeCount = () => {
                if (!countNode) {
                    return;
                }

                const selected = checkboxes.filter((el) => el.checked).length;
                countNode.textContent = String(selected);
            };

            selectAllButton?.addEventListener('click', () => {
                checkboxes.forEach((el) => {
                    if (!el.disabled) {
                        el.checked = true;
                    }
                });
                updateScopeCount();
            });

            clearAllButton?.addEventListener('click', () => {
                checkboxes.forEach((el) => {
                    el.checked = false;
                });
                updateScopeCount();
            });

            checkboxes.forEach((el) => {
                el.addEventListener('change', updateScopeCount);
            });

            updateScopeCount();
        })();
    </script>
@endsection

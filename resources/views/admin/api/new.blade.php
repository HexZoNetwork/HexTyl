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
        <form method="POST" action="{{ route('admin.api.new') }}">
            <div class="col-sm-8 col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Select Permissions (Safe Mode)</h3>
                        <div class="box-tools">
                            <button type="button" class="btn btn-xs btn-primary" id="set-all-read">Set All Read</button>
                            <button type="button" class="btn btn-xs btn-default" id="set-all-none">Set All None</button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            @foreach($resources as $resource)
                                <tr>
                                    <td class="col-sm-3 strong">{{ str_replace('_', ' ', title_case($resource)) }}</td>
                                    <td class="col-sm-4 text-center">
                                        <label class="btn btn-xs {{ ($resourceCaps[$resource] ?? 0) >= $permissions['r'] ? 'btn-primary' : 'btn-default disabled' }}" style="min-width:100px;">
                                            <input type="radio" id="r_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['r'] }}" style="display:none;" {{ ($resourceCaps[$resource] ?? 0) < $permissions['r'] ? 'disabled' : '' }}>
                                            Read
                                        </label>
                                    </td>
                                    <td class="col-sm-4 text-center">
                                        <label class="btn btn-xs btn-default" style="min-width:100px;">
                                            <input type="radio" id="n_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['n'] }}" style="display:none;" checked>
                                            None
                                        </label>
                                    </td>
                                    <td class="col-sm-1 text-center">
                                        @if(($resourceCaps[$resource] ?? 0) === 0)
                                            <span class="label label-warning">No Scope</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-sm-4 col-xs-12">
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label" for="memoField">Description <span class="field-required"></span></label>
                            <input id="memoField" type="text" name="memo" class="form-control">
                        </div>
                        <p class="text-muted">Permissions are capped by your admin scopes and restricted to <strong>Read/None</strong> for safer API access.</p>
                    </div>
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-success btn-sm pull-right">Create Credentials</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        document.getElementById('set-all-read')?.addEventListener('click', function () {
            document.querySelectorAll('input[id^="r_"]:not(:disabled)').forEach(function (el) {
                el.checked = true;
            });
        });

        document.getElementById('set-all-none')?.addEventListener('click', function () {
            document.querySelectorAll('input[id^="n_"]').forEach(function (el) {
                el.checked = true;
            });
        });
    </script>
@endsection

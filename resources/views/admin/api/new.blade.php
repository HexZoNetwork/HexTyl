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
                        <h3 class="box-title">Select Permissions</h3>
                        <div class="box-tools">
                            <button type="button" class="btn btn-xs btn-primary" id="set-all-write"><i class="fa fa-bolt"></i> Set All Write</button>
                            <button type="button" class="btn btn-xs btn-success" id="set-all-read"><i class="fa fa-check"></i> Set All Read</button>
                            <button type="button" class="btn btn-xs btn-warning" id="set-all-none"><i class="fa fa-ban"></i> Set All None</button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <div style="padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,.04);">
                            <span class="label label-primary">Selected Write</span>
                            <span class="label label-success">Selected Read</span>
                            <span class="label label-warning" style="margin-left:6px;">Selected None</span>
                            <span class="label label-default" style="margin-left:6px;">Unselected</span>
                        </div>
                        <table class="table table-hover">
                            @foreach($resources as $resource)
                                <tr>
                                    <td class="col-sm-3 strong">{{ str_replace('_', ' ', title_case($resource)) }}</td>
                                    <td class="col-sm-4 text-center">
                                        <label class="btn btn-xs api-scope-btn api-scope-write {{ ($resourceCaps[$resource] ?? 0) >= $permissions['w'] ? 'btn-default' : 'btn-default disabled' }}" style="min-width:100px;" for="w_{{ $resource }}">
                                            <input type="radio" id="w_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['w'] }}" style="display:none;" {{ ($resourceCaps[$resource] ?? 0) < $permissions['w'] ? 'disabled' : '' }}>
                                            Write
                                        </label>
                                    </td>
                                    <td class="col-sm-4 text-center">
                                        <label class="btn btn-xs api-scope-btn api-scope-read {{ ($resourceCaps[$resource] ?? 0) >= $permissions['r'] ? 'btn-default' : 'btn-default disabled' }}" style="min-width:100px;" for="r_{{ $resource }}">
                                            <input type="radio" id="r_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['r'] }}" style="display:none;" {{ ($resourceCaps[$resource] ?? 0) < $permissions['r'] ? 'disabled' : '' }}>
                                            Read
                                        </label>
                                    </td>
                                    <td class="col-sm-4 text-center">
                                        <label class="btn btn-xs api-scope-btn api-scope-none btn-default" style="min-width:100px;" for="n_{{ $resource }}">
                                            <input type="radio" id="n_{{ $resource }}" name="r_{{ $resource }}" value="{{ $permissions['n'] }}" style="display:none;" checked>
                                            None
                                        </label>
                                    </td>
                                    <td class="col-sm-2 text-center">
                                        @if(($resourceCaps[$resource] ?? 0) === 0)
                                            <span class="label label-warning">No Scope</span>
                                        @elseif(($resourceCaps[$resource] ?? 0) === 1)
                                            <span class="label label-info">Read Only</span>
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
                        <div class="alert alert-info" style="margin-bottom:12px;">
                            <i class="fa fa-lock"></i>
                            PTLA key ini privya cuma (<strong>{{ auth()->user()->username }}</strong>) yg bs liat
                        </div>
                        <div class="form-group">
                            <label class="control-label" for="memoField">Description <span class="field-required"></span></label>
                            <input id="memoField" type="text" name="memo" class="form-control">
                        </div>
                        <p class="text-muted">kalo mau read/writ ada scopenya</p>
                        @if(!$canCreateAny)
                            <div class="alert alert-warning" style="margin-bottom:0;">
                                <i class="fa fa-exclamation-triangle"></i>
                                Lu Ga ada akses tlol
                            </div>
                        @endif
                    </div>
                    <div class="box-footer">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-success btn-sm pull-right" {{ !$canCreateAny ? 'disabled' : '' }}>Create Credentials</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        const refreshScopeButtonState = () => {
            document.querySelectorAll('tr').forEach((row) => {
                const radios = row.querySelectorAll('input[type="radio"][name^="r_"]');
                if (!radios.length) return;

                const readRadio = row.querySelector('input[id^="r_"]');
                const writeRadio = row.querySelector('input[id^="w_"]');
                const noneRadio = row.querySelector('input[id^="n_"]');
                const readLabel = readRadio ? row.querySelector('label[for="' + readRadio.id + '"]') : null;
                const writeLabel = writeRadio ? row.querySelector('label[for="' + writeRadio.id + '"]') : null;
                const noneLabel = noneRadio ? row.querySelector('label[for="' + noneRadio.id + '"]') : null;

                if (writeLabel) {
                    writeLabel.classList.remove('btn-primary', 'btn-default', 'btn-warning', 'btn-success');
                    if (writeRadio?.disabled) {
                        writeLabel.classList.add('btn-default');
                    } else {
                        writeLabel.classList.add(writeRadio?.checked ? 'btn-primary' : 'btn-default');
                    }
                }

                if (readLabel) {
                    readLabel.classList.remove('btn-primary', 'btn-success', 'btn-default', 'btn-warning');
                    if (readRadio?.disabled) {
                        readLabel.classList.add('btn-default');
                    } else {
                        readLabel.classList.add(readRadio?.checked ? 'btn-success' : 'btn-default');
                    }
                }

                if (noneLabel) {
                    noneLabel.classList.remove('btn-success', 'btn-default', 'btn-warning');
                    noneLabel.classList.add(noneRadio?.checked ? 'btn-warning' : 'btn-default');
                }
            });
        };

        document.querySelectorAll('input[type="radio"][name^="r_"]').forEach((el) => {
            el.addEventListener('change', refreshScopeButtonState);
        });

        document.getElementById('set-all-write')?.addEventListener('click', function () {
            document.querySelectorAll('input[id^="w_"]:not(:disabled)').forEach(function (el) {
                el.checked = true;
            });
            refreshScopeButtonState();
        });

        document.getElementById('set-all-read')?.addEventListener('click', function () {
            document.querySelectorAll('input[id^="r_"]:not(:disabled)').forEach(function (el) {
                el.checked = true;
            });
            refreshScopeButtonState();
        });

        document.getElementById('set-all-none')?.addEventListener('click', function () {
            document.querySelectorAll('input[id^="n_"]').forEach(function (el) {
                el.checked = true;
            });
            refreshScopeButtonState();
        });

        refreshScopeButtonState();
    </script>
@endsection

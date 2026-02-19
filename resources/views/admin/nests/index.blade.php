@extends('layouts.admin')

@section('title')
    Nests
@endsection

@section('content-header')
    <h1>Nests<small>All nests currently available on this system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Nests</li>
    </ol>
@endsection

@section('content')
@php($canWriteNest = Auth::user()->isRoot() || Auth::user()->hasScope('server.update'))
<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-danger">
            Eggs are a powerful feature of Pterodactyl Panel that allow for extreme flexibility and configuration. Please note that while powerful, modifying an egg wrongly can very easily brick your servers and cause more problems. Please avoid editing our default eggs — those provided by <code>support@pterodactyl.io</code> — unless you are absolutely sure of what you are doing.
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Configured Nests</h3>
                @if($canWriteNest)
                    <div class="box-tools">
                        <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#importServiceOptionModal" role="button"><i class="fa fa-upload"></i> Import Egg</a>
                        <a href="{{ route('admin.nests.new') }}" class="btn btn-primary btn-sm">Create New</a>
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
                            <th class="text-center">Eggs</th>
                            <th class="text-center">Servers</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($nests as $nest)
                        <tr>
                            <td><code>{{ $nest->id }}</code></td>
                            <td><a href="{{ route('admin.nests.view', $nest->id) }}" data-toggle="tooltip" data-placement="right" title="{{ $nest->author }}"><strong>{{ $nest->name }}</strong></a></td>
                            <td class="text-muted">{{ $nest->description }}</td>
                            <td class="text-center"><span class="label label-info">{{ $nest->eggs_count }}</span></td>
                            <td class="text-center"><span class="label label-success">{{ $nest->servers_count }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@if($canWriteNest)
<div class="modal fade" tabindex="-1" role="dialog" id="importServiceOptionModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Import an Egg</h4>
            </div>
            <form action="{{ route('admin.nests.egg.import') }}" enctype="multipart/form-data" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="control-label" for="pImportFile">Egg File <span class="field-required"></span></label>
                        <div>
                            <input id="pImportFile" type="file" name="import_file" class="form-control" accept="application/json" />
                            <p class="small text-muted">Select the <code>.json</code> file for the new egg that you wish to import.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label" for="pImportToNest">Associated Nest <span class="field-required"></span></label>
                        <div>
                            <select id="pImportToNest" name="import_to_nest">
                                @foreach($nests as $nest)
                                   <option value="{{ $nest->id }}">{{ $nest->name }} &lt;{{ $nest->author }}&gt;</option>
                                @endforeach
                            </select>
                            <p class="small text-muted">Select the nest that this egg will be associated with from the dropdown. If you wish to associate it with a new nest you will need to create that nest before continuing.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{ csrf_field() }}
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('footer-scripts')
    @parent
    <script>
        @if($canWriteNest)
        $(document).ready(function() {
            $('#pImportToNest').select2();
        });
        @endif
    </script>
@endsection

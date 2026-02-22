@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Configuration
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Your daemon configuration file.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Configuration</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Configuration File</h3>
            </div>
            <div class="box-body">
                <pre class="no-margin">{{ $node->getYamlConfiguration() }}</pre>
            </div>
            <div class="box-footer">
                <p class="no-margin">This file should be placed in your daemon's root directory (usually <code>/etc/pterodactyl</code>) in a file called <code>config.yml</code>.</p>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Auto-Deploy</h3>
            </div>
            <div class="box-body">
                <p class="text-muted small">
                    Use the button below to generate a custom deployment command that can be used to configure
                    HexWings on the target server with a single command.
                </p>
            </div>
            <div class="box-footer">
                <button type="button" id="configTokenBtn" class="btn btn-sm btn-default" style="width:100%;">Generate Token</button>
            </div>
        </div>

        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Auto Bootstrap via SSH</h3>
            </div>
            <div class="box-body">
                <div class="form-group">
                    <label for="bootstrapHost">VPS Host / IP</label>
                    <input type="text" id="bootstrapHost" class="form-control" placeholder="203.0.113.10">
                </div>
                <div class="form-group">
                    <label for="bootstrapPort">SSH Port</label>
                    <input type="number" id="bootstrapPort" class="form-control" value="22" min="1" max="65535">
                </div>
                <div class="form-group">
                    <label for="bootstrapUser">SSH Username</label>
                    <input type="text" id="bootstrapUser" class="form-control" value="root" placeholder="root">
                </div>
                <div class="form-group">
                    <label for="bootstrapAuthType">Auth Type</label>
                    <select id="bootstrapAuthType" class="form-control">
                        <option value="password">Password</option>
                        <option value="private_key">Private Key</option>
                    </select>
                </div>
                <div class="form-group" id="bootstrapPasswordWrap">
                    <label for="bootstrapPassword">SSH Password</label>
                    <input type="password" id="bootstrapPassword" class="form-control" placeholder="Your SSH password">
                </div>
                <div class="form-group" id="bootstrapKeyWrap" style="display:none;">
                    <label for="bootstrapKey">Private Key (PEM/OpenSSH)</label>
                    <textarea id="bootstrapKey" class="form-control" rows="5" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                </div>
                <div class="checkbox no-margin-bottom">
                    <label>
                        <input type="checkbox" id="bootstrapStrictHostKey" value="1">
                        Enforce strict host key checking
                    </label>
                </div>
            </div>
            <div class="box-footer">
                <button type="button" id="bootstrapNodeBtn" class="btn btn-sm btn-info" style="width:100%;">Run Auto Bootstrap</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#configTokenBtn').on('click', function (event) {
        $.ajax({
            method: 'POST',
            url: '{{ route('admin.nodes.view.configuration.token', $node->id) }}',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).done(function (data) {
            var configureCmd = data.configure_command || ('cd /etc/pterodactyl && sudo /usr/local/bin/wings configure --panel-url {{ config('app.url') }} --token ' + data.token + ' --node ' + data.node + '{{ config('app.debug') ? ' --allow-insecure' : '' }}');
            var bootstrapScript = data.bootstrap_script || '';
            swal({
                type: 'success',
                title: 'Bootstrap Command Ready',
                text:
                    '<p><strong>Quick Configure</strong><br /><small><pre>' + $('<div>').text(configureCmd).html() + '</pre></small></p>' +
                    '<p><strong>Full Auto Install + Setup (Docker + HexWings + systemd)</strong><br /><small><pre style="max-height:300px; overflow:auto;">' + $('<div>').text(bootstrapScript).html() + '</pre></small></p>',
                html: true
            })
        }).fail(function () {
            swal({
                title: 'Error',
                text: 'Something went wrong creating your token.',
                type: 'error'
            });
        });
    });

    $('#bootstrapAuthType').on('change', function () {
        var mode = $(this).val();
        $('#bootstrapPasswordWrap').toggle(mode === 'password');
        $('#bootstrapKeyWrap').toggle(mode === 'private_key');
    }).trigger('change');

    $('#bootstrapNodeBtn').on('click', function () {
        var payload = {
            host: $('#bootstrapHost').val(),
            port: $('#bootstrapPort').val(),
            username: $('#bootstrapUser').val(),
            auth_type: $('#bootstrapAuthType').val(),
            password: $('#bootstrapPassword').val(),
            private_key: $('#bootstrapKey').val(),
            strict_host_key: $('#bootstrapStrictHostKey').is(':checked') ? 1 : 0,
        };

        swal({
            title: 'Run Bootstrap?',
            text: 'This will execute install/setup commands over SSH on the target VPS.',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, run it'
        }, function (confirmed) {
            if (!confirmed) return;

            $.ajax({
                method: 'POST',
                url: '{{ route('admin.nodes.view.configuration.bootstrap', $node->id) }}',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: payload
            }).done(function (response) {
                swal({
                    type: 'success',
                    title: 'Bootstrap Success',
                    text: '<p>' + $('<div>').text(response.message || 'Done').html() + '</p>'
                        + '<p><strong>STDOUT</strong><br /><small><pre style="max-height:220px; overflow:auto;">' + $('<div>').text(response.stdout || '').html() + '</pre></small></p>'
                        + '<p><strong>STDERR</strong><br /><small><pre style="max-height:160px; overflow:auto;">' + $('<div>').text(response.stderr || '').html() + '</pre></small></p>',
                    html: true
                });
            }).fail(function (xhr) {
                var message = 'Bootstrap failed.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                var stdout = (xhr && xhr.responseJSON && xhr.responseJSON.stdout) ? xhr.responseJSON.stdout : '';
                var stderr = (xhr && xhr.responseJSON && xhr.responseJSON.stderr) ? xhr.responseJSON.stderr : '';
                swal({
                    type: 'error',
                    title: 'Bootstrap Failed',
                    text: '<p>' + $('<div>').text(message).html() + '</p>'
                        + '<p><strong>STDOUT</strong><br /><small><pre style="max-height:220px; overflow:auto;">' + $('<div>').text(stdout).html() + '</pre></small></p>'
                        + '<p><strong>STDERR</strong><br /><small><pre style="max-height:160px; overflow:auto;">' + $('<div>').text(stderr).html() + '</pre></small></p>',
                    html: true
                });
            });
        });
    });
    </script>
@endsection

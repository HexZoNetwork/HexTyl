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
<style>
    .manual-scope-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        max-height: 260px;
        overflow: auto;
        padding: 6px;
        border: 1px solid #30363d;
        border-radius: 6px;
        background: #0f141b;
    }
    .manual-scope-btn {
        margin: 0;
        position: relative;
    }
    .manual-scope-btn input[type='checkbox'] {
        position: absolute !important;
        opacity: 0 !important;
        pointer-events: none !important;
        width: 0 !important;
        height: 0 !important;
        margin: 0 !important;
    }
</style>
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
                    <div class="form-group">
                        <label class="control-label">Role Mode <span class="field-required">*</span></label>
                        <input type="hidden" name="mode" id="roleModeInput" value="{{ old('mode', 'template') }}">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" id="modeTemplateBtn" class="btn btn-sm {{ old('mode', 'template') === 'template' ? 'btn-info' : 'btn-default' }}">Template</button>
                            <button type="button" id="modeManualBtn" class="btn btn-sm {{ old('mode') === 'manual' ? 'btn-info' : 'btn-default' }}">Manual</button>
                        </div>
                    </div>
                    <div class="form-group" id="templateSection">
                        <label class="control-label">Template <span class="field-required">*</span></label>
                        <input type="hidden" name="template" id="templateInput" value="{{ old('template', 'viewer') }}">
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            @foreach($templates as $key => $template)
                                <button type="button"
                                        class="btn btn-sm role-template-btn {{ old('template', 'viewer') === $key ? 'btn-info' : 'btn-default' }}"
                                        data-template="{{ $key }}"
                                        data-description="{{ $template['description'] }}"
                                        data-scopes="{{ implode(', ', $template['scopes']) }}">
                                    {{ $template['label'] }}
                                </button>
                            @endforeach
                        </div>
                        <p class="text-muted small" id="templateDescription" style="margin-top:8px;"></p>
                        <p class="text-muted small" id="templateScopes"></p>
                    </div>
                    <div class="form-group" id="manualSection" style="display:none;">
                        <label class="control-label">Manual Scopes</label>
                        <p class="text-muted small">Klik tombol scope untuk ON/OFF. Tidak perlu input keyword manual.</p>
                        <div id="manualScopeWrap" class="manual-scope-wrap">
                            @foreach($availableScopes as $scope)
                                <label class="btn btn-xs manual-scope-btn {{ in_array($scope, old('scopes', [])) ? 'btn-success' : 'btn-default' }}">
                                    <input type="checkbox" name="scopes[]" value="{{ $scope }}" {{ in_array($scope, old('scopes', [])) ? 'checked' : '' }}>
                                    <code>{{ $scope }}</code>
                                </label>
                            @endforeach
                        </div>
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
                <p>Role bisa dibuat dari <strong>template</strong> atau <strong>manual scope picker</strong>.</p>
                <p>System roles (<em>Root, Admin, User</em>) tetap protected dan immutable.</p>
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

@section('footer-scripts')
    @parent
    <script>
        (function () {
            const roleModeInput = document.getElementById('roleModeInput');
            const modeTemplateBtn = document.getElementById('modeTemplateBtn');
            const modeManualBtn = document.getElementById('modeManualBtn');
            const templateSection = document.getElementById('templateSection');
            const manualSection = document.getElementById('manualSection');
            const input = document.getElementById('templateInput');
            const desc = document.getElementById('templateDescription');
            const scopes = document.getElementById('templateScopes');
            const buttons = document.querySelectorAll('.role-template-btn');
            const manualButtons = document.querySelectorAll('.manual-scope-btn');

            const render = (activeBtn) => {
                buttons.forEach((btn) => btn.classList.remove('btn-info'));
                buttons.forEach((btn) => btn.classList.add('btn-default'));
                activeBtn.classList.remove('btn-default');
                activeBtn.classList.add('btn-info');
                input.value = activeBtn.getAttribute('data-template');
                desc.textContent = activeBtn.getAttribute('data-description');
                scopes.textContent = 'Scopes: ' + activeBtn.getAttribute('data-scopes');
            };

            const setMode = (mode) => {
                roleModeInput.value = mode;
                if (mode === 'template') {
                    modeTemplateBtn.classList.add('btn-info');
                    modeTemplateBtn.classList.remove('btn-default');
                    modeManualBtn.classList.remove('btn-info');
                    modeManualBtn.classList.add('btn-default');
                    templateSection.style.display = '';
                    manualSection.style.display = 'none';
                } else {
                    modeManualBtn.classList.add('btn-info');
                    modeManualBtn.classList.remove('btn-default');
                    modeTemplateBtn.classList.remove('btn-info');
                    modeTemplateBtn.classList.add('btn-default');
                    templateSection.style.display = 'none';
                    manualSection.style.display = '';
                }
            };

            buttons.forEach((btn) => {
                btn.addEventListener('click', () => render(btn));
            });

            manualButtons.forEach((label) => {
                label.addEventListener('click', function (event) {
                    const checkbox = label.querySelector('input[type="checkbox"]');
                    if (!checkbox || event.target.tagName === 'INPUT') {
                        return;
                    }

                    event.preventDefault();
                    checkbox.checked = !checkbox.checked;
                    label.classList.toggle('btn-success', checkbox.checked);
                    label.classList.toggle('btn-default', !checkbox.checked);
                });
            });

            const current = document.querySelector('.role-template-btn.btn-info') || buttons[0];
            if (current) render(current);

            modeTemplateBtn.addEventListener('click', function () { setMode('template'); });
            modeManualBtn.addEventListener('click', function () { setMode('manual'); });
            setMode(roleModeInput.value === 'manual' ? 'manual' : 'template');
        })();
    </script>
@endsection

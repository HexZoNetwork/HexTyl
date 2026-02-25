@extends('layouts.root')

@section('title') Root — All Users @endsection
@section('content-header')
    <h1>All Users <small>{{ $users->total() }} total</small></h1>
@endsection

@section('content')
<style>
    .root-users-rework .box {
        border-top: 0 !important;
        border: 1px solid #263b51;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(180deg, #0f1a2a 0%, #101b2a 100%);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
    }
    .root-users-rework .box-header {
        border-bottom: 1px solid #20384e;
        background: rgba(17, 30, 46, 0.92);
    }
    .root-users-rework .box-title {
        color: #d9e8f6;
        font-weight: 700;
    }
    .root-users-rework .badge {
        border-radius: 999px;
        padding: 4px 8px;
    }
    .root-users-rework .table > thead > tr > th {
        color: #93afc6;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #294057;
        background: #12253a;
    }
    .root-users-rework .table > tbody > tr > td {
        border-top: 1px solid #1e3448;
        color: #d0deea;
        vertical-align: middle;
    }
    .root-users-rework .table > tbody > tr:hover {
        background: rgba(48, 130, 218, 0.08);
    }
    .root-users-action {
        display: inline-flex;
        gap: 4px;
        align-items: center;
        flex-wrap: nowrap;
    }
    .quick-modal-note {
        color: #8ca0b5;
        font-size: 12px;
        margin-top: 8px;
    }
</style>
<div class="row root-users-rework">
    <div class="col-xs-12">
        <div class="root-toolbar">
            <p class="root-toolbar-title"><i class="fa fa-search"></i> Quick Search Users</p>
            <div class="root-toolbar-controls">
                <input type="text" id="rootUsersSearch" class="form-control root-search" placeholder="Find by username, email, role, status...">
                <form method="POST" action="{{ route('root.users.create_tester') }}" style="display:inline;">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-warning btn-sm"><i class="fa fa-user-plus"></i> Create Tester</button>
                </form>
                <button type="button" class="btn btn-default btn-sm" id="rootUsersClearSearch"><i class="fa fa-times"></i> Clear</button>
            </div>
        </div>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">User Accounts &nbsp;<span class="badge" style="background:#06b0d1;">{{ $users->total() }}</span></h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover" id="rootUsersTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Servers</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td>{{ $user->id }} @if($user->isRoot()) <span class="label label-danger">ROOT</span>@endif</td>
                            <td><a href="{{ route('admin.users.view', $user->id) }}">{{ $user->username }}</a></td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($user->isRoot())
                                    Root
                                @else
                                    {{ optional($user->role)->name ?? ($user->root_admin ? 'Admin' : 'User') }}
                                @endif
                            </td>
                            <td><span class="badge" style="background:#06b0d1;">{{ $user->servers_count }}</span></td>
                            <td>
                                @if($user->suspended)
                                    <span class="label label-danger">Suspended</span>
                                @else
                                    <span class="label label-success">Active</span>
                                @endif
                            </td>
                            <td>
                                <span class="root-users-action">
                                <a href="{{ route('admin.users.view', $user->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></a>
                                @if(!$user->isRoot())
                                <a href="#" class="btn btn-xs btn-info js-open-quick-server"
                                   data-base-url="{{ route('root.users.quick_server.get', $user->id) }}"
                                   data-username="{{ $user->username }}"
                                   title="Quick bulk server create">
                                    <i class="fa fa-bolt"></i>
                                </a>
                                @endif
                                @if(!$user->isRoot())
                                <form method="POST" action="{{ route('root.users.toggle_suspension', $user->id) }}" style="display:inline;">
                                    {{ csrf_field() }}
                                    <button type="submit" class="btn btn-xs {{ $user->suspended ? 'btn-success' : 'btn-warning' }}"
                                            onclick="return confirm('Toggle suspension for {{ $user->username }}?')">
                                        <i class="fa fa-{{ $user->suspended ? 'check' : 'ban' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('root.users.delete', $user->id) }}" style="display:inline;">
                                    {{ csrf_field() }}
                                    <button type="submit" class="btn btn-xs btn-danger"
                                            onclick="return confirm('Delete user {{ $user->username }} permanently? (must have no servers)')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">{{ $users->links() }}</div>
        </div>
        <div class="root-empty-state" id="rootUsersEmptyState" style="display:none; margin-top:10px;">
            <i class="fa fa-search"></i> No users matched your quick search on this page.
        </div>
    </div>
</div>
<div class="modal fade" id="quickServerModal" tabindex="-1" role="dialog" aria-labelledby="quickServerModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="background:#111c2d; border:1px solid #2d4560;">
            <div class="modal-header" style="border-bottom:1px solid #2d4560;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#d0deea;"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="quickServerModalLabel" style="color:#d9e8f6;">Quick Bulk Server Create</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="control-label" for="quickServerTarget">Target User</label>
                    <input type="text" id="quickServerTarget" class="form-control" value="" readonly>
                </div>
                <div class="form-group">
                    <label class="control-label" for="quickServerCount">Jumlah Server (1-50)</label>
                    <input type="number" min="1" max="50" id="quickServerCount" class="form-control" value="1">
                </div>
                <div class="form-group">
                    <label class="control-label" for="quickServerEggId">Egg</label>
                    <input type="number" min="1" id="quickServerEggId" class="form-control" placeholder="Kosong = auto default egg">
                </div>
                <p class="quick-modal-note">
                    Profile quick create: unlimited CPU/RAM/Disk. Role tester otomatis visibility public.
                </p>
            </div>
            <div class="modal-footer" style="border-top:1px solid #2d4560;">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info btn-sm" id="quickServerSubmitBtn"><i class="fa fa-bolt"></i> Create Servers</button>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var quickButtons = document.querySelectorAll('.js-open-quick-server');
        var modalEl = document.getElementById('quickServerModal');
        var target = document.getElementById('quickServerTarget');
        var countInput = document.getElementById('quickServerCount');
        var eggInput = document.getElementById('quickServerEggId');
        var submitBtn = document.getElementById('quickServerSubmitBtn');
        var closeButtons = modalEl ? modalEl.querySelectorAll('[data-dismiss="modal"], .close') : [];
        var currentBaseUrl = '';
        var backdrop = null;

        function openModal() {
            if (!modalEl) return;
            modalEl.style.display = 'block';
            modalEl.classList.add('in');
            modalEl.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');

            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade in';
            backdrop.addEventListener('click', closeModal);
            document.body.appendChild(backdrop);
        }

        function closeModal() {
            if (!modalEl) return;
            modalEl.style.display = 'none';
            modalEl.classList.remove('in');
            modalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            if (backdrop && backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
            }
            backdrop = null;
        }

        quickButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                currentBaseUrl = button.getAttribute('data-base-url') || '';
                target.value = button.getAttribute('data-username') || '';
                countInput.value = '1';
                eggInput.value = '';
                openModal();
            });
        });

        closeButtons.forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                closeModal();
            });
        });

        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var count = parseInt(String(countInput.value || '').trim(), 10);
                if (!Number.isFinite(count) || count < 1 || count > 50) {
                    alert('Jumlah server harus 1 sampai 50.');
                    countInput.focus();
                    return;
                }

                var eggRaw = String(eggInput.value || '').trim();
                if (eggRaw !== '') {
                    var eggId = parseInt(eggRaw, 10);
                    if (!Number.isFinite(eggId) || eggId < 1) {
                        alert('Egg ID harus angka positif.');
                        eggInput.focus();
                        return;
                    }
                }

                var params = ['count=' + encodeURIComponent(count)];
                if (eggRaw !== '') {
                    params.push('egg_id=' + encodeURIComponent(eggRaw));
                }

                var sep = currentBaseUrl.indexOf('?') === -1 ? '?' : '&';
                closeModal();
                window.location.href = currentBaseUrl + sep + params.join('&');
            });
        }

        var input = document.getElementById('rootUsersSearch');
        var clear = document.getElementById('rootUsersClearSearch');
        var table = document.getElementById('rootUsersTable');
        var empty = document.getElementById('rootUsersEmptyState');
        if (!input || !table || !empty) return;

        var rows = null;
        var ensureRows = function () {
            if (!rows) {
                rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            }
        };
        var sync = function () {
            ensureRows();
            var query = String(input.value || '').toLowerCase().trim();
            var visible = 0;
            rows.forEach(function (row) {
                var text = row.textContent.toLowerCase();
                var match = query === '' || text.indexOf(query) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            empty.style.display = visible === 0 ? '' : 'none';
        };

        input.addEventListener('input', sync);
        if (clear) {
            clear.addEventListener('click', function () {
                input.value = '';
                sync();
                input.focus();
            });
        }
    })();
</script>
@endsection

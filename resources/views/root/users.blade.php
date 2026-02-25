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
        animation: rootUsersFade 220ms ease both;
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
    @keyframes rootUsersFade {
        from { opacity: 0; transform: translateY(7px); }
        to { opacity: 1; transform: translateY(0); }
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
                                <a href="{{ route('admin.users.view', $user->id) }}" class="btn btn-xs btn-primary"><i class="fa fa-edit"></i></a>
                                @if(!$user->isRoot())
                                <a href="{{ route('root.users.quick_server.get', $user->id) }}" class="btn btn-xs btn-info"
                                   onclick="return rootQuickCreateBulk(event, this, '{{ $user->username }}')"
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
<script>
    function rootQuickCreateBulk(event, el, username) {
        event.preventDefault();
        var input = prompt('Jumlah server quick create untuk ' + username + ' (1-50):', '1');
        if (input === null) return false;

        var count = parseInt(String(input).trim(), 10);
        if (!Number.isFinite(count) || count < 1 || count > 50) {
            alert('Jumlah harus 1 sampai 50.');
            return false;
        }

        var eggInput = prompt('Egg ID khusus? (kosong = auto default)', '');
        if (eggInput === null) return false;
        var eggId = null;
        var eggTrimmed = String(eggInput).trim();
        if (eggTrimmed !== '') {
            eggId = parseInt(eggTrimmed, 10);
            if (!Number.isFinite(eggId) || eggId < 1) {
                alert('Egg ID harus angka positif.');
                return false;
            }
        }

        var confirmText = 'Create ' + count + ' quick server untuk ' + username + '?';
        if (eggId) {
            confirmText += ' (egg_id=' + eggId + ')';
        }
        if (!confirm(confirmText)) {
            return false;
        }

        var baseUrl = el.getAttribute('href') || '';
        var params = ['count=' + encodeURIComponent(count)];
        if (eggId) params.push('egg_id=' + encodeURIComponent(eggId));
        var sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
        window.location.href = baseUrl + sep + params.join('&');
        return false;
    }

    (function () {
        var input = document.getElementById('rootUsersSearch');
        var clear = document.getElementById('rootUsersClearSearch');
        var table = document.getElementById('rootUsersTable');
        var empty = document.getElementById('rootUsersEmptyState');
        if (!input || !table || !empty) return;

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var sync = function () {
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

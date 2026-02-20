<?php

/**
 * Contains all of the translation strings for different activity log
 * events. These should be keyed by the value in front of the colon (:)
 * in the event name. If there is no colon present, they should live at
 * the top level.
 */
return [
    'auth' => [
        'fail' => 'Gagal Login',
        'success' => 'Sukses',
        'password-reset' => 'Reset pw',
        'reset-password' => 'Lagi nge reset pw',
        'checkpoint' => 'ekhem 2fa',
        'recovery-token' => 'Pake recovery?',
        'token' => 'Done',
        'ip-blocked' => 'Ip Lu ke block :identifier',
        'sftp' => [
            'fail' => 'gagal log ftp srv',
        ],
    ],
    'user' => [
        'account' => [
            'email-changed' => 'email di ganti :old ke :new',
            'password-changed' => 'Ganti pw',
        ],
        'api-key' => [
            'create' => 'C Pltc :identifier',
            'delete' => 'Del Pltc :identifier',
        ],
        'ssh-key' => [
            'create' => 'add ssh key :fingerprint ke acc',
            'delete' => 'del ssh key :fingerprint dri acc',
        ],
        'two-factor' => [
            'create' => '2fa nyalin boz',
            'delete' => '2fa matiin boz',
        ],
    ],
    'server' => [
        'reinstall' => 'Lagi ngocok',
        'console' => [
            'command' => 'Runned ":command" di srv',
        ],
        'power' => [
            'start' => 'Dh jalan',
            'stop' => 'dh dead',
            'restart' => 'restart jir',
            'kill' => 'di bunuh ;-;',
        ],
        'backup' => [
            'download' => 'donlod :name backup',
            'delete' => 'del :name backup',
            'restore' => 'restore :name backup (file di del: :truncate)',
            'restore-complete' => 'sukses restore :name backup',
            'restore-failed' => 'sukses restore :name backup',
            'start' => 'sukses start bekup :name',
            'complete' => 'Done :name backup',
            'fail' => 'Gagal :name backup',
            'lock' => 'Sukses Rodok :name',
            'unlock' => 'Sukses crot :name',
        ],
        'database' => [
            'create' => 'add db :name',
            'rotate-password' => 'Password rotated for database :name',
            'delete' => 'Deleted database :name',
        ],
        'file' => [
            'compress_one' => 'Compressed :directory:file',
            'compress_other' => 'Compressed :count files in :directory',
            'read' => 'Viewed the contents of :file',
            'copy' => 'Created a copy of :file',
            'create-directory' => 'Created directory :directory:name',
            'decompress' => 'Decompressed :files in :directory',
            'delete_one' => 'Deleted :directory:files.0',
            'delete_other' => 'Deleted :count files in :directory',
            'download' => 'Downloaded :file',
            'pull' => 'Downloaded a remote file from :url to :directory',
            'rename_one' => 'Renamed :directory:files.0.from to :directory:files.0.to',
            'rename_other' => 'Renamed :count files in :directory',
            'write' => 'Wrote new content to :file',
            'upload' => 'Began a file upload',
            'uploaded' => 'Uploaded :directory:file',
        ],
        'sftp' => [
            'denied' => 'Blocked SFTP access due to permissions',
            'create_one' => 'Created :files.0',
            'create_other' => 'Created :count new files',
            'write_one' => 'Modified the contents of :files.0',
            'write_other' => 'Modified the contents of :count files',
            'delete_one' => 'Deleted :files.0',
            'delete_other' => 'Deleted :count files',
            'create-directory_one' => 'Created the :files.0 directory',
            'create-directory_other' => 'Created :count directories',
            'rename_one' => 'Renamed :files.0.from to :files.0.to',
            'rename_other' => 'Renamed or moved :count files',
        ],
        'allocation' => [
            'create' => 'Added :allocation to the server',
            'notes' => 'Updated the notes for :allocation from ":old" to ":new"',
            'primary' => 'Set :allocation as the primary server allocation',
            'delete' => 'Deleted the :allocation allocation',
        ],
        'schedule' => [
            'create' => 'Created the :name schedule',
            'update' => 'Updated the :name schedule',
            'execute' => 'Manually executed the :name schedule',
            'delete' => 'Deleted the :name schedule',
        ],
        'task' => [
            'create' => 'Created a new ":action" task for the :name schedule',
            'update' => 'Updated the ":action" task for the :name schedule',
            'delete' => 'Deleted a task for the :name schedule',
        ],
        'settings' => [
            'rename' => 'Renamed the server from :old to :new',
            'description' => 'Changed the server description from :old to :new',
        ],
        'startup' => [
            'edit' => 'Changed the :variable variable from ":old" to ":new"',
            'image' => 'Updated the Docker Image for the server from :old to :new',
        ],
        'subuser' => [
            'create' => 'Added :email as a subuser',
            'update' => 'Updated the subuser permissions for :email',
            'delete' => 'Removed :email as a subuser',
        ],
    ],
];

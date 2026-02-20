<?php

return [
    'location' => [
        'no_location_found' => 'Ga nemu aloc',
        'ask_short' => 'Short Code??',
        'ask_long' => 'Desk??',
        'created' => 'Yey Lokasi ewe baru= (:name) id= :id.',
        'deleted' => 'ke del',
    ],
    'user' => [
        'search_users' => 'Isi lah blok',
        'select_search_user' => 'id user di delete cari lagi sono',
        'deleted' => 'Ah Banned',
        'confirm_delete' => 'Yakin mau del?',
        'no_users_found' => 'Ga nemu tuh usernya',
        'multiple_found' => 'lah jir banyak bet akun yg make',
        'ask_admin' => 'Mau Admin?',
        'ask_email' => 'EmaiLLL??',
        'ask_username' => 'Nama?',
        'ask_name_first' => 'Nama Depan',
        'ask_name_last' => 'Nama Bool',
        'ask_password' => 'pw',
        'ask_password_tip' => 'kalau mau no pw tambah flag --no-password',
        'ask_password_help' => 'minimal 8 huruf jir',
        '2fa_help_text' => [
            'Ni buat off 2fa',
            'Kalo gamau ya ctrl + c aja',
        ],
        '2fa_disabled' => '2fa di offin :email.',
    ],
    'schedule' => [
        'output_line' => 'Lagi kerja `:schedule` (:hash).',
    ],
    'maintenance' => [
        'deleting_service_backup' => 'lgi remov bekup :file.',
    ],
    'server' => [
        'rebuild_failed' => 'rebuild di ":name" (#:id) node ":node" gagal: :message',
        'reinstall' => [
            'failed' => 'reinst di ":name" (#:id) node ":node" gagal: :message',
            'confirm' => 'Yakin mau reinst??',
        ],
        'power' => [
            'confirm' => 'Yakin mau :action ?',
            'action_failed' => 'Gagal ":name" di (#:id) node ":node" engroor: :message',
        ],
    ],
    'environment' => [
        'mail' => [
            'ask_smtp_host' => 'SMTP Host (e.g. smtp.gmail.com)',
            'ask_smtp_port' => 'SMTP Port',
            'ask_smtp_username' => 'SMTP Username',
            'ask_smtp_password' => 'SMTP Password',
            'ask_mailgun_domain' => 'Mailgun Domain',
            'ask_mailgun_endpoint' => 'Mailgun Endpoint',
            'ask_mailgun_secret' => 'Mailgun Secret',
            'ask_mandrill_secret' => 'Mandrill Secret',
            'ask_postmark_username' => 'Postmark API Key',
            'ask_driver' => 'Which driver should be used for sending emails?',
            'ask_mail_from' => 'Email address emails should originate from',
            'ask_mail_name' => 'Name that emails should appear from',
            'ask_encryption' => 'Encryption method to use',
        ],
    ],
];

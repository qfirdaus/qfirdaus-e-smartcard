<?php
/**
 * IQS FRAMEWORK CORE FILE
 *
 * READ ONLY for downstream project programmers.
 * Do not modify this file directly in template or cloned projects.
 * Custom changes must be implemented in project-specific files
 * or approved extension points.
 */declare(strict_types=1);

return [
    [
        'template_code' => 'STAFF_REMINDER_SKT',
        'template_name' => 'Staff Reminder SKT',
        'role_code' => 'staff',
        'category_code' => 'reminder',
        'subject_template' => 'Peringatan Tindakan SKT {{current_year}} untuk {{recipient_name}}',
        'body_html' => <<<HTML
<p>Assalamualaikum dan salam sejahtera {{recipient_name}},</p>
<p>Ini adalah peringatan bahawa terdapat tindakan berkaitan proses SKT yang memerlukan perhatian anda.</p>
<p><strong>Rujukan:</strong> {{reference_no}}</p>
<p><strong>Status:</strong> {{status_label}}</p>
<p><strong>Tahun:</strong> {{year}}</p>
<p>Sila semak tindakan anda melalui pautan berikut:</p>
<p><a href="{{action_link}}" target="_blank" rel="noopener noreferrer">Buka tindakan sekarang</a></p>
<p>Terima kasih.</p>
HTML,
        'body_text' => <<<TEXT
Assalamualaikum dan salam sejahtera {{recipient_name}},

Ini adalah peringatan bahawa terdapat tindakan berkaitan proses SKT yang memerlukan perhatian anda.
Rujukan: {{reference_no}}
Status: {{status_label}}
Tahun: {{year}}

Sila semak tindakan anda melalui pautan berikut:
{{action_link}}

Terima kasih.
TEXT,
        'status' => 'ACTIVE',
        'is_default' => 1,
        'description' => 'Template peringatan tindakan SKT untuk staf.',
        'notes' => 'Sesuai untuk flow tindakan yang menghantar reference_no, status_label, year, dan action_link.',
    ],
    [
        'template_code' => 'PUBLIC_PASSWORD_RESET',
        'template_name' => 'Public Password Reset',
        'role_code' => 'public',
        'category_code' => 'security',
        'subject_template' => 'Tindakan Reset Kata Laluan Akaun {{system_name}}',
        'body_html' => <<<HTML
<p>Salam {{recipient_name}},</p>
<p>Kami menerima permintaan untuk menetapkan semula kata laluan akaun manual anda di <strong>{{system_name}}</strong>.</p>
<p><strong>Login ID:</strong> {{login_id}}</p>
<p><strong>Tempoh Sah Pautan:</strong> {{expires_in_minutes}} minit</p>
<p><strong>Tamat Pada:</strong> {{expires_at}}</p>
<p>Sila gunakan pautan berikut untuk menetapkan kata laluan baharu:</p>
<p><a href="{{reset_url}}" target="_blank" rel="noopener noreferrer">Tetapkan kata laluan baharu</a></p>
<p>Jika anda tidak membuat permintaan ini, abaikan sahaja emel ini.</p>
HTML,
        'body_text' => <<<TEXT
Salam {{recipient_name}},

Kami menerima permintaan untuk menetapkan semula kata laluan akaun manual anda di {{system_name}}.
Login ID: {{login_id}}
Tempoh Sah Pautan: {{expires_in_minutes}} minit
Tamat Pada: {{expires_at}}

Tetapkan kata laluan baharu:
{{reset_url}}

Jika anda tidak membuat permintaan ini, abaikan sahaja emel ini.
TEXT,
        'status' => 'ACTIVE',
        'is_default' => 1,
        'description' => 'Template reset kata laluan untuk akaun manual.',
        'notes' => 'Sesuai untuk flow forgot-password yang menghantar login_id, reset_url, expires_in_minutes, dan expires_at.',
    ],
    [
        'template_code' => 'PUBLIC_PASSWORD_CHANGED_NOTICE',
        'template_name' => 'Public Password Changed Notice',
        'role_code' => 'public',
        'category_code' => 'security',
        'subject_template' => 'Notifikasi Keselamatan Akaun {{system_name}}',
        'body_html' => <<<HTML
<p>Salam {{recipient_name}},</p>
<p>Ini adalah notifikasi bahawa kata laluan akaun anda di <strong>{{system_name}}</strong> telah berjaya dikemas kini.</p>
<p><strong>Login ID:</strong> {{login_id}}</p>
<p><strong>Masa Perubahan:</strong> {{changed_at}}</p>
<p>Jika anda tidak melakukan perubahan ini, sila hubungi pentadbir sistem dengan segera.</p>
HTML,
        'body_text' => <<<TEXT
Salam {{recipient_name}},

Ini adalah notifikasi bahawa kata laluan akaun anda di {{system_name}} telah berjaya dikemas kini.
Login ID: {{login_id}}
Masa Perubahan: {{changed_at}}

Jika anda tidak melakukan perubahan ini, sila hubungi pentadbir sistem dengan segera.
TEXT,
        'status' => 'ACTIVE',
        'is_default' => 1,
        'description' => 'Template notifikasi selepas kata laluan berjaya dikemas kini.',
        'notes' => 'Sesuai untuk flow reset-password atau change-password yang menghantar login_id dan changed_at.',
    ],
];

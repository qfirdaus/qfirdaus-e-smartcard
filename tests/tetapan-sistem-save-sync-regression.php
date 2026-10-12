<?php
declare(strict_types=1);

final class TetapanSistemSaveSyncTestStore
{
    /** @var array<string,string> */
    public static array $appSettings = ['auth.sso_enabled' => '1'];
}

final class Database
{
    public static function getInstance(string $name): self
    {
        return new self();
    }

    public function getConnection(): object
    {
        return new stdClass();
    }
}

final class Config
{
    public function __construct(object $connection)
    {
    }

    /** @return array<string,string> */
    public function getGroup(string $group): array
    {
        return $group === 'app_settings' ? TetapanSistemSaveSyncTestStore::$appSettings : [];
    }
}

require_once __DIR__ . '/../public/setting/helper/config_helper.php';

function regressionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

regressionAssert(app_config('auth.sso_enabled') === '1', 'Nilai awal override DB tidak dibaca.');
TetapanSistemSaveSyncTestStore::$appSettings['auth.sso_enabled'] = '0';
regressionAssert(app_config('auth.sso_enabled') === '1', 'Simulasi cache request tidak stabil.');
app_config_reset_runtime_cache();
regressionAssert(app_config('auth.sso_enabled') === '0', 'Reset cache tidak membaca nilai selepas simpan.');

$controllerSource = file_get_contents(__DIR__ . '/../public/controllers/TetapanSistemController.php');
$frontendSource = file_get_contents(__DIR__ . '/../public/assets/js/pages/tetapan-sistem.js');

regressionAssert(is_string($controllerSource), 'Controller tetapan sistem tidak dapat dibaca.');
regressionAssert(is_string($frontendSource), 'JavaScript tetapan sistem tidak dapat dibaca.');
regressionAssert(
    str_contains($controllerSource, "app_config_reset_runtime_cache();"),
    'Controller tidak menginvalidasi cache konfigurasi runtime selepas simpan.'
);
regressionAssert(
    str_contains($controllerSource, "unset(\$settings['api_key']);"),
    'Respons AI Chatbot tidak membuang API key.'
);
regressionAssert(
    str_contains($controllerSource, "unset(\$settings['mail_password']);"),
    'Respons tetapan emel tidak membuang kata laluan SMTP.'
);
regressionAssert(
    str_contains($frontendSource, "normalizeServerBoolean"),
    'Normalisasi boolean respons server tiada.'
);
regressionAssert(
    str_contains($frontendSource, "syncAiChatbotFormState"),
    'Sinkronisasi borang AI Chatbot selepas save tiada.'
);
regressionAssert(
    str_contains($frontendSource, "[formAiChatbot, btnAiChatbot]"),
    'Borang AI Chatbot tidak termasuk dalam pengesanan status simpan.'
);

fwrite(STDOUT, "Tetapan sistem save-sync regression: OK\n");

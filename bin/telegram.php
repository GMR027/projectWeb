<?php
// Bot de Telegram. Uso:
//   php bin/telegram.php --sondeo     escucha mensajes preguntando a Telegram (sirve en tu equipo, sin dirección pública ni https)
//   php bin/telegram.php --webhook    registra https://<APP_URL>/api/telegram/webhook (servidor público con https)
//   php bin/telegram.php --info       muestra el bot y el estado del webhook
// Necesita TELEGRAM_BOT_TOKEN en el .env. Con --sondeo se quita el webhook (Telegram no permite los dos a la vez).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/app.php';

use Classes\Telegram;
use Classes\TelegramBot;

if (!Telegram::configurado()) { fwrite(STDERR, "Falta TELEGRAM_BOT_TOKEN en el .env\n"); exit(1); }
if (!Telegram::disponible()) { fwrite(STDERR, "Falta aplicar database/migracion_telegram.sql\n"); exit(1); }
$arg = $argv[1] ?? '--info';

if ($arg === '--info') {
    $b = Telegram::bot();
    echo $b ? "Bot: @{$b['username']} ({$b['nombre']})\n" : "No se pudo consultar el bot: ¿token correcto?\n";
    echo 'Webhook: ' . json_encode(Telegram::estadoWebhook(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} elseif ($arg === '--webhook') {
    [$ok, $m] = Telegram::registrarWebhook(Telegram::env('APP_URL'));
    echo ($ok ? 'OK: ' : 'ERROR: ') . $m . "\n";
    exit($ok ? 0 : 1);
} elseif ($arg === '--sondeo') {
    Telegram::api('deleteWebhook', ['drop_pending_updates' => false]);
    echo "Escuchando @" . Telegram::usuarioBot() . " (Ctrl+C para salir)…\n";
    $offset = 0;
    while (true) {
        $r = Telegram::api('getUpdates', ['offset' => $offset, 'timeout' => 25, 'allowed_updates' => ['message', 'callback_query', 'my_chat_member']], 35);
        if (empty($r['ok'])) { sleep(3); continue; }
        foreach ($r['result'] as $upd) {
            $offset = max($offset, (int)$upd['update_id'] + 1);
            try { TelegramBot::procesar($upd); } catch (Throwable $e) { fwrite(STDERR, '[' . date('c') . '] ' . $e->getMessage() . "\n"); }
            Telegram::vaciar();
        }
    }
} else {
    fwrite(STDERR, "Uso: php bin/telegram.php --sondeo | --webhook | --info\n");
    exit(1);
}

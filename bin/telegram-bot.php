<?php
// Telegram buttons without a public https address: this script asks Telegram
// for new button presses and messages and handles them, for as long as it runs.
// Use it when the site runs only on your own PC (e.g. in Laragon):
//
//     php bin/telegram-bot.php
//
// Keep the window open. Don't use it together with "Connect Telegram bot" on
// the admin page: Telegram sends updates either to the webhook or to this script.
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    }
});

require ROOT . '/app/Core/helpers.php';

use App\Core\App;
use App\Services\Telegram;

App::boot();

if (!Telegram::enabled()) {
    exit("Put the bot token in telegram.bot_token in config.php first.\n");
}

// Telegram only hands out updates here when no webhook is set.
Telegram::call('deleteWebhook', []);
$me = Telegram::call('getMe', []);
if (!$me) {
    exit("Could not reach Telegram. Check the bot token and the internet connection.\n");
}
echo "Bot @" . ($me['username'] ?? '') . " is running. Keep this window open (Ctrl + C stops it).\n";

$offset = 0;
while (true) {
    $updates = Telegram::call('getUpdates', [
        'offset' => $offset,
        'timeout' => 50,
        'allowed_updates' => ['message', 'callback_query'],
    ], false, 60);
    if ($updates === null) {
        sleep(5); // no internet for a moment; try again
        continue;
    }
    foreach ($updates as $update) {
        $offset = $update['update_id'] + 1;
        try {
            Telegram::handleUpdate($update);
        } catch (Throwable $e) {
            echo date('H:i:s') . ' ' . $e->getMessage() . "\n";
        }
    }
}

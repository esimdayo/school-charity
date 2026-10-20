<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Telegram;

final class TelegramController extends Controller
{
    // Telegram calls this for every button press or message to the bot.
    public function webhook(): void
    {
        $secret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if (!Telegram::enabled() || !hash_equals(Telegram::webhookSecret(), $secret)) {
            http_response_code(403);
            return;
        }
        $update = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($update)) Telegram::handleUpdate($update);
        echo 'ok';
    }
}

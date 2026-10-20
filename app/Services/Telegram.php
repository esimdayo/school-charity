<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\{App, Database as DB, Lang};
use App\Models\{Item, Order};

// Telegram bot for admins: every card payment arrives as a message with the
// item photo and "Money arrived" / "Not received" buttons. Telegram sends the
// button presses to /telegram/webhook (connect it once on the admin page).
final class Telegram
{
    public static function enabled(): bool
    {
        return (string) App::config('telegram.bot_token', '') !== '';
    }

    private static function adminIds(): array
    {
        return array_map('strval', (array) App::config('telegram.admin_ids', []));
    }

    private static function lang(): string
    {
        return (string) App::config('telegram.lang', 'uz');
    }

    private static function t(string $key, array $vars = []): string
    {
        return Lang::t($key, $vars, self::lang());
    }

    // Secret that Telegram sends back with every webhook call.
    public static function webhookSecret(): string
    {
        return hash('sha256', 'webhook:' . App::config('telegram.bot_token', ''));
    }

    public static function call(string $method, array $params, bool $multipart = false): ?array
    {
        $url = rtrim((string) App::config('telegram.api_url', 'https://api.telegram.org'), '/')
            . '/bot' . App::config('telegram.bot_token') . '/' . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $multipart ? [] : ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $multipart ? $params : json_encode($params),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if (!($data['ok'] ?? false)) {
            error_log("Telegram $method failed: " . ($data['description'] ?? 'no response'));
            return null;
        }
        return $data['result'];
    }

    private static function caption(array $order, array $item, string $footer = ''): string
    {
        $lang = self::lang();
        return implode("\n", [
            '<b>' . self::t('tg_new_payment') . '</b>',
            '',
            '💰 <b>' . money($order['amount'], $lang) . '</b>',
            '🎁 ' . e($item['title']),
            '👤 ' . e($order['buyer_name']) . ', ' . e($order['buyer_phone']),
            '💳 ' . self::t('payer_card_short', ['last4' => e($order['payer_card'])]),
            '🕒 ' . fmt_date((int) $order['sent_at'], $lang),
            '',
            $footer ?: self::t('tg_check_bank'),
        ]);
    }

    private static function buttons(int $orderId): array
    {
        return ['inline_keyboard' => [[
            ['text' => '✅ ' . self::t('money_arrived'), 'callback_data' => "confirm:$orderId"],
            ['text' => '❌ ' . self::t('not_received'), 'callback_data' => "unpaid:$orderId"],
        ]]];
    }

    // Called when a buyer presses "I've sent the money".
    public static function notifyPayment(array $order): void
    {
        if (!self::enabled()) return;
        $item = Item::find((int) $order['item_id']);
        $photo = ROOT . '/public/uploads/' . basename($item['photo']);
        foreach (self::adminIds() as $chatId) {
            $msg = is_file($photo)
                ? self::call('sendPhoto', [
                    'chat_id' => $chatId,
                    'photo' => new \CURLFile($photo),
                    'caption' => self::caption($order, $item),
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(self::buttons((int) $order['id'])),
                ], true)
                : self::call('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => self::caption($order, $item),
                    'parse_mode' => 'HTML',
                    'reply_markup' => self::buttons((int) $order['id']),
                ]);
            if ($msg) {
                DB::run('INSERT INTO telegram_messages (order_id, chat_id, message_id) VALUES (?, ?, ?)',
                    [$order['id'], $chatId, $msg['message_id']]);
            }
        }
    }

    // After a payment is confirmed or rejected (in Telegram or on the website),
    // replace the buttons in every admin's message with the result.
    public static function markHandled(int $orderId, string $byName = ''): void
    {
        if (!self::enabled()) return;
        $order = Order::find($orderId);
        $item = Item::find((int) $order['item_id']);
        $result = in_array($order['status'], ['paid', 'picked_up'], true)
            ? '✅ <b>' . self::t('tg_confirmed', ['code' => e($order['pickup_code'])]) . '</b>'
            : '❌ <b>' . self::t('tg_rejected') . '</b>';
        $footer = $byName !== '' ? $result . "\n" . self::t('tg_by', ['name' => e($byName)]) : $result;
        foreach (DB::all('SELECT * FROM telegram_messages WHERE order_id = ?', [$orderId]) as $row) {
            $params = [
                'chat_id' => $row['chat_id'],
                'message_id' => (int) $row['message_id'],
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ];
            // Photo messages have a caption; text messages (photo missing) have text.
            self::call('editMessageCaption', $params + ['caption' => self::caption($order, $item, $footer)])
                ?? self::call('editMessageText', $params + ['text' => self::caption($order, $item, $footer)]);
        }
    }

    public static function websiteName(): string
    {
        return self::t('tg_website');
    }

    // One update from Telegram (a button press or a message).
    public static function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            self::handleButton($update['callback_query']);
        } elseif (isset($update['message']['chat']['id'])) {
            $chatId = (string) $update['message']['chat']['id'];
            $text = in_array($chatId, self::adminIds(), true)
                ? self::t('tg_hello_admin')
                : self::t('tg_hello_other', ['id' => $chatId]);
            self::call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']);
        }
    }

    private static function handleButton(array $cb): void
    {
        $answer = fn (string $text = '', bool $alert = false) =>
            self::call('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => $text, 'show_alert' => $alert]);

        if (!in_array((string) ($cb['from']['id'] ?? ''), self::adminIds(), true)) {
            $answer(self::t('tg_not_admin'), true);
            return;
        }
        [$action, $id] = array_pad(explode(':', (string) ($cb['data'] ?? '')), 2, '');
        $order = Order::find((int) $id);
        if (!$order) {
            $answer(self::t('order_not_found'));
            return;
        }
        if ($order['status'] !== 'awaiting') {
            $answer(self::t('tg_already'));
            self::markHandled((int) $order['id']);
            return;
        }
        if ($action === 'confirm') Order::markPaid((int) $order['id']);
        elseif ($action === 'unpaid') Order::cancel((int) $order['id'], 'unpaid');
        else return;
        $answer($action === 'confirm' ? '✅' : '❌');
        $name = trim(($cb['from']['first_name'] ?? '') . ' ' . ($cb['from']['last_name'] ?? ''));
        self::markHandled((int) $order['id'], $name);
    }

    // Tells Telegram where to send button presses. Needs an https base_url.
    public static function connectWebhook(): bool
    {
        return self::call('setWebhook', [
            'url' => App::baseUrl() . '/telegram/webhook',
            'secret_token' => self::webhookSecret(),
            'allowed_updates' => ['message', 'callback_query'],
        ]) !== null;
    }
}

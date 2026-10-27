<?php
declare(strict_types=1);

namespace App\Core;

// Loads config, starts the session and sets the language for this request.
final class App
{
    private static array $config = [];

    public static function boot(): void
    {
        $file = ROOT . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Copy config.example.php to config.php and fill it in.');
        }
        self::$config = require $file;
        date_default_timezone_set(self::config('timezone', 'Asia/Tashkent'));
        if (PHP_SAPI === 'cli') return; // bin/telegram-bot.php: no session or headers

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => str_starts_with((string) self::config('base_url', ''), 'https'),
        ]);
        session_start();

        Lang::init();
    }

    // config('telegram.bot_token') reads nested keys.
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value;
    }

    // The requested path and query without BASE_PATH, e.g. /item/5 for
    // http://localhost/charity/item/5.
    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) $uri = substr($uri, strlen(BASE_PATH));
        return str_starts_with($uri, '/') ? $uri : '/' . $uri;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) self::config('base_url', ''), '/');
    }
}

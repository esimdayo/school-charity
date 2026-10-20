<?php
declare(strict_types=1);

namespace App\Core;

// Uzbek (default), Russian and English. Texts live in app/lang/*.php.
final class Lang
{
    public const LANGS = ['uz', 'ru', 'en'];
    public const NAMES = ['uz' => "O'zbekcha", 'ru' => 'Русский', 'en' => 'English'];

    private static string $lang = 'uz';
    private static array $texts = [];

    public static function init(): void
    {
        $cookie = $_COOKIE['lang'] ?? '';
        if (in_array($cookie, self::LANGS, true)) {
            self::$lang = $cookie;
        } else {
            $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
            self::$lang = match (true) {
                str_starts_with($accept, 'ru') => 'ru',
                str_starts_with($accept, 'en') => 'en',
                default => (string) App::config('default_lang', 'uz'),
            };
        }
    }

    public static function current(): string
    {
        return self::$lang;
    }

    // Values in $vars are inserted as given, so escape user text before passing it.
    public static function t(string $key, array $vars = [], ?string $lang = null): string
    {
        $lang ??= self::$lang;
        self::$texts[$lang] ??= require ROOT . "/app/lang/$lang.php";
        self::$texts['en'] ??= require ROOT . '/app/lang/en.php';
        $text = self::$texts[$lang][$key] ?? self::$texts['en'][$key] ?? $key;
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $text);
    }
}

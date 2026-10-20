<?php
declare(strict_types=1);

// Small functions used in the views.
use App\Core\{App, Lang};

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function t(string $key, array $vars = []): string
{
    return Lang::t($key, $vars);
}

function money(int|string $n, ?string $lang = null): string
{
    return number_format((int) $n, 0, '', ' ') . ' ' . Lang::t('currency', [], $lang);
}

// 20.10.2026, 14:24 for Uzbek and Russian, 20 Oct 2026, 14:24 for English.
function fmt_date(?int $ts, ?string $lang = null): string
{
    if (!$ts) return '';
    return ($lang ?? Lang::current()) === 'en' ? date('j M Y, H:i', $ts) : date('d.m.Y, H:i', $ts);
}

// Card number grouped in fours: "1111 1111 2222 2222"
function full_card(): string
{
    return trim(chunk_split(preg_replace('/\D/', '', (string) App::config('recipient_card', '')), 4, ' '));
}

function masked_card(): string
{
    $digits = preg_replace('/\D/', '', (string) App::config('recipient_card', ''));
    return $digits ? '•••• ' . substr($digits, -4) : '';
}

function photo_url(string $photo): string
{
    return '/uploads/' . rawurlencode(basename($photo));
}

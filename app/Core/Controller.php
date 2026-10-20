<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $name, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        View::render($name, $data, Auth::check());
    }

    protected function redirect(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }

    protected function notFound(string $textKey = 'not_found'): void
    {
        $this->view('message', ['title' => Lang::t('not_found'), 'text' => Lang::t($textKey)], 404);
    }

    // Every POST form carries a CSRF token from the session.
    protected function checkCsrf(): void
    {
        if (!hash_equals(Csrf::token(), (string) ($_POST['_csrf'] ?? ''))) {
            http_response_code(419);
            exit('Session expired. Go back and try again.');
        }
    }

    protected function input(string $key, int $max = 1000): string
    {
        return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
    }
}

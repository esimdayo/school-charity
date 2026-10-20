<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Lang};

final class LangController extends Controller
{
    public function switch(string $lang): void
    {
        $lang = in_array($lang, Lang::LANGS, true) ? $lang : 'uz';
        setcookie('lang', $lang, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax']);
        $back = (string) ($_GET['back'] ?? '/');
        $this->redirect(str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : '/');
    }
}

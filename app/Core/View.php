<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    // Renders app/Views/{name}.php inside the layout.
    public static function render(string $name, array $data = [], bool $admin = false): void
    {
        extract($data);
        ob_start();
        require ROOT . "/app/Views/$name.php";
        $content = ob_get_clean();
        $title = $data['title'] ?? '';
        require ROOT . '/app/Views/layout.php';
    }
}

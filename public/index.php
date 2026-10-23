<?php
// Front controller: every request comes here (see .htaccess) and the router
// sends it to a controller.
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Built-in PHP server (php -S): let it serve real files like style.css.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    }
});

require ROOT . '/app/Core/helpers.php';

use App\Core\App;
use App\Core\Router;
use App\Controllers\{ShopController, DonateController, PaymentController, OrderController, AdminController, TelegramController, LangController, StoryController, LoginController, MyController};

App::boot();

$router = new Router();

$router->get('/', [ShopController::class, 'index']);
$router->get('/item/{id}', [ShopController::class, 'show']);
$router->post('/item/{id}/buy', [ShopController::class, 'buy']);

$router->get('/donate', [DonateController::class, 'form']);
$router->post('/donate', [DonateController::class, 'store']);

$router->get('/pay/card/{token}', [PaymentController::class, 'card']);
$router->post('/pay/card/{token}', [PaymentController::class, 'cardSubmit']);
$router->get('/pay/test/{token}', [PaymentController::class, 'test']);
$router->post('/pay/test/{token}', [PaymentController::class, 'testSubmit']);

$router->get('/order/{token}', [OrderController::class, 'show']);

$router->get('/login', [LoginController::class, 'form']);
$router->get('/login/check', [LoginController::class, 'check']);
$router->post('/logout', [LoginController::class, 'logout']);
$router->get('/my', [MyController::class, 'orders']);
$router->get('/story', [StoryController::class, 'show']);
$router->get('/lang/{lang}', [LangController::class, 'switch']);

$router->get('/admin/login', [AdminController::class, 'loginForm']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->post('/admin/logout', [AdminController::class, 'logout']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->post('/admin/items/{id}/approve', [AdminController::class, 'approve']);
$router->post('/admin/items/{id}/reject', [AdminController::class, 'reject']);
$router->post('/admin/orders/{id}/confirm', [AdminController::class, 'confirmPayment']);
$router->post('/admin/orders/{id}/unpaid', [AdminController::class, 'rejectPayment']);
$router->get('/admin/pickup', [AdminController::class, 'pickup']);
$router->post('/admin/orders/{id}/pickup', [AdminController::class, 'handOver']);
$router->get('/admin/stats', [AdminController::class, 'stats']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->post('/admin/settings', [AdminController::class, 'saveSettings']);
$router->post('/admin/raised/reset', [AdminController::class, 'resetRaised']);
$router->get('/admin/receipt/{id}', [AdminController::class, 'receipt']);
$router->post('/admin/telegram/connect', [AdminController::class, 'connectTelegram']);

$router->post('/telegram/webhook', [TelegramController::class, 'webhook']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

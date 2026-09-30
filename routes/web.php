<?php

// =====================================================
// URL と処理（コントローラー）の対応表
//   php artisan route:list で一覧が見られます
// =====================================================

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InsideController;
use App\Http\Controllers\Liff;
use App\Http\Controllers\Mock;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// 構築ナビ（オリジナルのお店の公式LINEを、本番と同じ順番でつなぐ道案内）
Route::get('build', [\App\Http\Controllers\BuildController::class, 'index'])->name('build');
Route::post('build/select', [\App\Http\Controllers\BuildController::class, 'select'])->name('build.select');
Route::post('build/stop', [\App\Http\Controllers\BuildController::class, 'stop'])->name('build.stop');
Route::get('build/nav', [\App\Http\Controllers\BuildController::class, 'nav'])->name('build.nav');

// ---------------------------------------------------------------
// 管理画面（運営スタッフ）… ログインが必要
// ---------------------------------------------------------------
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [Admin\LoginController::class, 'show'])->name('login');
    Route::post('login', [Admin\LoginController::class, 'login'])->name('login.post');
    Route::post('logout', [Admin\LoginController::class, 'logout'])->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('stores', Admin\StoreController::class)->except(['create']);
        Route::post('stores/{store}/approve', [Admin\StoreController::class, 'approve'])->name('stores.approve');
        Route::post('stores/{store}/reject', [Admin\StoreController::class, 'reject'])->name('stores.reject');
        Route::post('stores/{store}/check', [Admin\StoreController::class, 'check'])->name('stores.check');
        Route::post('stores/{store}/test-line', [Admin\StoreController::class, 'testLine'])->name('stores.test-line');
        Route::post('stores/{store}/rates', [Admin\StoreController::class, 'saveRates'])->name('stores.rates');
        Route::resource('stores.menus', Admin\MenuController::class)->except(['index', 'show']);

        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/confirm-payment', [Admin\OrderController::class, 'confirmPayment'])->name('orders.confirm-payment');
        Route::post('orders/{order}/refund', [Admin\OrderController::class, 'refund'])->name('orders.refund');
        Route::post('orders/{order}/refund-complete', [Admin\OrderController::class, 'refundComplete'])->name('orders.refund-complete');

        Route::get('users', [Admin\CustomerController::class, 'index'])->name('users.index');
        Route::get('users/{customer}', [Admin\CustomerController::class, 'show'])->name('users.show');

        Route::get('sales', Admin\SalesController::class)->name('sales');

        Route::resource('menu-templates', Admin\MenuTemplateController::class)->except(['show']);
        Route::resource('agents', Admin\AgentController::class);
    });
});

// ---------------------------------------------------------------
// LIFF（LINEの中で開く画面）… お客さん・店舗オーナー
// ---------------------------------------------------------------
Route::prefix('liff')->name('liff.')->middleware('line.user')->group(function () {
    Route::post('session', [Liff\SessionController::class, 'store'])->name('session');

    // お客さん
    Route::get('shops', [Liff\ShopController::class, 'index'])->name('shops');
    Route::get('s/{slug}', [Liff\ShopController::class, 'show'])->name('store');
    Route::get('s/{slug}/m/{menu}', [Liff\OrderController::class, 'create'])->name('order.form');   // 「この商品を送る」→ 注文画面
    Route::post('s/{slug}/order', [Liff\OrderController::class, 'store'])->name('order');
    Route::get('orders/{order}/done', [Liff\OrderController::class, 'done'])->name('order.done');
    Route::get('history', [Liff\HistoryController::class, 'index'])->name('history');
    Route::get('history/{order}/receipt', [Liff\HistoryController::class, 'receipt'])->name('receipt');
    Route::get('thanks', [Liff\HistoryController::class, 'thanks'])->name('thanks');

    // 店舗オーナー
    Route::get('register', [Liff\OwnerController::class, 'register'])->name('register');
    Route::post('register', [Liff\OwnerController::class, 'storeRegistration'])->name('register.post');
    Route::get('manage', [Liff\OwnerController::class, 'index'])->name('manage');
    Route::get('manage/{store}/menus', [Liff\OwnerController::class, 'menus'])->name('manage.menus');
    Route::post('manage/{store}/menus', [Liff\OwnerController::class, 'addMenu'])->name('manage.menus.add');
    Route::post('manage/{store}/menus/{menu}/toggle', [Liff\OwnerController::class, 'toggleMenu'])->name('manage.menus.toggle');
    Route::get('manage/{store}/gifts', [Liff\OwnerController::class, 'gifts'])->name('manage.gifts');
    Route::get('manage/{store}/gifts/{order}', [Liff\OwnerController::class, 'gift'])->name('manage.gift');   // 贈り物を受け取る画面
    Route::post('manage/{store}/gifts/{order}/receive', [Liff\OwnerController::class, 'receive'])->name('manage.receive');
    Route::get('manage/{store}/gifts/{order}/thank', [Liff\OwnerController::class, 'thankForm'])->name('manage.thank');
    Route::post('manage/{store}/gifts/{order}/thank', [Liff\OwnerController::class, 'thank'])->name('manage.thank.post');
});

// お礼動画：期限付きの「署名付きURL」でだけ見られる
Route::get('media/thank-videos/{order}', [Liff\HistoryController::class, 'video'])->name('media.thank-video')->middleware('signed');

// ---------------------------------------------------------------
// 疑似LINE（LINE社の役）
// ---------------------------------------------------------------
Route::prefix('mock')->name('mock.')->group(function () {
    Route::post('switch-account', [Mock\AccountController::class, 'switch'])->name('switch-account');

    // 疑似スマホは2台（お客さん・オーナー）。/mock/phone で左右に並べて表示し、1台ずつの画面は /mock/phone/{customer|owner}
    Route::get('phone', [Mock\PhoneController::class, 'both'])->name('phone');
    Route::prefix('phone/{phone}')->where(['phone' => 'customer|owner'])->group(function () {
        Route::get('/', [Mock\PhoneController::class, 'index'])->name('phone.screen');
        Route::post('name', [Mock\PhoneController::class, 'rename'])->name('phone.name');
        Route::post('add', [Mock\PhoneController::class, 'add'])->name('phone.add');
        Route::post('chat/{oa}/send', [Mock\PhoneController::class, 'send'])->name('phone.send');
        Route::post('chat/{oa}/block', [Mock\PhoneController::class, 'block'])->name('phone.block');
        Route::get('chat/{oa}/last', [Mock\PhoneController::class, 'last'])->name('phone.last');
    });
    Route::post('phone/liff-send', [Mock\PhoneController::class, 'liffSend'])->name('phone.liff-send');

    Route::get('manager', [Mock\ManagerController::class, 'index'])->name('manager');
    Route::get('manager/create', [Mock\ManagerController::class, 'create'])->name('manager.create');
    Route::post('manager/create', [Mock\ManagerController::class, 'store'])->name('manager.store');
    Route::get('manager/invite/{token}', [Mock\ManagerController::class, 'invite'])->name('manager.invite');
    Route::post('manager/invite/{token}', [Mock\ManagerController::class, 'join'])->name('manager.join');
    Route::prefix('manager/oa/{oa}')->name('manager.oa.')->group(function () {
        Route::get('/', [Mock\ManagerController::class, 'home'])->name('home');
        Route::get('settings', [Mock\ManagerController::class, 'settings'])->name('settings');
        Route::get('members', [Mock\ManagerController::class, 'members'])->name('members');
        Route::post('members/invite', [Mock\ManagerController::class, 'createInvite'])->name('members.invite');
        Route::get('messaging-api', [Mock\ManagerController::class, 'messaging'])->name('messaging');
        Route::post('messaging-api', [Mock\ManagerController::class, 'enableMessaging'])->name('messaging.enable');
        Route::get('response', [Mock\ManagerController::class, 'response'])->name('response');
        Route::post('response', [Mock\ManagerController::class, 'saveResponse'])->name('response.save');
        Route::get('richmenus', [Mock\RichMenuController::class, 'index'])->name('richmenus');
        Route::get('richmenus/create', [Mock\RichMenuController::class, 'create'])->name('richmenus.create');
        Route::post('richmenus', [Mock\RichMenuController::class, 'store'])->name('richmenus.store');
        Route::post('richmenus/{richMenu}/default', [Mock\RichMenuController::class, 'makeDefault'])->name('richmenus.default');
        Route::get('richmenus/{richMenu}/edit', [Mock\RichMenuController::class, 'edit'])->name('richmenus.edit');
        Route::post('richmenus/{richMenu}', [Mock\RichMenuController::class, 'update'])->name('richmenus.update');
        Route::delete('richmenus/{richMenu}', [Mock\RichMenuController::class, 'destroy'])->name('richmenus.destroy');
    });

    Route::get('developers', [Mock\DevelopersController::class, 'index'])->name('developers');
    Route::get('developers/provider/{provider}', [Mock\DevelopersController::class, 'provider'])->name('developers.provider');
    Route::post('developers/provider/{provider}/channels', [Mock\DevelopersController::class, 'createChannel'])->name('developers.channels.store');
    Route::prefix('developers/channel/{channel}')->name('developers.channel.')->group(function () {
        Route::get('/', [Mock\DevelopersController::class, 'channel'])->name('show');
        Route::post('secret', [Mock\DevelopersController::class, 'reissueSecret'])->name('secret');
        Route::post('token', [Mock\DevelopersController::class, 'issueToken'])->name('token');
        Route::post('webhook', [Mock\DevelopersController::class, 'saveWebhook'])->name('webhook');
        Route::post('webhook/verify', [Mock\DevelopersController::class, 'verify'])->name('verify');
        Route::post('webhook/use', [Mock\DevelopersController::class, 'toggleWebhook'])->name('use');
        Route::post('publish', [Mock\DevelopersController::class, 'publish'])->name('publish');
        Route::post('liff', [Mock\DevelopersController::class, 'addLiff'])->name('liff');
        Route::post('liff/{liffId}', [Mock\DevelopersController::class, 'updateLiff'])->name('liff.update');
        Route::delete('liff/{liffId}', [Mock\DevelopersController::class, 'deleteLiff'])->name('liff.delete');
        Route::post('roles', [Mock\DevelopersController::class, 'addRole'])->name('roles');
    });
});

// 疑似LINE の Messaging API（本物の https://api.line.me の代わり）
Route::prefix('mock-line-api')->group(function () {
    Route::post('v2/oauth/verify', [Mock\ApiController::class, 'verifyToken']);
    Route::middleware('mock.token')->group(function () {
        Route::post('v2/bot/message/reply', [Mock\ApiController::class, 'reply']);
        Route::post('v2/bot/message/push', [Mock\ApiController::class, 'push']);
        Route::get('v2/bot/profile/{userId}', [Mock\ApiController::class, 'profile']);
        Route::get('v2/bot/info', [Mock\ApiController::class, 'info']);
    });
});

// ---------------------------------------------------------------
// 裏側ビュー
// ---------------------------------------------------------------
Route::get('inside', [InsideController::class, 'index'])->name('inside');
Route::get('inside/logs', [InsideController::class, 'logs'])->name('inside.logs');
Route::post('inside/clear', [InsideController::class, 'clear'])->name('inside.clear');

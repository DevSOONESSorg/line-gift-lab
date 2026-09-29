<?php

// =====================================================
// Webhook の受け口（LINE社 → 自社サーバー）… URL の先頭に /api が付きます
//   POST /api/webhook/line/store/{slug}   店舗専用チャネルから
//   POST /api/webhook/line/platform       運営の共通チャネルから
// api グループなので、セッションや CSRF チェックはありません（代わりに「署名」で確かめる）
// =====================================================

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhook/line/store/{slug}', [WebhookController::class, 'store'])->name('webhook.store');
Route::post('webhook/line/platform', [WebhookController::class, 'platform'])->name('webhook.platform');

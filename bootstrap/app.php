<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 名前で呼べるミドルウェア（routes/web.php で ->middleware('line.user') のように使う）
        $middleware->alias([
            'line.user' => \App\Http\Middleware\IdentifyLineUser::class,
            'mock.token' => \App\Http\Middleware\MockLineToken::class,
        ]);
        // 疑似LINEのAPIは「サーバー同士」の通信なので、画面用の CSRF チェックはしない
        $middleware->validateCsrfTokens(except: ['mock-line-api/*']);
        // 未ログインで管理画面を開いたらログイン画面へ
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        // 全画面で使う共通の値（環境名など）
        $middleware->web(append: [\App\Http\Middleware\ShareLabContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

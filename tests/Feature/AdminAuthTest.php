<?php

namespace Tests\Feature;

use Tests\TestCase;

// ログインしていない人は、管理画面に入れない（ログイン画面に移される）
class AdminAuthTest extends TestCase
{
    public function test_ログインしていないと注文管理はログイン画面に移される(): void
    {
        $this->get('/admin/orders')->assertRedirect(route('admin.login'));
    }

    public function test_ログインしていないと店舗管理もログイン画面に移される(): void
    {
        $this->get('/admin/stores')->assertRedirect(route('admin.login'));
    }
}

<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

// 状態遷移のテスト（コースC の C1-3 で書き足していく）
//   docker compose exec app php artisan test
class OrderStatusTest extends TestCase
{
    public function test_リクエスト中は受取済みにできる(): void
    {
        $this->assertTrue(OrderStatus::Requested->canTransitionTo(OrderStatus::Received));
    }

    public function test_期限切れは受取済みにできない(): void
    {
        $this->assertFalse(OrderStatus::Expired->canTransitionTo(OrderStatus::Received));
    }
}

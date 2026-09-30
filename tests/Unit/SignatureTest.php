<?php

namespace Tests\Unit;

use App\Services\Line\Signature;
use PHPUnit\Framework\TestCase;

// LINE の署名（X-Line-Signature）の確かめ方が正しいか
class SignatureTest extends TestCase
{
    public function test_同じシークレットと本文なら署名が合う(): void
    {
        $body = '{"events":[]}';
        $this->assertTrue(Signature::verify('secret-123', $body, Signature::sign('secret-123', $body)));
    }

    public function test_本文が1文字でも変わると合わない(): void
    {
        $sig = Signature::sign('secret-123', '{"events":[]}');
        $this->assertFalse(Signature::verify('secret-123', '{"events":[1]}', $sig));
    }

    public function test_シークレットが違うと合わない(): void
    {
        $body = '{"events":[]}';
        $this->assertFalse(Signature::verify('secret-999', $body, Signature::sign('secret-123', $body)));
    }

    public function test_シークレットか署名がないと合わない(): void
    {
        $this->assertFalse(Signature::verify(null, '{}', 'abc'));
        $this->assertFalse(Signature::verify('secret-123', '{}', null));
    }
}

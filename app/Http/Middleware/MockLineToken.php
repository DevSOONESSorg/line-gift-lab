<?php

namespace App\Http\Middleware;

use App\Models\Mock\Channel;
use App\Support\Inside;
use Closure;
use Illuminate\Http\Request;

// 疑似LINE API の「許可証（チャネルアクセストークン）」チェック
class MockLineToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) $request->bearerToken();
        $channel = Channel::byToken($token);
        if (! $channel) {
            Inside::ng('line', "API {$request->method()} /{$request->path()} → 401 トークンが無効です",
                '受け取ったトークン: '.Inside::mask($token)."\n→ 再発行したのに管理画面を更新していない／別のチャネルのトークン、などが原因です");
            return response()->json(['message' => 'Authentication failed. Confirm that the access token in the authorization header is valid.'], 401);
        }
        $request->attributes->set('channel', $channel);
        return $next($request);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// このアプリの「外から見たURL」
//   疑似LINE（コースA）… http://localhost:3000
//   本物のLINE（コースB）… cloudflared（トンネル）が発行した https のURL
class PublicUrl
{
    public static function local(): string
    {
        return 'http://localhost:'.config('lab.host_port');
    }

    public static function tunnel(): string
    {
        if (config('lab.public_url')) return config('lab.public_url');
        if (! config('lab.tunnel_metrics')) return '';
        return Cache::remember('tunnel_url', 15, function () {
            try {
                $host = Http::timeout(2)->get(config('lab.tunnel_metrics').'/quicktunnel')->json('hostname');
                return $host ? "https://{$host}" : '';
            } catch (\Throwable) {
                return '';
            }
        });
    }
}

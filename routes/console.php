<?php

// =====================================================
// 決まった時間に自動で動く処理（スケジューラー）
//   Docker では php artisan schedule:work が裏で動いていて、毎分これを確認します
//   手で動かすときは：php artisan orders:expire
// =====================================================

use Illuminate\Support\Facades\Schedule;

Schedule::command('orders:expire')->everyMinute();

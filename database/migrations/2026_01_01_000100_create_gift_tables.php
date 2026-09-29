<?php

// =====================================================
// 自社サービス「おくりギフト」のテーブル（database/database.sqlite）
// 本番環境の管理画面にある項目を、教材用にまとめています。
// =====================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 代理店（1次・2次）
        Schema::create('agents', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('company')->default('');
            $t->foreignId('parent_id')->nullable()->constrained('agents');   // 空なら1次代理店
            $t->decimal('reward_rate', 5, 2)->default(0);                    // 報酬率（売上に対する%）
            $t->string('referral_code', 6)->unique();                        // 紹介コード
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // 商品テンプレート（運営が用意する「よくある商品」の見本）
        Schema::create('menu_templates', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('sort_order')->default(0);
            $t->string('name');
            $t->string('description')->default('');
            $t->string('category');
            $t->unsignedInteger('reference_price');
            $t->string('image_color', 7)->default('#888888');   // 教材では画像の代わりに色
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // 店舗
        Schema::create('stores', function (Blueprint $t) {
            $t->id();
            // 基本情報
            $t->string('name');
            $t->string('slug')->unique();                  // URLに入る「お店の住所」
            $t->text('description')->nullable();
            $t->string('website_url')->nullable();
            $t->string('image_color', 7)->default('#e8590c');
            // 所在地・代表者・営業許可
            $t->string('postal_code')->nullable();
            $t->string('prefecture')->nullable();
            $t->string('city')->nullable();
            $t->string('address')->nullable();
            $t->string('tel')->nullable();
            $t->string('representative_name')->nullable();
            $t->string('representative_tel')->nullable();
            $t->string('business_license_number')->nullable();
            // 振込先
            $t->string('bank_name')->nullable();
            $t->string('bank_branch')->nullable();
            $t->string('bank_account_type')->nullable();   // 普通 / 当座
            $t->string('bank_account_number')->nullable();
            $t->string('bank_account_name')->nullable();
            $t->string('yucho_symbol')->nullable();
            $t->string('yucho_number')->nullable();
            // 管理設定
            $t->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $t->decimal('commission_rate', 5, 2)->default(10);  // デフォルト手数料率
            $t->boolean('is_approved')->default(false);
            $t->boolean('is_listed_in_directory')->default(true); // 共通アプリの店舗一覧に掲載
            $t->boolean('require_id_verification')->default(false);
            $t->boolean('wants_original')->default(false);        // 出店登録で「オリジナル（自前の公式LINE）」を希望
            // 店舗管理者（出店登録した人＝オーナー）
            $t->string('owner_line_user_id')->nullable();
            // LINE 連携設定（店舗専用チャネル）
            $t->string('liff_id')->nullable();
            $t->string('line_messaging_channel_id')->nullable();
            $t->text('line_messaging_channel_secret')->nullable();       // 暗号化して保存（モデルの casts）
            $t->text('line_messaging_channel_access_token')->nullable(); // 暗号化して保存
            $t->string('line_official_account_id')->nullable();
            $t->boolean('auto_reply_enabled')->default(true);          // テキスト受信時にギフト誘導を自動返信
            $t->timestamps();
        });

        // 月別手数料率（ない月はデフォルト手数料率）
        Schema::create('store_commission_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');
            $t->decimal('rate', 5, 2);
            $t->timestamps();
            $t->unique(['store_id', 'year', 'month']);
        });

        // 商品（店舗ごとのメニュー）
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->foreignId('menu_template_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->unsignedInteger('price');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // お客さん（LINEユーザー）
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('line_user_id')->unique();
            $t->string('line_display_name');
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            // 登録カード（自社は「目印」だけ持つ。本物はカード番号を決済会社が保管）
            $t->string('card_last4', 4)->nullable();
            $t->string('card_exp', 5)->nullable();
            // 身分証（一度登録すれば、ほかの店舗でも有効）
            $t->timestamp('id_verified_at')->nullable();
            $t->timestamps();
        });

        // 注文（ギフト）
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained();
            $t->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->constrained();
            $t->string('menu_name');                         // 注文時点の商品名（あとで商品が変わっても残す）
            $t->unsignedInteger('amount');
            $t->decimal('commission_rate', 5, 2);            // 注文時点の手数料率
            $t->unsignedInteger('commission');               // 手数料
            $t->string('payment_method');                    // card / bank_transfer
            $t->string('status');                            // App\Enums\OrderStatus
            $t->string('route')->default('common');          // common（共通掲載） / original（店舗の公式LINE）
            $t->text('message')->nullable();                 // お客さんからのメッセージ
            $t->string('card_last4', 4)->nullable();
            $t->timestamp('expires_at')->nullable();         // 与信の期限 or 入金期限
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('thanked_at')->nullable();
            $t->string('thank_video_path')->nullable();      // お礼動画（storage/app/private/...）
            $t->text('thank_message')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->timestamps();
        });

        // 状態が変わった記録（いつ・何から何へ・だれが）
        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from')->nullable();
            $t->string('to');
            $t->string('by');       // customer / owner / admin / system
            $t->string('note')->default('');
            $t->timestamp('created_at')->useCurrent();
        });

        // サービス全体の設定（運営の公式LINEの値など）
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value');
        });
    }

    public function down(): void
    {
        foreach (['settings', 'order_status_logs', 'orders', 'customers', 'menus', 'store_commission_rates', 'stores', 'menu_templates', 'agents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

namespace Tests\Feature;

use App\Models\Mock\AutoReply;
use App\Models\Mock\LineUser;
use App\Models\Mock\Message;
use App\Models\Setting;
use App\Services\BuildGuide;
use App\Services\BuildScenario;
use App\Services\MockLine\MockLine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// 構築ナビが「本番の構築ルール」どおりに判定するか
//   ・ケースB（お店が公式LINEを運用中）では、応答メッセージをオフにすると事故として止める
//   ・変更前の状態を正しく控えないと先に進めない
//   ・応答メッセージはキーワード一致 → 一律応答 の順で返る
class BuildCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 疑似LINE・裏側ビューのデータベースも、テストのあいだはメモリの中に作る（手元のデータを消さない）
        config(['database.connections.mockline.database' => ':memory:', 'database.connections.inside.database' => ':memory:']);
        DB::purge('mockline'); DB::purge('inside');
        Storage::fake('local'); Storage::fake('public');
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    public function test_ケースBの店はキーワード応答を持ち_変更前の状態が記録される(): void
    {
        $store = BuildScenario::start('club-azure');
        $guide = new BuildGuide($store);
        $this->assertSame('B', $guide->caseOf());
        $this->assertSame(['auto_reply_on' => true, 'greeting_on' => true, 'keywords' => 3, 'case' => 'B'],
            array_intersect_key($guide->initial(), array_flip(['auto_reply_on', 'greeting_on', 'keywords', 'case'])));
        $this->assertSame(3, $guide->oa->autoReplies()->get()->reject->isCatchAll()->count());
    }

    public function test_ケースAの店はテキストのボタンを持たない(): void
    {
        $store = BuildScenario::start('lounge-coral');
        $guide = new BuildGuide($store);
        $this->assertSame('A', $guide->caseOf());
        $this->assertSame(0, $guide->initial()['keywords']);
        $types = collect($guide->oa->richMenus()->first()->actions)->pluck('type')->unique()->values()->all();
        $this->assertNotContains('text', $types);
    }

    public function test_応答メッセージはキーワード一致を優先し_なければ一律応答(): void
    {
        $store = BuildScenario::start('club-azure');
        $oa = (new BuildGuide($store))->oa;
        $this->assertSame('営業時間', AutoReply::match($oa, '営業時間')->title);
        $this->assertTrue(AutoReply::match($oa, '今日空いてる？')->isCatchAll());
        $oa->autoReplies()->where('title', '営業時間')->update(['enabled' => false]);
        $this->assertTrue(AutoReply::match($oa, '営業時間')->isCatchAll());
    }

    public function test_スナップショットは実際の変更前の状態と合うときだけ完了(): void
    {
        $store = BuildScenario::start('club-azure');
        session(['build.store' => $store->id]);
        $this->withSession(['build.store' => $store->id])
            ->post(route('build.snapshot'), ['auto_reply_on' => '0', 'keywords' => 0, 'greeting_on' => '1', 'case' => 'A']);
        $this->assertFalse((new BuildGuide($store))->snapshotOk());

        $this->withSession(['build.store' => $store->id])
            ->post(route('build.snapshot'), ['auto_reply_on' => '1', 'keywords' => 3, 'greeting_on' => '1', 'case' => 'B']);
        $this->assertTrue((new BuildGuide($store))->snapshotOk());
    }

    public function test_ケースBで応答メッセージをオフにすると応答設定の手順は完了にならない(): void
    {
        $store = BuildScenario::start('club-azure');
        $guide = new BuildGuide($store);
        $oa = $guide->oa;
        $ch = MockLine::enableMessagingApi($oa, DB::connection('mockline')->table('providers')->value('id'), 'company');
        $ch->update(['use_webhook' => true]);

        $step = fn () => collect((new BuildGuide($store->fresh()))->steps())->firstWhere('key', 'response');

        // 標準手順のつもりで応答メッセージをオフ → 事故として止める
        $oa->update(['auto_reply_on' => false]);
        $store->update(['auto_reply_enabled' => false]);
        $this->assertFalse($step()['done']);
        $this->assertNotNull($step()['bad']);

        // 正しくは：応答メッセージ・あいさつはオンのまま、自社の自動返信をオフ
        $oa->update(['auto_reply_on' => true]);
        $this->assertTrue($step()['done']);
    }

    public function test_ケースBで応答メッセージがオフだとキーワードに返事がない(): void
    {
        $store = BuildScenario::start('club-azure');
        $oa = (new BuildGuide($store))->oa;
        $guest = LineUser::me('customer');

        MockLine::userSendsText($oa, $guest, 'お店の情報');
        $this->assertTrue(Message::where(['official_account_id' => $oa->id, 'via' => 'auto'])->latest('id')->first()?->text !== null);

        $oa->update(['auto_reply_on' => false]);
        $before = Message::where(['official_account_id' => $oa->id, 'via' => 'auto'])->count();
        MockLine::userSendsText($oa, $guest, 'お店の情報');
        $this->assertSame($before, Message::where(['official_account_id' => $oa->id, 'via' => 'auto'])->count());
    }

    public function test_LIFF_IDには見まちがえやすい文字が必ず入る(): void
    {
        $login = MockLine::createLoginChannel(DB::connection('mockline')->table('providers')->value('id'), 'テスト LIFF', 'company');
        foreach (range(1, 20) as $i) {
            $id = MockLine::addLiff($login, "t{$i}", 'http://localhost:3000/liff/s/x', 'Full', 'openid profile', true)->liff_id;
            $this->assertMatchesRegularExpression('/^\d{10}-[A-Za-z0-9]*[IlO0][A-Za-z0-9]*$/', $id);
        }
    }

    public function test_構築履歴のリセットで記録も消える(): void
    {
        BuildScenario::start('club-azure');
        Setting::put('build.snapshot.club-azure', '{}');
        BuildScenario::resetAll();
        $this->assertSame('', Setting::get('scenario.club-azure.initial'));
        $this->assertSame('', Setting::get('build.snapshot.club-azure'));
    }
}

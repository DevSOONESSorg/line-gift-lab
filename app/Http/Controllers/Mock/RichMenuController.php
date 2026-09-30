<?php

namespace App\Http\Controllers\Mock;

use App\Http\Controllers\Controller;
use App\Models\Mock\OfficialAccount;
use App\Models\Mock\RichMenu;
use App\Support\Inside;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// 疑似 Manager のリッチメニュー
class RichMenuController extends Controller
{
    private function guard(Request $r, OfficialAccount $oa): void
    {
        $role = $oa->roleOf($r->session()->get('mock_account', 'personal'));
        abort_unless($role, 403, "「{$oa->name}」のメンバーではありません。");
        view()->share(['oa' => $oa, 'role' => $role, 'roles' => ManagerController::ROLES]);
    }

    public function index(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        return view('mock.manager.richmenus', ['menus' => $oa->richMenus()->latest('id')->get()]);
    }

    public function create(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        return view('mock.manager.richmenu-form');
    }

    public function store(Request $r, OfficialAccount $oa)
    {
        $this->guard($r, $oa);
        $tplKey = (string) $r->input('template');
        $tpl = config("lab.richmenu_templates.$tplKey");
        $fail = fn ($m) => back()->withInput()->withErrors(['richmenu' => $m]);

        if (! $r->filled('title')) return $fail('タイトルを入れてください（管理用。お客さんには見えません）。');
        if (! $tpl) return $fail('テンプレートを選んでください。');
        if (! $r->hasFile('image')) return $fail('背景画像を選んでください（PNG または JPEG・1MBまで）。');
        $file = $r->file('image');
        if ($file->getSize() > 1024 * 1024) return $fail('画像が1MBをこえています。');
        $size = @getimagesize($file->getRealPath());
        if (! $size || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) return $fail('PNG か JPEG の画像を選んでください。');
        $ok = config("lab.richmenu_sizes.{$tpl['size']}");
        if (! collect($ok)->contains(fn ($s) => $s[0] === $size[0] && $s[1] === $size[1])) {
            return $fail("画像サイズが合いません。選んだ画像は {$size[0]}×{$size[1]}px です。「{$tpl['label']}」には "
                .collect($ok)->map(fn ($s) => "{$s[0]}×{$s[1]}")->join(' / ').' のどれかが必要です。');
        }
        $areas = RichMenu::templateAreas($tplKey);
        $actions = [];
        foreach ($areas as $i => $_) {
            $type = $r->input("action_type_$i", 'none');
            $value = trim((string) $r->input("action_value_$i"));
            if ($type !== 'none' && $value === '') return $fail('ボタン '.chr(65 + $i).' のURL／テキストが空です。');
            $actions[] = ['type' => $type, 'value' => $value];
        }
        $path = $file->store('richmenus', 'public');
        if ($r->boolean('is_default')) $oa->richMenus()->update(['is_default' => false]);
        $oa->richMenus()->create(['title' => $r->input('title'), 'template' => $tplKey, 'image_path' => $path,
            'width' => $size[0], 'height' => $size[1], 'bar_text' => $r->input('bar_text') ?: 'メニュー',
            'actions' => $actions, 'is_default' => $r->boolean('is_default')]);
        Inside::ok('line', "「{$oa->name}」にリッチメニュー「{$r->input('title')}」を作成しました",
            collect($actions)->map(fn ($a, $i) => 'ボタン'.chr(65 + $i).': '.match ($a['type']) { 'link' => 'リンク → '.$a['value'], 'text' => "テキスト「{$a['value']}」を送る", default => '設定なし' })->join("\n"));
        return redirect()->route('mock.manager.oa.richmenus', $oa)->with('msg', '保存しました');
    }

    // 既存のリッチメニューのボタン（アクション）だけを変える。画像・分割はそのまま
    public function edit(Request $r, OfficialAccount $oa, RichMenu $richMenu)
    {
        $this->guard($r, $oa);
        abort_unless($richMenu->official_account_id == $oa->id, 404);
        return view('mock.manager.richmenu-edit', ['menu' => $richMenu]);
    }

    public function update(Request $r, OfficialAccount $oa, RichMenu $richMenu)
    {
        $this->guard($r, $oa);
        abort_unless($richMenu->official_account_id == $oa->id, 404);
        $actions = [];
        foreach ($richMenu->areas() as $i => $_) {
            $old = $richMenu->actions[$i] ?? [];
            $type = $r->input("action_type_$i", 'none');
            $value = trim((string) $r->input("action_value_$i"));
            if ($type !== 'none' && $value === '') return back()->withInput()->withErrors(['richmenu' => 'ボタン '.chr(65 + $i).' のURL／テキストが空です。']);
            if ($type === 'link' && ! preg_match('#^(https?|line)://#', $value)) return back()->withInput()->withErrors(['richmenu' => 'ボタン '.chr(65 + $i).' のリンクは https:// から始まるURLを入れてください。']);
            $actions[] = ['type' => $type, 'value' => $type === 'none' ? '' : $value] + (isset($old['label']) ? ['label' => $old['label']] : []);
        }
        $richMenu->update(['actions' => $actions, 'title' => $r->input('title') ?: $richMenu->title, 'bar_text' => $r->input('bar_text') ?: $richMenu->bar_text]);
        Inside::ok('line', "「{$oa->name}」のリッチメニュー「{$richMenu->title}」のアクションを変更しました",
            collect($actions)->map(fn ($a, $i) => 'ボタン'.chr(65 + $i).(isset($a['label']) ? "（{$a['label']}）" : '').': '.match ($a['type']) { 'link' => 'リンク → '.$a['value'], 'text' => "テキスト「{$a['value']}」を送る", default => '設定なし' })->join("\n"));
        return redirect()->route('mock.manager.oa.richmenus', $oa)->with('msg', '保存しました');
    }

    public function makeDefault(Request $r, OfficialAccount $oa, RichMenu $richMenu)
    {
        $this->guard($r, $oa);
        $oa->richMenus()->update(['is_default' => false]);
        $richMenu->update(['is_default' => true]);
        return back()->with('msg', '表示するメニューを切り替えました');
    }

    public function destroy(Request $r, OfficialAccount $oa, RichMenu $richMenu)
    {
        $this->guard($r, $oa);
        Storage::disk('public')->delete($richMenu->image_path);
        $richMenu->delete();
        return back()->with('msg', '削除しました');
    }
}

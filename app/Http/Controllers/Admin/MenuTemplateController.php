<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuTemplate;
use Illuminate\Http\Request;

class MenuTemplateController extends Controller
{
    public function index(Request $r)
    {
        $templates = MenuTemplate::when($r->category, fn ($q) => $q->where('category', $r->category))->orderBy('sort_order')->get();
        return view('admin.menu-templates.index', ['templates' => $templates, 'categories' => MenuTemplate::CATEGORIES]);
    }

    public function create() { return view('admin.menu-templates.form', ['template' => new MenuTemplate(['is_active' => true, 'image_color' => '#888888']), 'categories' => MenuTemplate::CATEGORIES]); }

    public function store(Request $r)
    {
        MenuTemplate::create($this->validated($r));
        return redirect()->route('admin.menu-templates.index')->with('msg', '作成しました');
    }

    public function edit(MenuTemplate $menuTemplate) { return view('admin.menu-templates.form', ['template' => $menuTemplate, 'categories' => MenuTemplate::CATEGORIES]); }

    public function update(Request $r, MenuTemplate $menuTemplate)
    {
        $menuTemplate->update($this->validated($r));
        return redirect()->route('admin.menu-templates.index')->with('msg', '更新しました');
    }

    public function destroy(MenuTemplate $menuTemplate)
    {
        $menuTemplate->delete();
        return redirect()->route('admin.menu-templates.index')->with('msg', '削除しました');
    }

    private function validated(Request $r): array
    {
        return $r->validate([
            'sort_order' => 'required|integer|min:0', 'name' => 'required|max:100', 'description' => 'nullable|max:200',
            'category' => 'required|in:'.implode(',', MenuTemplate::CATEGORIES), 'reference_price' => 'required|integer|min:1',
            'image_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
        ]) + ['is_active' => $r->boolean('is_active'), 'description' => (string) $r->input('description')];
    }
}

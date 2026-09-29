<?php

namespace App\Models\Mock;

class RichMenu extends MockModel
{
    protected $casts = ['actions' => 'array', 'is_default' => 'boolean'];

    // テンプレートから「押せる四角」の一覧（x, y, w, h）を作る
    public function areas(): array
    {
        return self::templateAreas($this->template);
    }

    public static function templateAreas(string $template): array
    {
        $tpl = config("lab.richmenu_templates.$template");
        if (! $tpl) return [];
        if (isset($tpl['areas'])) return array_map(fn ($a) => ['x' => $a[0], 'y' => $a[1], 'w' => $a[2], 'h' => $a[3]], $tpl['areas']);
        [$cols, $rows] = $tpl['grid'];
        $areas = [];
        for ($r = 0; $r < $rows; $r++) for ($c = 0; $c < $cols; $c++) $areas[] = ['x' => $c / $cols, 'y' => $r / $rows, 'w' => 1 / $cols, 'h' => 1 / $rows];
        return $areas;
    }
}

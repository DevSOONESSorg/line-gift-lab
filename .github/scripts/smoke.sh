#!/usr/bin/env bash
# =====================================================
# 全画面の巡回（CI 用）
#   起動中の教材（BASE_URL）に管理者でログインし、smoke-urls.php が作った
#   すべての画面を開いて「500番台のエラー（サーバーが落ちた）」が出ないか確かめます。
#   403（権限がない）や 404 は「正しく断っている」ので OK 扱い。
#
#   使い方:  BASE_URL=http://127.0.0.1:3000 bash .github/scripts/smoke.sh
# =====================================================
set -uo pipefail
BASE_URL="${BASE_URL:-http://127.0.0.1:3000}"
JAR="$(mktemp)"

# 1. 管理画面にログイン（初期データの管理者。README に書いてあるもの）
token=$(curl -s -c "$JAR" -b "$JAR" "$BASE_URL/admin/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" \
  --data-urlencode "_token=$token" --data-urlencode "email=admin@example.com" --data-urlencode "password=Taiken-2026" \
  "$BASE_URL/admin/login")
if [ "$code" != "302" ]; then echo "❌ 管理画面にログインできません（HTTP $code）"; exit 1; fi
echo "✅ 管理画面にログインしました"

# 疑似 Manager / Developers を「会社の作業アカウント」で開けるようにする（構築ナビと同じ立場）
token=$(curl -s -c "$JAR" -b "$JAR" "$BASE_URL/mock/manager" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')
curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "_token=$token" --data-urlencode "account_id=company" --data-urlencode "back=/mock/manager" "$BASE_URL/mock/switch-account"
echo "✅ 疑似LINEを「会社の作業アカウント」に切り替えました"

# 2. 画面を1つずつ開く
php .github/scripts/smoke-urls.php > /tmp/smoke-urls.txt
fail=0; total=0
while IFS= read -r line; do
  case "$line" in "#"*) echo "  ⏭  ${line#\# }"; continue ;; esac
  total=$((total + 1))
  code=$(curl -s -o /tmp/smoke-body.html -w '%{http_code}' -b "$JAR" "$BASE_URL$line")
  if [ "$code" -ge 500 ] || [ "$code" = "000" ]; then
    echo "  ❌ $code  $line"; fail=$((fail + 1))
  else
    echo "  ✅ $code  $line"
  fi
done < /tmp/smoke-urls.txt

echo ""
echo "開いた画面: $total ／ エラー: $fail"
[ "$fail" -eq 0 ]

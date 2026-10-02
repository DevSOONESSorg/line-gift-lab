# =====================================================
# main ブランチの保護ルール（Branch protection rule）
#
#   GitHub の画面では  Settings → Branches → Add rule  で設定するものを、コードで書いています。
#   terraform apply すると、この内容どおりに GitHub の設定が作られ・直されます。
#
#   このルールでできること:
#     - main には直接 push できない（必ず PR を通す）
#     - PR は、CI の6つのチェックが全部 ✅ になるまでマージボタンが押せない
#     - 管理者（自分）も例外にしない
# =====================================================
resource "github_branch_protection" "main" {
  repository_id = var.repository # 対象のリポジトリ（名前で指定できる）
  pattern       = var.branch     # 守るブランチ（"main"）

  # 管理者もルールに従う。false にすると、管理者だけは ❌ でもマージできる「抜け道」ができる
  enforce_admins = true

  # CI のチェックをマージの条件にする
  required_status_checks {
    strict   = false               # true にすると「main の最新を取り込んでから」でないとマージできない（1人開発では手間なだけなので false）
    contexts = var.required_checks # variables.tf の6つ
  }

  # main への変更は PR 経由に限る。
  # 承認（Approve）の人数は 0：1人で開発しているので、自分の PR を自分で承認できない問題を避ける
  required_pull_request_reviews {
    required_approving_review_count = 0
  }

  # main を消したり、履歴を書き換える強制 push（git push --force）を禁止する
  allows_deletions    = false
  allows_force_pushes = false
}

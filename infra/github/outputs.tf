# apply が終わったときに表示する情報（確認用）
output "protected_branch" {
  description = "保護したブランチ"
  value       = "${var.owner}/${var.repository} の ${var.branch}"
}

output "required_checks" {
  description = "マージの条件にしたチェック"
  value       = var.required_checks
}

output "settings_url" {
  description = "GitHub の画面で確認する場所"
  value       = "https://github.com/${var.owner}/${var.repository}/settings/branches"
}

variable "project_name" {
  description = "リソース名のプレフィックス"
  type        = string
  default     = "trainingmemo"
}

variable "github_repository" {
  description = "GitHub OIDCで信頼するリポジトリ(owner/repo形式)"
  type        = string
  default     = "Ntsoccer05/trainingMemo"
}

variable "local_operator_iam_user_name" {
  description = "ローカルからterraform plan/applyを実行するためのIAMユーザー名"
  type        = string
  default     = "trainingmemo-terraform-operator"
}

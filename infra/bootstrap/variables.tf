variable "project_name" {
  description = "リソース名のプレフィックス"
  type        = string
  default     = "trainingmemo"
}

variable "github_repository" {
  # GitHubは現在、OIDCトークンのsubクレームに "owner@ownerID/repo@repoID" という
  # Immutable ID形式(リポジトリ名変更の影響を受けない不変ID)を使用する。
  # CloudTrailで実測した値: repo:Ntsoccer05@62924767/TrainingMemoApp@758677209:ref:refs/heads/main
  # 単純な "owner/repo" 形式では sub クレームとマッチしないため、この形式で指定する。
  description = "GitHub OIDCで信頼するリポジトリ(owner@ownerID/repo@repoID形式)"
  type        = string
  default     = "Ntsoccer05@62924767/TrainingMemoApp@758677209"
}

variable "local_operator_iam_user_name" {
  description = "ローカルからterraform plan/applyを実行するためのIAMユーザー名"
  type        = string
  default     = "trainingmemo-terraform-operator"
}

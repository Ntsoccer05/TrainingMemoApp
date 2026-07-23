terraform {
  required_version = ">= 1.9.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.60"
    }
  }
  # ブートストラップ自体のstateはローカルで保持する(tfstate用バケットが
  # まだ存在しないため)。作成後にこのファイルを直接読み替える必要はない
  # — ブートストラップは初回のみ実行し、以後は変更しない運用とする。
}

provider "aws" {
  region = "ap-northeast-1"
}

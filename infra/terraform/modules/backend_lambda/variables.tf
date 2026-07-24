variable "project_name" {
  type = string
}

variable "vpc_id" {
  type = string
}

variable "vpc_cidr_block" {
  description = "LambdaのSGからVPC内DNSリゾルバへのegressを許可するためのVPC CIDR"
  type        = string
}

variable "private_subnet_ids" {
  type = list(string)
}

variable "db_security_group_id" {
  type = string
}

variable "deploy_bucket" {
  description = "Lambdaデプロイzipを置くS3バケット名"
  type        = string
}

variable "deploy_object_key" {
  description = "LambdaデプロイzipのS3キー(CIが更新する)"
  type        = string
  default     = "backend/placeholder.zip"
}

# ⚠️ 未検証: バージョン番号(:56)は執筆時点の推測値。
# 実際にapplyする前に `aws lambda list-layer-versions --layer-name php-82-fpm --region ap-northeast-1`
# で最新バージョンを確認し、必要なら更新すること。
variable "bref_php_layer_arn" {
  description = "Bref公式 php-82-fpm レイヤーARN(ap-northeast-1)"
  type        = string
  default     = "arn:aws:lambda:ap-northeast-1:534081306603:layer:php-82-fpm:56"
}

# ⚠️ 未検証: FPM版のバージョン番号(:56)を暫定的に流用している。
# 実際にapplyする前に `aws lambda list-layer-versions --layer-name php-82 --region ap-northeast-1`
# で最新バージョンを確認し、必要なら更新すること。
variable "bref_console_layer_arn" {
  description = "Bref公式 php-82(コンソール/CLI実行用、FPMではない)レイヤーARN(ap-northeast-1)"
  type        = string
  default     = "arn:aws:lambda:ap-northeast-1:534081306603:layer:php-82:56"
}

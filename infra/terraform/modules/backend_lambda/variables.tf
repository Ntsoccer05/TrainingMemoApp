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

# arm64(Graviton2)版。x86版よりGB秒あたり課金が約20%安く、コールドスタート時のCPU性能も同等以上。
# バージョン番号(:101)は composer.json で固定している bref/bref ^2.4 に同梱された
# vendor/bref/bref/layers.json (ap-northeast-1 / arm-php-82-fpm) の値。
# 新しいBrefバージョンに上げた際は `aws lambda list-layer-versions --layer-name arm-php-82-fpm --region ap-northeast-1`
# で最新バージョンを確認し、必要なら更新すること。
variable "bref_php_layer_arn" {
  description = "Bref公式 arm-php-82-fpm レイヤーARN(ap-northeast-1, arm64)"
  type        = string
  default     = "arn:aws:lambda:ap-northeast-1:534081306603:layer:arm-php-82-fpm:101"
}

# arm64(Graviton2)版のコンソール/CLI実行用レイヤー。バージョン番号の出典・更新方法はFPM版と同じ。
variable "bref_console_layer_arn" {
  description = "Bref公式 arm-php-82(コンソール/CLI実行用、FPMではない)レイヤーARN(ap-northeast-1, arm64)"
  type        = string
  default     = "arn:aws:lambda:ap-northeast-1:534081306603:layer:arm-php-82:101"
}

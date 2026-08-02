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
  # ⚠️ このデフォルト値は単なる初期プレースホルダーではなく、CIが実コードを
  # 継続的に上書きし続ける「安全弁」としての役割を持つ。
  # aws_lambda_function.app/artisan は s3_key を lifecycle.ignore_changes で
  # 追跡除外しているが、これは「Terraform起因の差分検知・修正」を抑制するだけであり、
  # architectures等ignore_changes対象外の属性を変更した際にTerraformがUpdateFunctionCode
  # (AWS Lambda API上、s3_bucket/s3_keyと同じ呼び出しでしか変更できない)を発行すると、
  # ignore_changes中でも「stateに残っている(=このデフォルト値のまま更新されていない)
  # 古いs3_key」が一緒に送信され、CIがデプロイした実コードを上書きしてしまう
  # (実際に発生した事故: arm64化のterraform applyが本番Lambdaのコードを
  # 216バイトのプレースホルダーで上書きし、APIが全滅した)。
  # そのため、デプロイパイプライン側もコミットSHA別のキーとは別に、常にこの
  # 固定キー(backend/latest.zip)を最新の実コードで上書きし続ける運用とし、
  # 万一この経路でUpdateFunctionCodeが走っても実害が出ないようにしている。
  description = "LambdaデプロイzipのS3キー(CIが常に最新化する固定キー)"
  type        = string
  default     = "backend/latest.zip"
}

# arm64(Graviton2)版。x86版よりGB秒あたり課金が約20%安く、コールドスタート時のCPU性能も同等以上。
# Bref 3.0からAmazon Linux 2023(provided.al2023)ベースに移行し、FPM/console/functionレイヤーが
# 単一の arm-php-82 レイヤーに統合された(動作モードはLambda関数側の BREF_RUNTIME 環境変数で切り替える)。
# アカウントID(873528684822)・バージョン番号(:23)は composer.json で固定している bref/bref ^3.0 に
# 同梱された vendor/bref/bref/layers.json (ap-northeast-1 / arm-php-82) の値。
# 新しいBrefバージョンに上げた際は `aws lambda list-layer-versions --layer-name arm-php-82 --region ap-northeast-1`
# で最新バージョンを確認し、必要なら更新すること。
variable "bref_php_layer_arn" {
  description = "Bref公式 arm-php-82 レイヤーARN(ap-northeast-1, arm64, provided.al2023)"
  type        = string
  default     = "arn:aws:lambda:ap-northeast-1:873528684822:layer:arm-php-82:23"
}

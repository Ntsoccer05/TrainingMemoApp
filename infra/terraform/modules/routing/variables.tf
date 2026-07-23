variable "project_name" {
  description = "プロジェクト名(リソース命名のプレフィックスに使用)"
  type        = string
}

variable "domain_name" {
  description = "CloudFrontに割り当てるドメイン名(既存Route53ゾーンのレコードとして設定)"
  type        = string
}

variable "route53_zone_id" {
  description = "既存Route53パブリックホストゾーンID(ACM DNS検証レコードの追加先)"
  type        = string
}

variable "s3_bucket_regional_domain_name" {
  description = "frontendモジュールで作成するSPA配信用S3バケットのリージョナルドメイン名"
  type        = string
}

variable "s3_origin_access_control_id" {
  description = "frontendモジュールで作成するCloudFront Origin Access ControlのID"
  type        = string
}

variable "api_domain_name" {
  description = "API GatewayのHTTP APIエンドポイント(https://を除いたホスト名)"
  type        = string
}

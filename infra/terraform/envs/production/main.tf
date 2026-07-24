module "frontend_bucket" {
  source       = "../../modules/frontend"
  project_name = var.project_name
}

module "nat_instance" {
  source = "../../modules/nat_instance"

  project_name           = var.project_name
  vpc_id                 = var.vpc_id
  public_subnet_id       = var.public_subnet_ids[0]
  private_route_table_id = var.private_route_table_id
}

module "backend_lambda" {
  source = "../../modules/backend_lambda"

  project_name         = var.project_name
  vpc_id               = var.vpc_id
  vpc_cidr_block       = var.vpc_cidr_block
  private_subnet_ids   = var.private_subnet_ids
  db_security_group_id = var.db_security_group_id
  deploy_bucket        = module.frontend_bucket.bucket_id
}

module "routing" {
  source = "../../modules/routing"
  providers = {
    aws.us_east_1 = aws.us_east_1
  }

  project_name                   = var.project_name
  domain_name                    = var.domain_name
  route53_zone_id                = var.route53_zone_id
  s3_bucket_regional_domain_name = module.frontend_bucket.bucket_regional_domain_name
  s3_origin_access_control_id    = module.frontend_bucket.origin_access_control_id
  api_domain_name                = replace(module.backend_lambda.api_endpoint, "https://", "")
}

# 注意: training-memo.com の Route53 Aレコード(Alias)を CloudFront へ向ける切替は、
# 本Terraformコードでは意図的に行わない。段階移行(docs/infrastructure/serverless-migration-design.md 8章)
# に基づき、動作確認が完了した後に手動 or 別のTerraform適用ステップで実施する。

# frontend/routing間の循環参照を避けるため、バケットポリシーはここで定義する
# (frontend: S3+OAC作成 → routing: CloudFront作成(frontendの出力を参照) →
#  このバケットポリシー(routingの出力=CloudFront ARNを参照) という一方向の依存関係にする)
data "aws_iam_policy_document" "frontend_bucket_policy" {
  statement {
    sid    = "AllowCloudFrontOACRead"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["cloudfront.amazonaws.com"]
    }

    actions   = ["s3:GetObject"]
    resources = ["${module.frontend_bucket.bucket_arn}/*"]

    condition {
      test     = "StringEquals"
      variable = "AWS:SourceArn"
      values   = [module.routing.cloudfront_distribution_arn]
    }
  }
}

resource "aws_s3_bucket_policy" "frontend" {
  bucket = module.frontend_bucket.bucket_id
  policy = data.aws_iam_policy_document.frontend_bucket_policy.json
}

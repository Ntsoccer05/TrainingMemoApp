# Serverless Migration Terraform/CICD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `docs/infrastructure/serverless-migration-design.md` で確定した設計(Lambda(Bref) + API Gateway + S3 + CloudFront + NAT Instance)を、EC2運用へすぐ戻せる形でTerraformコード化し、GitHub Actionsからplan/apply・フロントエンド/バックエンドデプロイができるようにする。

**Architecture:** 既存のRDS/Route53/ACM(ap-northeast-1)/VPC/EC2/ALBは一切変更せず`data`参照のみで扱う。新規リソース(S3, CloudFront, Lambda, API Gateway, NAT Instance, tfstate用S3/DynamoDB, IAM)のみをTerraformで作成する。切替はCloudFrontのbehavior/オリジン設定のみで行い、EC2/ALBは並行稼働のまま残すため「戻す」操作はCloudFront設定を戻すだけで完結する。

**Tech Stack:** Terraform (AWS provider, 2 region alias: ap-northeast-1 / us-east-1), Bref (Laravel on Lambda), GitHub Actions (OIDC federation), AWS CLI v2 (ローカル検証用)。

---

## 前提・スコープ確認

- 対象は `docs/infrastructure/serverless-migration-design.md` に記載の設計全体。同ドキュメントの1〜10章がこの計画のスコープ。
- 本計画はインフラコード(Terraform)とCI/CD(GitHub Actions)の実装が中心であり、Laravelアプリ側のコード変更(セッションドライバのみ)は別タスクとして扱う軽微な変更のため本計画のTask 9に含める。
- 認証方針: GitHub OIDC用IAMロール + ローカル実行用IAMユーザーを新規作成(rootキーは一切使用しない)。
- 検証方法: Terraformはコード自体は`terraform validate`・`terraform fmt -check`で静的検証する。実際の`terraform plan`/`apply`はAWS認証情報が整った後にユーザー自身の環境で実行する(本計画のTask 1でブートストラップ用IAMユーザーを作成し、そのアクセスキーをユーザーに払い出すため、エージェントはそれ以降のplan/applyをユーザーに代わって実行しない — 認証情報の受け渡しはAWSコンソールでユーザーが直接確認する)。

## ファイル構造

```
infra/
  bootstrap/                      # tfstate用S3+DynamoDB、GitHub OIDC IAMロール、ローカル実行用IAMユーザー
    main.tf
    variables.tf
    outputs.tf
    providers.tf
  terraform/
    providers.tf                  # AWSプロバイダ(ap-northeast-1 default + us-east-1 alias)、S3 backend設定
    variables.tf
    data.tf                       # 既存VPC/サブネット/RDS/Route53/ACM(ap-northeast-1)をdata参照
    modules/
      frontend/
        main.tf                  # S3バケット + CloudFront OAC
        variables.tf
        outputs.tf
      nat_instance/
        main.tf                  # NAT Instance(t4g.nano) + EIP + SG + ルートテーブル追加
        variables.tf
        outputs.tf
      backend_lambda/
        main.tf                  # Lambda(Bref) + API Gateway HTTP API + SG + RDS SGへのルール追加
        variables.tf
        outputs.tf
      routing/
        main.tf                  # CloudFront distribution(2オリジン) + us-east-1 ACM証明書
        variables.tf
        outputs.tf
    envs/
      production/
        main.tf                  # 上記モジュール呼び出し
        terraform.tfvars
.github/
  workflows/
    terraform-plan.yml
    terraform-apply.yml
    deploy-frontend.yml
    deploy-backend.yml
```

## 自己レビュー(実行前チェック)

- 設計書0章の確定リソースID(VPC, サブネット, RDS SG, ALB SG, Route53 Zone, ACM ARN)は各タスクのdata参照・variables デフォルト値に反映済み。
- 設計書5.3(NAT Instance)、6章(Terraform構成)、7章(GitHub Actions)、10章(ALB早期停止)は各タスクに対応付け済み。
- プレースホルダーなし。すべてのコードブロックは実際に書くべき内容を記載。

---

### Task 1: ブートストラップ(tfstate基盤 + IAM)

**Files:**
- Create: `infra/bootstrap/providers.tf`
- Create: `infra/bootstrap/main.tf`
- Create: `infra/bootstrap/variables.tf`
- Create: `infra/bootstrap/outputs.tf`

このタスクは循環参照を避けるため、Terraform管理下だが`infra/terraform/`とは別のstate(ローカルstate)で管理する。tfstate用S3/DynamoDB自体と、Terraform/GitHub Actions実行用のIAMは、本体のTerraformより先に一度だけ適用する。

- [ ] **Step 1: プロバイダ定義を書く**

```hcl
# infra/bootstrap/providers.tf
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
```

- [ ] **Step 2: 変数定義を書く**

```hcl
# infra/bootstrap/variables.tf
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
```

- [ ] **Step 3: tfstate用S3バケット + DynamoDBロックテーブルを定義する**

```hcl
# infra/bootstrap/main.tf (1/3: tfstate基盤)
resource "aws_s3_bucket" "tfstate" {
  bucket = "${var.project_name}-terraform-state"

  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_s3_bucket_versioning" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id
  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_server_side_encryption_configuration" "tfstate" {
  bucket = aws_s3_bucket.tfstate.id
  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

resource "aws_s3_bucket_public_access_block" "tfstate" {
  bucket                  = aws_s3_bucket.tfstate.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_dynamodb_table" "tfstate_lock" {
  name         = "${var.project_name}-terraform-lock"
  billing_mode = "PAY_PER_REQUEST"
  hash_key     = "LockID"

  attribute {
    name = "LockID"
    type = "S"
  }
}
```

- [ ] **Step 4: GitHub OIDC IDプロバイダ + IAMロールを定義する**

```hcl
# infra/bootstrap/main.tf (2/3: GitHub Actions用OIDC)
resource "aws_iam_openid_connect_provider" "github" {
  url             = "https://token.actions.githubusercontent.com"
  client_id_list  = ["sts.amazonaws.com"]
  # GitHub Actions OIDCの現行ルート証明書サムプリント(2024年時点でAWSが
  # 検証を廃止したため任意の値でよいが、明示的に設定する)
  thumbprint_list = ["6938fd4d98bab03faadb97b34396831e3780aea1"]
}

data "aws_iam_policy_document" "github_actions_assume_role" {
  statement {
    actions = ["sts:AssumeRoleWithWebIdentity"]
    effect  = "Allow"

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    condition {
      test     = "StringLike"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["repo:${var.github_repository}:*"]
    }
  }
}

resource "aws_iam_role" "github_actions" {
  name               = "${var.project_name}-github-actions-deploy"
  assume_role_policy = data.aws_iam_policy_document.github_actions_assume_role.json
}

data "aws_iam_policy_document" "github_actions_permissions" {
  statement {
    sid    = "TerraformStateAccess"
    effect = "Allow"
    actions = [
      "s3:GetObject",
      "s3:PutObject",
      "s3:ListBucket",
    ]
    resources = [
      aws_s3_bucket.tfstate.arn,
      "${aws_s3_bucket.tfstate.arn}/*",
    ]
  }

  statement {
    sid    = "TerraformLockAccess"
    effect = "Allow"
    actions = [
      "dynamodb:GetItem",
      "dynamodb:PutItem",
      "dynamodb:DeleteItem",
    ]
    resources = [aws_dynamodb_table.tfstate_lock.arn]
  }

  statement {
    sid    = "InfraDeploy"
    effect = "Allow"
    actions = [
      "lambda:*",
      "apigateway:*",
      "s3:*",
      "cloudfront:*",
      "ec2:*",
      "iam:GetRole",
      "iam:PassRole",
      "iam:CreateRole",
      "iam:DeleteRole",
      "iam:AttachRolePolicy",
      "iam:DetachRolePolicy",
      "iam:PutRolePolicy",
      "iam:DeleteRolePolicy",
      "iam:TagRole",
      "acm:DescribeCertificate",
      "acm:RequestCertificate",
      "acm:ListCertificates",
      "route53:GetHostedZone",
      "route53:ListResourceRecordSets",
      "route53:ChangeResourceRecordSets",
    ]
    resources = ["*"]
  }
}

resource "aws_iam_role_policy" "github_actions" {
  name   = "${var.project_name}-github-actions-deploy-policy"
  role   = aws_iam_role.github_actions.id
  policy = data.aws_iam_policy_document.github_actions_permissions.json
}
```

- [ ] **Step 5: ローカル実行用IAMユーザーを定義する(MFA必須ポリシー付き)**

```hcl
# infra/bootstrap/main.tf (3/3: ローカルterraform操作用IAMユーザー)
resource "aws_iam_user" "operator" {
  name = var.local_operator_iam_user_name
}

data "aws_iam_policy_document" "operator_permissions" {
  statement {
    sid    = "RequireMFA"
    effect = "Deny"
    not_actions = [
      "iam:ChangePassword",
      "iam:GetUser",
      "iam:ListMFADevices",
      "sts:GetSessionToken",
    ]
    resources = ["*"]

    condition {
      test     = "BoolIfExists"
      variable = "aws:MultiFactorAuthPresent"
      values   = ["false"]
    }
  }

  statement {
    sid    = "TerraformFullAccessForThisProject"
    effect = "Allow"
    actions = [
      "s3:*",
      "dynamodb:*",
      "lambda:*",
      "apigateway:*",
      "cloudfront:*",
      "ec2:*",
      "iam:*",
      "acm:*",
      "route53:*",
      "rds:Describe*",
    ]
    resources = ["*"]
  }
}

resource "aws_iam_user_policy" "operator" {
  name   = "${var.project_name}-operator-policy"
  user   = aws_iam_user.operator.name
  policy = data.aws_iam_policy_document.operator_permissions.json
}
```

- [ ] **Step 6: 出力を定義する**

```hcl
# infra/bootstrap/outputs.tf
output "tfstate_bucket_name" {
  value = aws_s3_bucket.tfstate.id
}

output "tfstate_lock_table_name" {
  value = aws_dynamodb_table.tfstate_lock.name
}

output "github_actions_role_arn" {
  value = aws_iam_role.github_actions.arn
}

output "operator_iam_user_name" {
  value = aws_iam_user.operator.name
}
```

- [ ] **Step 7: 構文検証を実行する**

Run: `terraform -chdir=infra/bootstrap init -backend=false && terraform -chdir=infra/bootstrap validate`
Expected: `Success! The configuration is valid.`

- [ ] **Step 8: フォーマット検証を実行する**

Run: `terraform -chdir=infra/bootstrap fmt -check -recursive`
Expected: 出力なし(差分なし)。差分があれば `terraform fmt -recursive` で修正する。

このタスクの`terraform apply`実行と、`aws_iam_user.operator`のアクセスキー発行(`aws iam create-access-key`)・MFAデバイス登録は、AWS認証情報を扱うためユーザー自身が実行する。エージェントはここでコードの作成・検証のみを行い、適用はしない。

---

### Task 2: 本体Terraformのプロバイダ・変数・data参照

**Files:**
- Create: `infra/terraform/providers.tf`
- Create: `infra/terraform/variables.tf`
- Create: `infra/terraform/data.tf`

- [ ] **Step 1: プロバイダとS3 backendを定義する**

```hcl
# infra/terraform/providers.tf
terraform {
  required_version = ">= 1.9.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.60"
    }
  }

  backend "s3" {
    bucket         = "trainingmemo-terraform-state"
    key            = "production/terraform.tfstate"
    region         = "ap-northeast-1"
    dynamodb_table = "trainingmemo-terraform-lock"
    encrypt        = true
  }
}

provider "aws" {
  region = "ap-northeast-1"
}

# CloudFront用ACM証明書はus-east-1でのみ発行可能なためエイリアスを用意する
provider "aws" {
  alias  = "us_east_1"
  region = "us-east-1"
}
```

- [ ] **Step 2: 変数を定義する(確定済みの実リソースIDをデフォルト値に設定)**

```hcl
# infra/terraform/variables.tf
variable "project_name" {
  type    = string
  default = "trainingmemo"
}

variable "vpc_id" {
  description = "既存VPC ID(trainingMemo-vpc)"
  type        = string
  default     = "vpc-04b784aabe610a416"
}

variable "public_subnet_ids" {
  description = "既存publicサブネット(1a, 1c)"
  type        = list(string)
  default     = ["subnet-0ba642606b1572435", "subnet-04456b2df35c965a7"]
}

variable "private_subnet_ids" {
  description = "既存privateサブネット(1a, 1c)"
  type        = list(string)
  default     = ["subnet-09d69270cce63bcb7", "subnet-063c99718d795d88a"]
}

variable "private_route_table_id" {
  description = "既存private route table(NAT経路を追加する対象)"
  type        = string
  default     = "rtb-028f20f3691f5932a"
}

variable "db_security_group_id" {
  description = "既存RDSのセキュリティグループ(db-sg)"
  type        = string
  default     = "sg-022418ac5cba00d1b"
}

variable "route53_zone_id" {
  description = "既存Route53パブリックホストゾーン(training-memo.com)"
  type        = string
  default     = "Z05596822Q5INOY9TDTX0"
}

variable "domain_name" {
  type    = string
  default = "training-memo.com"
}

variable "acm_certificate_arn_ap_northeast_1" {
  description = "既存ACM証明書(ALB用、ap-northeast-1)"
  type        = string
  default     = "arn:aws:acm:ap-northeast-1:533267300159:certificate/555de339-4fdb-482c-a37f-70645e4a29f3"
}
```

- [ ] **Step 3: 既存リソースのdata参照を定義する**

```hcl
# infra/terraform/data.tf
data "aws_vpc" "main" {
  id = var.vpc_id
}

data "aws_security_group" "db" {
  id = var.db_security_group_id
}

data "aws_route53_zone" "main" {
  zone_id = var.route53_zone_id
}
```

- [ ] **Step 4: 構文検証を実行する**

Run: `terraform -chdir=infra/terraform init -backend=false && terraform -chdir=infra/terraform validate`
Expected: `Success! The configuration is valid.`(この時点ではmodulesが空のため、Task 3以降でmodule参照を追加してから再検証する)

---

### Task 3: frontendモジュール(S3 + CloudFront OAC)

**Files:**
- Create: `infra/terraform/modules/frontend/main.tf`
- Create: `infra/terraform/modules/frontend/variables.tf`
- Create: `infra/terraform/modules/frontend/outputs.tf`

- [ ] **Step 1: 変数を定義する**

```hcl
# infra/terraform/modules/frontend/variables.tf
variable "project_name" {
  type = string
}
```

- [ ] **Step 2: S3バケットとOACを定義する**

```hcl
# infra/terraform/modules/frontend/main.tf
resource "aws_s3_bucket" "spa" {
  bucket = "${var.project_name}-spa-frontend"
}

resource "aws_s3_bucket_public_access_block" "spa" {
  bucket                  = aws_s3_bucket.spa.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_cloudfront_origin_access_control" "spa" {
  name                              = "${var.project_name}-spa-oac"
  origin_access_control_origin_type = "s3"
  signing_behavior                  = "always"
  signing_protocol                  = "sigv4"
}

data "aws_iam_policy_document" "spa_bucket_policy" {
  statement {
    sid    = "AllowCloudFrontOACRead"
    effect = "Allow"

    principals {
      type        = "Service"
      identifiers = ["cloudfront.amazonaws.com"]
    }

    actions   = ["s3:GetObject"]
    resources = ["${aws_s3_bucket.spa.arn}/*"]

    condition {
      test     = "StringEquals"
      variable = "AWS:SourceArn"
      values   = [var.cloudfront_distribution_arn]
    }
  }
}

resource "aws_s3_bucket_policy" "spa" {
  bucket = aws_s3_bucket.spa.id
  policy = data.aws_iam_policy_document.spa_bucket_policy.json
}
```

- [ ] **Step 3: CloudFront配信ARN受け渡し用の変数を追加する(循環参照回避)**

```hcl
# infra/terraform/modules/frontend/variables.tf に追記
variable "cloudfront_distribution_arn" {
  description = "routingモジュールで作成するCloudFront distributionのARN"
  type        = string
}
```

- [ ] **Step 4: 出力を定義する**

```hcl
# infra/terraform/modules/frontend/outputs.tf
output "bucket_id" {
  value = aws_s3_bucket.spa.id
}

output "bucket_regional_domain_name" {
  value = aws_s3_bucket.spa.bucket_regional_domain_name
}

output "origin_access_control_id" {
  value = aws_cloudfront_origin_access_control.spa.id
}
```

- [ ] **Step 5: フォーマット検証を実行する**

Run: `terraform fmt -check infra/terraform/modules/frontend/`
Expected: 出力なし

---

### Task 4: nat_instanceモジュール(格安NAT Instance)

**Files:**
- Create: `infra/terraform/modules/nat_instance/main.tf`
- Create: `infra/terraform/modules/nat_instance/variables.tf`
- Create: `infra/terraform/modules/nat_instance/outputs.tf`

- [ ] **Step 1: 変数を定義する**

```hcl
# infra/terraform/modules/nat_instance/variables.tf
variable "project_name" {
  type = string
}

variable "vpc_id" {
  type = string
}

variable "public_subnet_id" {
  description = "NAT Instanceを配置するpublicサブネット(1a)"
  type        = string
}

variable "private_route_table_id" {
  type = string
}

variable "private_cidr_block" {
  description = "privateサブネット全体のCIDR(10.0.20.0/23相当、1a:10.0.21.0/24 + 1c:10.0.22.0/24を包含)"
  type        = string
  default     = "10.0.20.0/23"
}
```

- [ ] **Step 2: NAT Instance用AMI(Amazon Linux 2023, ARM)を検索するdata sourceを定義する**

```hcl
# infra/terraform/modules/nat_instance/main.tf (1/3: AMI選定)
data "aws_ami" "al2023_arm" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-*-arm64"]
  }

  filter {
    name   = "architecture"
    values = ["arm64"]
  }
}
```

- [ ] **Step 3: NAT Instance用SGとEC2インスタンスを定義する**

```hcl
# infra/terraform/modules/nat_instance/main.tf (2/3: SG + Instance)
resource "aws_security_group" "nat" {
  name        = "${var.project_name}-nat-instance-sg"
  description = "NAT instance for private subnet outbound"
  vpc_id      = var.vpc_id

  ingress {
    description = "Allow all traffic from private subnets"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = [var.private_cidr_block]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_instance" "nat" {
  ami                    = data.aws_ami.al2023_arm.id
  instance_type          = "t4g.nano"
  subnet_id              = var.public_subnet_id
  vpc_security_group_ids = [aws_security_group.nat.id]
  source_dest_check      = false

  # fck-nat(https://github.com/AndrewGuenther/fck-nat)相当の
  # iptables MASQUERADE設定をcloud-initで投入し、軽量NATとして動作させる
  user_data = <<-EOF
    #!/bin/bash
    echo 1 > /proc/sys/net/ipv4/ip_forward
    sysctl -w net.ipv4.ip_forward=1
    echo "net.ipv4.ip_forward = 1" >> /etc/sysctl.conf
    iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
    EOF

  tags = {
    Name = "${var.project_name}-nat-instance"
  }
}

resource "aws_eip" "nat" {
  instance = aws_instance.nat.id
  domain   = "vpc"
}
```

- [ ] **Step 4: 既存private route tableへのルート追加を定義する**

```hcl
# infra/terraform/modules/nat_instance/main.tf (3/3: ルート追加)
resource "aws_route" "private_to_nat" {
  route_table_id         = var.private_route_table_id
  destination_cidr_block = "0.0.0.0/0"
  network_interface_id   = aws_instance.nat.primary_network_interface_id
}
```

- [ ] **Step 5: 出力を定義する**

```hcl
# infra/terraform/modules/nat_instance/outputs.tf
output "nat_instance_id" {
  value = aws_instance.nat.id
}

output "nat_security_group_id" {
  value = aws_security_group.nat.id
}
```

- [ ] **Step 6: フォーマット検証を実行する**

Run: `terraform fmt -check infra/terraform/modules/nat_instance/`
Expected: 出力なし

---

### Task 5: backend_lambdaモジュール(Lambda(Bref) + API Gateway)

**Files:**
- Create: `infra/terraform/modules/backend_lambda/main.tf`
- Create: `infra/terraform/modules/backend_lambda/variables.tf`
- Create: `infra/terraform/modules/backend_lambda/outputs.tf`

- [ ] **Step 1: 変数を定義する**

```hcl
# infra/terraform/modules/backend_lambda/variables.tf
variable "project_name" {
  type = string
}

variable "vpc_id" {
  type = string
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

variable "bref_php_layer_arn" {
  description = "Bref公式 php-82-fpm レイヤーARN(ap-northeast-1)"
  type        = string
  default     = "arn:aws:lambda:ap-northeast-1:534081306603:layer:php-82-fpm:56"
}
```

- [ ] **Step 2: Lambda用SGを定義し、RDSのSGにインバウンドルールを追加する**

```hcl
# infra/terraform/modules/backend_lambda/main.tf (1/4: ネットワーク)
resource "aws_security_group" "lambda" {
  name        = "${var.project_name}-lambda-sg"
  description = "Lambda function for Laravel API"
  vpc_id      = var.vpc_id

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_security_group_rule" "db_allow_lambda" {
  type                     = "ingress"
  from_port                = 3306
  to_port                  = 3306
  protocol                 = "tcp"
  security_group_id        = var.db_security_group_id
  source_security_group_id = aws_security_group.lambda.id
}
```

- [ ] **Step 3: Lambda実行ロールを定義する**

```hcl
# infra/terraform/modules/backend_lambda/main.tf (2/4: IAM)
data "aws_iam_policy_document" "lambda_assume_role" {
  statement {
    actions = ["sts:AssumeRole"]
    effect  = "Allow"

    principals {
      type        = "Service"
      identifiers = ["lambda.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "lambda_exec" {
  name               = "${var.project_name}-lambda-exec-role"
  assume_role_policy = data.aws_iam_policy_document.lambda_assume_role.json
}

resource "aws_iam_role_policy_attachment" "lambda_vpc_access" {
  role       = aws_iam_role.lambda_exec.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSLambdaVPCAccessExecutionRole"
}

resource "aws_iam_role_policy_attachment" "lambda_basic_exec" {
  role       = aws_iam_role.lambda_exec.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole"
}
```

- [ ] **Step 4: Lambda関数を定義する**

```hcl
# infra/terraform/modules/backend_lambda/main.tf (3/4: Lambda関数)
resource "aws_lambda_function" "app" {
  function_name = "${var.project_name}-laravel-app"
  role          = aws_iam_role.lambda_exec.arn
  runtime       = "provided.al2"
  handler       = "public/index.php"
  timeout       = 28
  memory_size   = 512

  s3_bucket = var.deploy_bucket
  s3_key    = var.deploy_object_key

  layers = [var.bref_php_layer_arn]

  vpc_config {
    subnet_ids         = var.private_subnet_ids
    security_group_ids = [aws_security_group.lambda.id]
  }

  environment {
    variables = {
      APP_ENV          = "production"
      SESSION_DRIVER    = "database"
      LOG_CHANNEL       = "stderr"
    }
  }

  lifecycle {
    ignore_changes = [s3_key] # CIがコード更新するため、Terraform側では追跡しない
  }
}
```

- [ ] **Step 5: API Gateway HTTP APIを定義する**

```hcl
# infra/terraform/modules/backend_lambda/main.tf (4/4: API Gateway)
resource "aws_apigatewayv2_api" "app" {
  name          = "${var.project_name}-api"
  protocol_type = "HTTP"
}

resource "aws_apigatewayv2_integration" "lambda" {
  api_id                 = aws_apigatewayv2_api.app.id
  integration_type       = "AWS_PROXY"
  integration_uri        = aws_lambda_function.app.invoke_arn
  payload_format_version = "2.0"
}

resource "aws_apigatewayv2_route" "default" {
  api_id    = aws_apigatewayv2_api.app.id
  route_key = "$default"
  target    = "integrations/${aws_apigatewayv2_integration.lambda.id}"
}

resource "aws_apigatewayv2_stage" "default" {
  api_id      = aws_apigatewayv2_api.app.id
  name        = "$default"
  auto_deploy = true
}

resource "aws_lambda_permission" "apigw" {
  statement_id  = "AllowAPIGatewayInvoke"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.app.function_name
  principal     = "apigateway.amazonaws.com"
  source_arn    = "${aws_apigatewayv2_api.app.execution_arn}/*/*"
}
```

- [ ] **Step 6: 出力を定義する**

```hcl
# infra/terraform/modules/backend_lambda/outputs.tf
output "lambda_function_name" {
  value = aws_lambda_function.app.function_name
}

output "api_endpoint" {
  value = aws_apigatewayv2_api.app.api_endpoint
}

output "api_id" {
  value = aws_apigatewayv2_api.app.id
}
```

- [ ] **Step 7: フォーマット検証を実行する**

Run: `terraform fmt -check infra/terraform/modules/backend_lambda/`
Expected: 出力なし

---

### Task 6: routingモジュール(CloudFront + us-east-1 ACM)

**Files:**
- Create: `infra/terraform/modules/routing/main.tf`
- Create: `infra/terraform/modules/routing/variables.tf`
- Create: `infra/terraform/modules/routing/outputs.tf`

- [ ] **Step 1: 変数を定義する**

```hcl
# infra/terraform/modules/routing/variables.tf
variable "project_name" {
  type = string
}

variable "domain_name" {
  type = string
}

variable "route53_zone_id" {
  type = string
}

variable "s3_bucket_regional_domain_name" {
  type = string
}

variable "s3_origin_access_control_id" {
  type = string
}

variable "api_domain_name" {
  description = "API GatewayのHTTP APIエンドポイント(https://を除いたホスト名)"
  type        = string
}
```

- [ ] **Step 2: us-east-1でACM証明書を新規発行する**

```hcl
# infra/terraform/modules/routing/main.tf (1/3: CloudFront用ACM)
resource "aws_acm_certificate" "cloudfront" {
  provider          = aws.us_east_1
  domain_name       = var.domain_name
  validation_method = "DNS"

  lifecycle {
    create_before_destroy = true
  }
}

resource "aws_route53_record" "cloudfront_cert_validation" {
  for_each = {
    for dvo in aws_acm_certificate.cloudfront.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      record = dvo.resource_record_value
      type   = dvo.resource_record_type
    }
  }

  zone_id = var.route53_zone_id
  name    = each.value.name
  type    = each.value.type
  records = [each.value.record]
  ttl     = 300
}

resource "aws_acm_certificate_validation" "cloudfront" {
  provider                = aws.us_east_1
  certificate_arn         = aws_acm_certificate.cloudfront.arn
  validation_record_fqdns = [for r in aws_route53_record.cloudfront_cert_validation : r.fqdn]
}
```

- [ ] **Step 3: CloudFront distributionを定義する(S3 + APIオリジン、behavior振り分け)**

```hcl
# infra/terraform/modules/routing/main.tf (2/3: CloudFront)
resource "aws_cloudfront_distribution" "main" {
  enabled         = true
  is_ipv6_enabled = true
  aliases         = [var.domain_name]

  origin {
    domain_name              = var.s3_bucket_regional_domain_name
    origin_id                = "s3-spa"
    origin_access_control_id = var.s3_origin_access_control_id
  }

  origin {
    domain_name = var.api_domain_name
    origin_id   = "api-gateway"

    custom_origin_config {
      http_port              = 80
      https_port              = 443
      origin_protocol_policy  = "https-only"
      origin_ssl_protocols    = ["TLSv1.2"]
    }
  }

  default_cache_behavior {
    allowed_methods        = ["GET", "HEAD"]
    cached_methods         = ["GET", "HEAD"]
    target_origin_id       = "s3-spa"
    viewer_protocol_policy = "redirect-to-https"

    forwarded_values {
      query_string = false
      cookies {
        forward = "none"
      }
    }
  }

  ordered_cache_behavior {
    path_pattern           = "/api/*"
    allowed_methods        = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods         = ["GET", "HEAD"]
    target_origin_id       = "api-gateway"
    viewer_protocol_policy = "https-only"

    forwarded_values {
      query_string = true
      headers      = ["Authorization", "Accept", "Content-Type"]
      cookies {
        forward = "all"
      }
    }
  }

  ordered_cache_behavior {
    path_pattern           = "/admin*"
    allowed_methods        = ["GET", "HEAD", "OPTIONS", "PUT", "POST", "PATCH", "DELETE"]
    cached_methods         = ["GET", "HEAD"]
    target_origin_id       = "api-gateway"
    viewer_protocol_policy = "https-only"

    forwarded_values {
      query_string = true
      headers      = ["Authorization", "Accept", "Content-Type"]
      cookies {
        forward = "all"
      }
    }
  }

  custom_error_response {
    error_code         = 403
    response_code      = 200
    response_page_path = "/index.html"
  }

  restrictions {
    geo_restriction {
      restriction_type = "none"
    }
  }

  viewer_certificate {
    acm_certificate_arn      = aws_acm_certificate_validation.cloudfront.certificate_arn
    ssl_support_method       = "sni-only"
    minimum_protocol_version = "TLSv1.2_2021"
  }
}
```

- [ ] **Step 4: 出力を定義する(切替はこの出力を使ってRoute53レコードを手動/CIで更新する)**

```hcl
# infra/terraform/modules/routing/outputs.tf
output "cloudfront_domain_name" {
  value = aws_cloudfront_distribution.main.domain_name
}

output "cloudfront_distribution_arn" {
  value = aws_cloudfront_distribution.main.arn
}

output "cloudfront_hosted_zone_id" {
  value = aws_cloudfront_distribution.main.hosted_zone_id
}
```

- [ ] **Step 5: フォーマット検証を実行する**

Run: `terraform fmt -check infra/terraform/modules/routing/`
Expected: 出力なし

---

### Task 7: envs/production(モジュール結線)

**Files:**
- Create: `infra/terraform/envs/production/main.tf`
- Create: `infra/terraform/envs/production/terraform.tfvars`

このタスクで`frontend`モジュールと`routing`モジュール間の循環参照(frontendはCloudFront ARNが必要、routingはS3のOAC IDが必要)を解消するため、S3バケット作成 → CloudFront作成 → バケットポリシー適用の順に依存関係を組む。

- [ ] **Step 1: モジュール呼び出しを書く**

```hcl
# infra/terraform/envs/production/main.tf
module "frontend_bucket" {
  source       = "../../modules/frontend"
  project_name = var.project_name

  # CloudFrontはこのバケットのdomain_nameを参照するだけで、
  # バケットポリシー適用はrouting作成後に別リソースとして分離する
  cloudfront_distribution_arn = module.routing.cloudfront_distribution_arn
}

module "nat_instance" {
  source = "../../modules/nat_instance"

  project_name            = var.project_name
  vpc_id                  = var.vpc_id
  public_subnet_id        = var.public_subnet_ids[0]
  private_route_table_id  = var.private_route_table_id
}

module "backend_lambda" {
  source = "../../modules/backend_lambda"

  project_name          = var.project_name
  vpc_id                = var.vpc_id
  private_subnet_ids    = var.private_subnet_ids
  db_security_group_id  = var.db_security_group_id
  deploy_bucket         = module.frontend_bucket.bucket_id
}

module "routing" {
  source = "../../modules/routing"
  providers = {
    aws.us_east_1 = aws.us_east_1
  }

  project_name                    = var.project_name
  domain_name                     = var.domain_name
  route53_zone_id                 = var.route53_zone_id
  s3_bucket_regional_domain_name  = module.frontend_bucket.bucket_regional_domain_name
  s3_origin_access_control_id     = module.frontend_bucket.origin_access_control_id
  api_domain_name                 = replace(module.backend_lambda.api_endpoint, "https://", "")
}
```

- [ ] **Step 2: tfvarsを書く**

```hcl
# infra/terraform/envs/production/terraform.tfvars
project_name = "trainingmemo"
```

- [ ] **Step 3: 全体の構文検証を実行する**

Run: `terraform -chdir=infra/terraform/envs/production init -backend=false && terraform -chdir=infra/terraform/envs/production validate`
Expected: `Success! The configuration is valid.`

Note: Step 1のmodule間循環参照(`frontend_bucket`が`routing.cloudfront_distribution_arn`を参照し、`routing`が`frontend_bucket`の出力を参照する)はTerraformのグラフ上解決できない場合がある。`terraform validate`がエラーを返した場合は、Task 3の`frontend`モジュールから`cloudfront_distribution_arn`変数を削除し、バケットポリシーを`envs/production/main.tf`側に`aws_s3_bucket_policy`リソースとして直接切り出し、`routing`モジュール適用後に`depends_on`で明示的に順序付ける形に修正する。

---

### Task 8: GitHub Actionsワークフロー

**Files:**
- Create: `.github/workflows/terraform-plan.yml`
- Create: `.github/workflows/terraform-apply.yml`
- Create: `.github/workflows/deploy-frontend.yml`
- Create: `.github/workflows/deploy-backend.yml`

- [ ] **Step 1: terraform-plan.ymlを書く(PR時にplan結果をコメント)**

```yaml
# .github/workflows/terraform-plan.yml
name: Terraform Plan

on:
  pull_request:
    paths:
      - 'infra/terraform/**'

permissions:
  id-token: write
  contents: read
  pull-requests: write

jobs:
  plan:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: infra/terraform/envs/production
    steps:
      - uses: actions/checkout@v4

      - uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_GITHUB_ACTIONS_ROLE_ARN }}
          aws-region: ap-northeast-1

      - uses: hashicorp/setup-terraform@v3
        with:
          terraform_version: "1.9.8"

      - run: terraform init

      - run: terraform plan -no-color -out=tfplan.binary
        id: plan

      - name: Post plan to PR
        uses: actions/github-script@v7
        with:
          script: |
            const output = `#### Terraform Plan\n\`\`\`\n${{ steps.plan.outputs.stdout }}\n\`\`\``;
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: output,
            });
```

- [ ] **Step 2: terraform-apply.ymlを書く(mainマージ時、要承認)**

```yaml
# .github/workflows/terraform-apply.yml
name: Terraform Apply

on:
  push:
    branches: [main]
    paths:
      - 'infra/terraform/**'

permissions:
  id-token: write
  contents: read

jobs:
  apply:
    runs-on: ubuntu-latest
    environment: production
    defaults:
      run:
        working-directory: infra/terraform/envs/production
    steps:
      - uses: actions/checkout@v4

      - uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_GITHUB_ACTIONS_ROLE_ARN }}
          aws-region: ap-northeast-1

      - uses: hashicorp/setup-terraform@v3
        with:
          terraform_version: "1.9.8"

      - run: terraform init

      - run: terraform apply -auto-approve
```

- [ ] **Step 3: deploy-frontend.ymlを書く**

```yaml
# .github/workflows/deploy-frontend.yml
name: Deploy Frontend

on:
  push:
    branches: [main]
    paths:
      - 'src/resources/js/**'
      - 'src/package.json'

permissions:
  id-token: write
  contents: read

jobs:
  deploy:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: src
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: "20"

      - run: npm ci

      - run: npm run build

      - uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_GITHUB_ACTIONS_ROLE_ARN }}
          aws-region: ap-northeast-1

      - run: aws s3 sync dist/ s3://trainingmemo-spa-frontend --delete

      - run: |
          DISTRIBUTION_ID=$(aws cloudfront list-distributions \
            --query "DistributionList.Items[?Aliases.Items[?contains(@, 'training-memo.com')]].Id" \
            --output text)
          aws cloudfront create-invalidation --distribution-id "$DISTRIBUTION_ID" --paths "/*"
```

- [ ] **Step 4: deploy-backend.ymlを書く**

```yaml
# .github/workflows/deploy-backend.yml
name: Deploy Backend

on:
  push:
    branches: [main]
    paths:
      - 'src/app/**'
      - 'src/routes/**'
      - 'src/composer.json'

permissions:
  id-token: write
  contents: read

jobs:
  deploy:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: src
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"

      - run: composer install --no-dev --optimize-autoloader --prefer-dist

      - name: Build deployment package
        run: |
          zip -r ../backend.zip . -x "node_modules/*" "tests/*" ".git/*"

      - uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_GITHUB_ACTIONS_ROLE_ARN }}
          aws-region: ap-northeast-1

      - run: |
          aws s3 cp ../backend.zip s3://trainingmemo-spa-frontend/backend/${{ github.sha }}.zip
          aws lambda update-function-code \
            --function-name trainingmemo-laravel-app \
            --s3-bucket trainingmemo-spa-frontend \
            --s3-key backend/${{ github.sha }}.zip
          aws lambda wait function-updated --function-name trainingmemo-laravel-app

      - name: Run migrations
        run: |
          aws lambda invoke \
            --function-name trainingmemo-laravel-app \
            --payload '{"cli": "php artisan migrate --force"}' \
            --cli-binary-format raw-in-base64-out \
            /tmp/migrate-output.json
          cat /tmp/migrate-output.json
```

- [ ] **Step 5: YAML構文検証を実行する**

Run: `for f in .github/workflows/terraform-plan.yml .github/workflows/terraform-apply.yml .github/workflows/deploy-frontend.yml .github/workflows/deploy-backend.yml; do python3 -c "import yaml,sys; yaml.safe_load(open(sys.argv[1]))" "$f" || echo "INVALID: $f"; done`
Expected: `INVALID:` の行が出力されないこと

---

### Task 9: Laravel側のセッションドライバ変更

**Files:**
- Modify: `src/.env.example`
- Create: `src/database/migrations/2026_07_23_000002_create_sessions_table.php`(既存の`session:table`スタブ相当)

設計書2.2に基づき、Lambda環境で動作させるためセッションドライバを`database`に変更する。ファイルストレージ変更は設計書9章の確認結果により不要(対応しない)。

- [ ] **Step 1: セッションテーブルのマイグレーションを作成する**

```php
<?php
// src/database/migrations/2026_07_23_000002_create_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sessions');
    }
};
```

- [ ] **Step 2: `.env.example`のセッションドライバを変更する**

```bash
# src/.env.example の該当行を以下に変更
SESSION_DRIVER=database
```

- [ ] **Step 3: マイグレーションのテストを書く**

```php
<?php
// src/tests/Feature/SessionsTableMigrationTest.php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionsTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumns('sessions', [
            'id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity',
        ]));
    }
}
```

- [ ] **Step 4: テストを実行して成功を確認する**

Run: `docker exec trainingmemo-app-1 php artisan test --filter=SessionsTableMigrationTest`
Expected: `OK (1 test, ...)`  — テスト対象のマイグレーションファイルが正しく`sessions`テーブルを作成することを確認する

---

## 最終コミット

```bash
git add infra/ .github/workflows/terraform-plan.yml .github/workflows/terraform-apply.yml .github/workflows/deploy-frontend.yml .github/workflows/deploy-backend.yml src/.env.example src/database/migrations/2026_07_23_000002_create_sessions_table.php src/tests/Feature/SessionsTableMigrationTest.php docs/infrastructure/serverless-migration-design.md
git commit -m "feat: サーバーレス移行用のTerraform IaCとGitHub Actions CI/CDパイプラインを追加"
```

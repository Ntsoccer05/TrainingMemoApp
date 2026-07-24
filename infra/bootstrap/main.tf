# --- tfstate基盤 ---

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

# --- GitHub Actions用OIDC ---

resource "aws_iam_openid_connect_provider" "github" {
  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]
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
      "iam:ListRolePolicies",
      "iam:GetRolePolicy",
      "iam:ListAttachedRolePolicies",
      "acm:DescribeCertificate",
      "acm:RequestCertificate",
      "acm:ListCertificates",
      "acm:ListTagsForCertificate",
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

# --- ローカルterraform操作用IAMユーザー ---

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

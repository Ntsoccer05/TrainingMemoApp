# ------------------------------------------------------------------
# 1/4: ネットワーク
# ------------------------------------------------------------------
resource "aws_security_group" "lambda" {
  name        = "${var.project_name}-lambda-sg"
  description = "Lambda function for Laravel API"
  vpc_id      = var.vpc_id

  egress {
    description = "HTTPS to external OAuth providers via NAT instance"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description = "SMTP submission (STARTTLS) to Gmail for Inquiry mail"
    from_port   = 587
    to_port     = 587
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    description     = "MySQL to RDS"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [var.db_security_group_id]
  }

  # 明示的にegressルールを指定すると暗黙のフルアレグレスが無効化され、
  # VPC内DNSリゾルバ(UDP/TCP 53)への通信も遮断されてしまう。
  # これによりホスト名解決が必要な処理(DB接続含む)がすべてハングし、
  # Lambdaのタイムアウトまで応答が返らない不具合が発生していたため追加する。
  egress {
    description = "DNS resolution to VPC resolver"
    from_port   = 53
    to_port     = 53
    protocol    = "udp"
    cidr_blocks = [var.vpc_cidr_block]
  }

  egress {
    description = "DNS resolution to VPC resolver (TCP fallback)"
    from_port   = 53
    to_port     = 53
    protocol    = "tcp"
    cidr_blocks = [var.vpc_cidr_block]
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

# ------------------------------------------------------------------
# 2/4: IAM
# ------------------------------------------------------------------
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

# ------------------------------------------------------------------
# 3/4: Lambda関数
# ------------------------------------------------------------------
data "archive_file" "placeholder" {
  type        = "zip"
  output_path = "${path.module}/placeholder.zip"

  source {
    content  = "<?php // placeholder for initial terraform apply. Real deploy package is uploaded by CI."
    filename = "index.php"
  }
}

resource "aws_s3_object" "placeholder" {
  bucket = var.deploy_bucket
  key    = var.deploy_object_key
  source = data.archive_file.placeholder.output_path
  etag   = data.archive_file.placeholder.output_md5

  lifecycle {
    ignore_changes = [source, etag] # 実際のデプロイはCIがLambda関数コードを直接更新するため、以降Terraformでは追跡しない
  }
}

resource "aws_lambda_function" "app" {
  function_name = "${var.project_name}-laravel-app"
  role          = aws_iam_role.lambda_exec.arn
  runtime       = "provided.al2023"
  architectures = ["arm64"]
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
      APP_ENV        = "production"
      SESSION_DRIVER = "database"
      CACHE_DRIVER   = "database"
      LOG_CHANNEL    = "stderr"
      BREF_RUNTIME   = "fpm"
    }
  }

  depends_on = [aws_s3_object.placeholder]

  lifecycle {
    ignore_changes = [
      s3_key,      # CIがコード更新するため、Terraform側では追跡しない
      environment, # DB_PASSWORD/APP_KEY等の機密値はCIがGitHub Secretsから設定するため、Terraform側では追跡しない
    ]
  }
}

resource "aws_lambda_function" "artisan" {
  function_name = "${var.project_name}-laravel-artisan"
  role          = aws_iam_role.lambda_exec.arn
  runtime       = "provided.al2023"
  architectures = ["arm64"]
  handler       = "artisan"
  timeout       = 120
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
      APP_ENV        = "production"
      SESSION_DRIVER = "database"
      CACHE_DRIVER   = "database"
      LOG_CHANNEL    = "stderr"
      BREF_RUNTIME   = "console"
    }
  }

  lifecycle {
    ignore_changes = [
      s3_key,      # CIがコード更新するため、Terraform側では追跡しない
      environment, # DB_PASSWORD/APP_KEY等の機密値はCIがGitHub Secretsから設定するため、Terraform側では追跡しない
    ]
  }

  depends_on = [aws_s3_object.placeholder]
}

# ------------------------------------------------------------------
# 4/4: API Gateway
# ------------------------------------------------------------------
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

# ------------------------------------------------------------------
# 5/5: ウォームアップ(EventBridge Schedulerによる5分間隔ping)
# ------------------------------------------------------------------
# API Gateway/CloudFrontを経由せず、Lambda関数を直接invokeする(muuv-e-partsプロジェクトの
# lambda_warmupモジュールと同じ方式)。ペイロードはAPI Gateway v2形式のGET /api/healthを
# 模したものにし、Brefの FpmRuntime が正しくHTTPリクエストとして解釈できるようにする。
# 実測(CloudWatch Logs Insights)により、このLambdaの実行環境は約8〜10.5分でコールドスタート
# する(=5分間隔なら3〜5分の安全マージンがある)ことを確認済み。
# 実行時間帯は毎日5:00〜24:00(JST)のみとする。4:00〜5:00・24:00〜翌1:00は利用者が
# ごくわずかな想定のため対象外とし、1:00〜4:00はRDS停止中(ping自体が無意味)のため元々対象外。
data "aws_iam_policy_document" "warmup_scheduler_assume_role" {
  statement {
    actions = ["sts:AssumeRole"]
    effect  = "Allow"

    principals {
      type        = "Service"
      identifiers = ["scheduler.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "warmup_scheduler" {
  name               = "${var.project_name}-lambda-warmup-scheduler-role"
  assume_role_policy = data.aws_iam_policy_document.warmup_scheduler_assume_role.json
}

data "aws_iam_policy_document" "warmup_scheduler_lambda_invoke" {
  statement {
    sid       = "AllowLambdaInvoke"
    effect    = "Allow"
    actions   = ["lambda:InvokeFunction"]
    resources = [aws_lambda_function.app.arn]
  }
}

resource "aws_iam_role_policy" "warmup_scheduler_lambda_invoke" {
  name   = "lambda-invoke"
  role   = aws_iam_role.warmup_scheduler.id
  policy = data.aws_iam_policy_document.warmup_scheduler_lambda_invoke.json
}

resource "aws_scheduler_schedule" "lambda_warmup" {
  name       = "${var.project_name}-laravel-app-warmup"
  group_name = "default"

  flexible_time_window {
    mode = "OFF"
  }

  # 毎日5:00〜24:00(JST)の間、5分おき。
  schedule_expression          = "cron(0/5 5-23 * * ? *)"
  schedule_expression_timezone = "Asia/Tokyo"

  target {
    arn      = aws_lambda_function.app.arn
    role_arn = aws_iam_role.warmup_scheduler.arn

    input = jsonencode({
      version        = "2.0"
      routeKey       = "$default"
      rawPath        = "/api/health"
      rawQueryString = ""
      headers        = { accept = "application/json" }
      requestContext = {
        http = {
          method   = "GET"
          path     = "/api/health"
          protocol = "HTTP/1.1"
          sourceIp = "127.0.0.1"
        }
      }
      isBase64Encoded = false
    })
  }
}

resource "aws_lambda_permission" "warmup_scheduler" {
  statement_id  = "AllowEventBridgeSchedulerWarmup"
  action        = "lambda:InvokeFunction"
  function_name = aws_lambda_function.app.function_name
  principal     = "scheduler.amazonaws.com"
  source_arn    = aws_scheduler_schedule.lambda_warmup.arn
}

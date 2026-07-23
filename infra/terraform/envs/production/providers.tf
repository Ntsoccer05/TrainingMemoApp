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

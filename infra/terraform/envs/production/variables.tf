variable "project_name" {
  description = "プロジェクト名(リソース命名のプレフィックスに使用)"
  type        = string
  default     = "trainingmemo"
}

variable "vpc_id" {
  description = "既存VPC ID(trainingMemo-vpc)"
  type        = string
  default     = "vpc-04b784aabe610a416"
}

variable "vpc_cidr_block" {
  description = "既存VPCのCIDRブロック(LambdaのSGからVPC内DNSリゾルバへのegress許可に使用)"
  type        = string
  default     = "10.0.0.0/16"
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
  description = "アプリケーションで使用する既存ドメイン名"
  type        = string
  default     = "training-memo.com"
}

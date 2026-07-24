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
  # 10.0.20.0/23 (10.0.20.0-10.0.21.255) だと1c側のprivateサブネット(10.0.22.0/24)が
  # 範囲外になり、そちらのENIから発信された通信がNAT instanceのSGで弾かれ
  # 外部疎通(SMTP/HTTPS問わず全て)がタイムアウトする不具合があったため、
  # 1a(10.0.21.0/24)・1c(10.0.22.0/24)の両方を包含する/22に訂正。
  description = "privateサブネット全体のCIDR(10.0.20.0/22、1a:10.0.21.0/24 + 1c:10.0.22.0/24を包含)"
  type        = string
  default     = "10.0.20.0/22"
}

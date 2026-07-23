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

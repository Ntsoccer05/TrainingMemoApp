output "nat_instance_id" {
  value = aws_instance.nat.id
}

output "nat_security_group_id" {
  value = aws_security_group.nat.id
}

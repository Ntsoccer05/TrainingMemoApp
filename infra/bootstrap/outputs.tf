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

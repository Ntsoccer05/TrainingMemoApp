output "lambda_function_name" {
  value = aws_lambda_function.app.function_name
}

output "artisan_function_name" {
  value = aws_lambda_function.artisan.function_name
}

output "api_endpoint" {
  value = aws_apigatewayv2_api.app.api_endpoint
}

output "api_id" {
  value = aws_apigatewayv2_api.app.id
}

# EC2/RDS 起動停止スケジューラー

`training-memo-EC2-RDS-scheduler-function`(Lambda, Python 3.13)のソースコード。

- Terraform管理外(EC2時代に手動で作成された既存リソース)。CIによる自動デプロイもされていないため、コード変更時は手動で以下を実行してデプロイする。
- 依存パッケージ(`requests`, `pytz`)は Lambda レイヤー `training-memo-layer` から提供されるため、このディレクトリには含めない。
- 呼び出し元は EventBridge Scheduler(コンソールの「スケジューラ」>「スケジュール」、名前は `training-memo-EC2-RDS-scheduler-*`)。

## デプロイ方法

```bash
cd infra/lambda/ec2-rds-scheduler
zip -r /tmp/scheduler-function.zip lambda_function.py
aws lambda update-function-code \
  --function-name training-memo-EC2-RDS-scheduler-function \
  --zip-file fileb:///tmp/scheduler-function.zip \
  --region ap-northeast-1 \
  --profile trainingmemo-mfa
```

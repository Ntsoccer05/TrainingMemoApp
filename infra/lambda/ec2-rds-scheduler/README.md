# EC2/RDS 起動停止スケジューラー

`training-memo-EC2-RDS-scheduler-function`(Lambda, Python 3.13)のソースコード。

- 現在の実装(`lambda_function.py`)は RDS インスタンス(`training-memo`)の起動・停止のみを行う。関数名に "EC2" が残っているのは EC2 時代の命名を引き継いでいるため。
- 動作: 日本時間(JST)基準で毎日 1:00〜4:00 のみ停止し、それ以外の時間帯は起動する(曜日・祝日は問わない)。EventBridge Scheduler から定期的に呼び出され、呼び出しのたびに現在時刻を見て start/stop を判定する。
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

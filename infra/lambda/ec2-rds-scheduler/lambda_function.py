import boto3
import datetime
import pytz

# AWS クライアント
rds = boto3.client('rds')

# RDS の ID
RDS_INSTANCE_ID = "training-memo"

def lambda_handler(event, context):
    """EventBridge からの呼び出しを処理"""
    # 日本のタイムゾーン (JST, UTC+9)
    jst = pytz.timezone('Asia/Tokyo')
    now = datetime.datetime.now(jst)
    current_hour = now.hour
    print(f"Current Hour (Japan Time): {current_hour}")  # 現在時刻をログに出力

    # 曜日・祝日を問わず、毎日 1:00 ～ 4:00 のみ停止。それ以外の時間帯は起動。
    if 1 <= current_hour < 4:
        action = "stop"
    else:
        action = "start"

    try:
        if action == "start":
            rds.start_db_instance(DBInstanceIdentifier=RDS_INSTANCE_ID)
            return {"status": "RDS Started"}
        else:
            rds.stop_db_instance(DBInstanceIdentifier=RDS_INSTANCE_ID)
            return {"status": "RDS Stopped"}
    except Exception as e:
        return {"status": "error", "message": str(e)}

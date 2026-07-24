import boto3
import requests
import datetime
import pytz

# AWS クライアント
rds = boto3.client('rds')

# RDS の ID
RDS_INSTANCE_ID = "training-memo"

# 日本の祝日 API
HOLIDAY_API = "https://holidays-jp.github.io/api/v1/date.json"

def is_holiday():
    """今日が祝日かどうかを判定"""
    today = datetime.datetime.today().strftime("%Y-%m-%d")
    response = requests.get(HOLIDAY_API)
    holidays = response.json()
    return today in holidays

def is_weekend():
    """土日かどうかを判定"""
    today = datetime.datetime.today().weekday()
    return today == 5 or today == 6   # 土曜日(5) or 日曜日(6)ならTrue

def lambda_handler(event, context):
    """EventBridge からの呼び出しを処理"""
    # 日本のタイムゾーン (JST, UTC+9)
    jst = pytz.timezone('Asia/Tokyo')
    now = datetime.datetime.now(jst)
    current_hour = now.hour
    print(f"Current Hour (Japan Time): {current_hour}")  # 現在時刻をログに出力

    holiday = is_holiday()
    weekend = is_weekend()

    # 休日・祝日の 00:00 ～ 07:00 または 21:00 ～ 24:00 は停止
    if holiday or weekend:
        if (0 <= current_hour < 7) or (21 <= current_hour < 24):
            action = "stop"
        else:
            action = "start"

    # 平日の 00:00 ～ 15:00 または 22:00 ～ 24:00 は停止
    else:
        if (0 <= current_hour < 15) or (22 <= current_hour < 24):
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

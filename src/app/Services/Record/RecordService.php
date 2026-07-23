<?php

namespace App\Services\Record;

use App\Models\RecordState;
use Carbon\Carbon;

class RecordService
{
    /**
     * ユーザーの最新のRecordStateを返す。
     * 「作成日時が最新のレコード」と「更新日時が最新のレコード」を比較し、
     * より新しい方を返す(全件取得はしない)。
     *
     * @param int $userId
     * @return RecordState|null
     */
    public function getLatestRecordState(int $userId): ?RecordState
    {
        $latestCreated = RecordState::where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if (is_null($latestCreated)) {
            return null;
        }

        $latestUpdated = RecordState::where('user_id', $userId)
            ->whereNotNull('updated_at')
            ->latest('updated_at')
            ->first();

        // RecordState::UPDATED_AT が null のため updated_at はEloquentのCarbon自動キャスト対象外になっている。
        // 素の文字列のままだと ->gt() が使えないため明示的にCarbon化する。
        if (! is_null($latestUpdated) && Carbon::parse($latestUpdated->updated_at)->gt($latestCreated->created_at)) {
            return $latestUpdated;
        }

        return $latestCreated;
    }
}

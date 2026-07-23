<?php

namespace App\Services\RecordContent;

use App\Models\RecordState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RecordContentService
{
    /**
     * ホーム画面のカレンダー表示用に、指定期間内のRecordStateを
     * 関連するrecordMenus/menu/categoryごとEager Loadして取得する。
     *
     * @param int $userId
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection<int, RecordState>
     */
    public function getRecordsInRange(int $userId, Carbon $from, Carbon $to): Collection
    {
        return RecordState::where('user_id', $userId)
            ->whereBetween('recorded_at', [$from, $to])
            ->with(['recordMenus.menu', 'recordMenus.category'])
            ->get();
    }
}

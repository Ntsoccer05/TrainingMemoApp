<?php

namespace App\Services\RecordContent;

use App\Models\RecordMenu;
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

    /**
     * 種目の履歴確認画面向けに、指定日より前の直近5件のRecordMenuを
     * recordState/recordContentsごとEager Loadして取得する。
     *
     * @param int $userId
     * @param int $categoryId
     * @param int $menuId
     * @param string $recordedAt
     * @return Collection<int, RecordMenu>
     */
    public function getMenuHistory(int $userId, int $categoryId, int $menuId, string $recordedAt): Collection
    {
        return RecordMenu::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('menu_id', $menuId)
            ->where('recorded_at', '<', $recordedAt)
            ->orderBy('recorded_at', 'desc')
            ->take(5)
            ->get()
            ->load([
                'recordState:id,bodyWeight',
                'recordContents' => fn ($query) => $query->orderBy('set', 'asc'),
            ]);
    }

    /**
     * 種目記録画面向けに、今回の記録内容と前回の記録内容をまとめて取得する。
     * $recordedAt が指定されている場合のみ前回分を検索する。
     * 今回分のRecordMenuが既に存在する場合は自分自身を1件スキップして前回分を探す。
     *
     * @param int $userId
     * @param int $categoryId
     * @param int $menuId
     * @param int $recordStateId
     * @param string|null $recordedAt
     * @return array{tgtRecords: ?Collection, previousRecordState: ?\App\Models\RecordState, previousRecords: ?Collection}
     */
    public function getCurrentAndPreviousRecord(
        int $userId,
        int $categoryId,
        int $menuId,
        int $recordStateId,
        ?string $recordedAt
    ): array {
        $tgtRecordMenu = RecordMenu::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('menu_id', $menuId)
            ->where('record_state_id', $recordStateId)
            ->first();

        $tgtRecords = $tgtRecordMenu
            ? $tgtRecordMenu->recordContents()->orderBy('set', 'asc')->get()
            : null;

        $previousRecordMenu = null;
        $previousRecords = null;

        if ($recordedAt) {
            $query = RecordMenu::where('user_id', $userId)
                ->where('category_id', $categoryId)
                ->where('menu_id', $menuId)
                ->whereDate('recorded_at', $tgtRecordMenu ? '<=' : '<', $recordedAt)
                ->orderBy('recorded_at', 'desc');

            if ($tgtRecordMenu) {
                $query->where('id', '!=', $tgtRecordMenu->id);
            }

            $previousRecordMenu = $query->first();

            if ($previousRecordMenu) {
                $previousRecordMenu->load('recordState');
                $previousRecords = $previousRecordMenu->recordContents()->orderBy('set', 'asc')->get();
            }
        }

        return [
            'tgtRecords' => $tgtRecords,
            'previousRecordState' => $previousRecordMenu?->recordState,
            'previousRecords' => $previousRecords,
        ];
    }
}

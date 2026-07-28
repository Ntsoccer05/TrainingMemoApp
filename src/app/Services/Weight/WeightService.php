<?php

namespace App\Services\Weight;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class WeightService
{
    /**
     * 指定日の体重・メモ・タグを記録する。既にその日のRecordStateがあれば更新する。
     *
     * @param int $userId
     * @param string $recordedAt Y-m-d形式
     * @param float|null $bodyWeight
     * @param string|null $memo
     * @param array<int> $tagIds
     * @return RecordState
     */
    public function recordWeight(int $userId, string $recordedAt, ?float $bodyWeight, ?string $memo, array $tagIds = []): RecordState
    {
        $recordState = RecordState::firstOrNew([
            'user_id' => $userId,
            'recorded_at' => $recordedAt,
        ]);
        $isExisting = $recordState->exists;

        $recordState->bodyWeight = $bodyWeight;
        $recordState->weight_memo = $memo;
        if ($isExisting) {
            $recordState->updated_at = Carbon::now();
        }
        $recordState->save();

        $recordState->weightTags()->sync($tagIds);

        return $recordState;
    }

    /**
     * 指定期間内の、体重が記録されているRecordStateを日付昇順で返す。
     *
     * @param int $userId
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection<int, RecordState>
     */
    public function getWeightHistory(int $userId, Carbon $from, Carbon $to): Collection
    {
        return RecordState::where('user_id', $userId)
            ->whereBetween('recorded_at', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('bodyWeight')
            ->with('weightTags')
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * タグ別に「そのタグが付いた日の翌日」の体重変動平均を計算する。
     * 翌日の体重記録が存在しないタグはスキップする。
     *
     * @param int $userId
     * @return array<int, array{tag: string, average_diff: float, sample_count: int}>
     */
    public function getTagStatistics(int $userId): array
    {
        $tags = WeightTag::where('user_id', $userId)->get();

        if ($tags->isEmpty()) {
            return [];
        }

        // 体重記録とタグの紐付けを1回のクエリでまとめて取得する(タグ×記録件数ぶんの個別クエリを避ける)。
        $records = RecordState::where('user_id', $userId)
            ->whereNotNull('bodyWeight')
            ->with('weightTags:id')
            ->get(['id', 'recorded_at', 'bodyWeight']);

        // 「翌日の体重」をクエリなしで引けるように、日付をキーにしたマップを作る。
        $weightByDate = $records->mapWithKeys(function ($record) {
            return [Carbon::parse($record->recorded_at)->toDateString() => $record->bodyWeight];
        });

        $stats = [];
        foreach ($tags as $tag) {
            $diffs = [];
            foreach ($records as $record) {
                if (! $record->weightTags->contains('id', $tag->id)) {
                    continue;
                }

                $nextDay = Carbon::parse($record->recorded_at)->addDay()->toDateString();
                if ($weightByDate->has($nextDay)) {
                    $diffs[] = $weightByDate->get($nextDay) - $record->bodyWeight;
                }
            }

            if (count($diffs) > 0) {
                $stats[] = [
                    'tag' => $tag->content,
                    'average_diff' => round(array_sum($diffs) / count($diffs), 2),
                    'sample_count' => count($diffs),
                ];
            }
        }

        return $stats;
    }

    /**
     * ユーザーの目標体重と期限日を更新する。
     *
     * @param int $userId
     * @param float $targetWeight
     * @param string|null $targetWeightDate
     * @return User
     */
    public function updateTargetWeight(int $userId, float $targetWeight, ?string $targetWeightDate = null): User
    {
        $user = User::findOrFail($userId);
        $user->target_weight = $targetWeight;
        $user->target_weight_date = $targetWeightDate;
        $user->save();

        return $user;
    }

    /**
     * ユーザー自身の体重タグをすべて返す。
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection<int, WeightTag>
     */
    public function getAllTags(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return WeightTag::where('user_id', $userId)->get();
    }

    /**
     * ユーザーの体重タグを追加する。既に5個保持している場合はnullを返す。
     *
     * @param int $userId
     * @param string $content
     * @return WeightTag|null
     */
    public function addTag(int $userId, string $content): ?WeightTag
    {
        if (WeightTag::where('user_id', $userId)->count() >= 5) {
            return null;
        }

        return WeightTag::create(['user_id' => $userId, 'content' => $content]);
    }

    /**
     * ユーザー自身の体重タグを削除する。
     *
     * @param int $userId
     * @param int $tagId
     * @return void
     */
    public function deleteTag(int $userId, int $tagId): void
    {
        $tag = WeightTag::where('user_id', $userId)->findOrFail($tagId);
        $tag->delete();
    }
}

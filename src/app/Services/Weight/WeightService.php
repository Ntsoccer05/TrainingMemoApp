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
        $stats = [];

        foreach ($tags as $tag) {
            $records = RecordState::where('record_states.user_id', $userId)
                ->whereNotNull('bodyWeight')
                ->whereHas('weightTags', function ($query) use ($tag) {
                    $query->where('weight_tags.id', $tag->id);
                })
                ->get(['id', 'recorded_at', 'bodyWeight']);

            $diffs = [];
            foreach ($records as $record) {
                $nextDay = Carbon::parse($record->recorded_at)->addDay()->toDateString();
                $nextRecord = RecordState::where('user_id', $userId)
                    ->whereDate('recorded_at', $nextDay)
                    ->whereNotNull('bodyWeight')
                    ->first();

                if ($nextRecord) {
                    $diffs[] = $nextRecord->bodyWeight - $record->bodyWeight;
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
     * ユーザーの目標体重を更新する。
     *
     * @param int $userId
     * @param float $targetWeight
     * @return User
     */
    public function updateTargetWeight(int $userId, float $targetWeight): User
    {
        $user = User::findOrFail($userId);
        $user->target_weight = $targetWeight;
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

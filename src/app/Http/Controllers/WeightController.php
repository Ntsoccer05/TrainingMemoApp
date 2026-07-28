<?php

namespace App\Http\Controllers;

use App\Http\Requests\Weight\GetWeightDashboardRequest;
use App\Http\Requests\Weight\StoreWeightRecordRequest;
use App\Http\Requests\Weight\StoreWeightTagRequest;
use App\Http\Requests\Weight\UpdateTargetWeightRequest;
use App\Services\Weight\WeightService;

class WeightController extends Controller
{
    public function store(StoreWeightRecordRequest $request, WeightService $weightService)
    {
        $recordState = $weightService->recordWeight(
            auth()->id(),
            $request->input('recorded_at'),
            $request->input('body_weight'),
            $request->input('memo'),
            $request->input('tag_ids', [])
        );

        return response()->json([
            'status_code' => 200,
            'message' => '体重記録を保存しました。',
            'record' => $recordState->load('weightTags'),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboard(GetWeightDashboardRequest $request, WeightService $weightService)
    {
        $data = $weightService->getDashboardData(
            auth()->id(),
            $request->resolvedFrom(),
            $request->resolvedTo(),
            $request->resolvedSelectedDate()
        );

        return response()->json([
            'status_code' => 200,
            'records' => $data['records'],
            'target_weight' => $data['target_weight'],
            'target_weight_date' => $data['target_weight_date'],
            'tags' => $data['tags'],
            'tag_stats' => $data['tag_stats'],
            'selected_date_record' => $data['selected_date_record'],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function storeTag(StoreWeightTagRequest $request, WeightService $weightService)
    {
        $tag = $weightService->addTag(auth()->id(), $request->input('content'));

        if ($tag === null) {
            return response()->json([
                'status_code' => 422,
                'message' => 'タグは5個までです。',
            ], 422);
        }

        return response()->json([
            'status_code' => 200,
            'tag' => $tag,
        ]);
    }

    public function destroyTag(int $id, WeightService $weightService)
    {
        $weightService->deleteTag(auth()->id(), $id);

        return response()->json([
            'status_code' => 200,
            'message' => 'タグを削除しました。',
        ]);
    }

    public function updateTargetWeight(UpdateTargetWeightRequest $request, WeightService $weightService)
    {
        $user = $weightService->updateTargetWeight(
            auth()->id(),
            $request->input('target_weight'),
            $request->input('target_weight_date')
        );

        return response()->json([
            'status_code' => 200,
            'target_weight' => $user->target_weight,
            'target_weight_date' => $user->target_weight_date?->format('Y-m-d'),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}

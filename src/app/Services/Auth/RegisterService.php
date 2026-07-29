<?php

namespace App\Services\Auth;

use App\Models\Category;
use App\Models\WeightTag;
use Illuminate\Support\Facades\DB;

class RegisterService
{
    private const DEFAULT_CATEGORIES = ['胸', '背中', '足', '腕', '腹筋'];

    private const DEFAULT_WEIGHT_TAGS = ['食べすぎ', '飲みすぎ', '体調不良', '運動'];

    /**
     * 新規ユーザーにデフォルトのカテゴリー・体重タグを作成する。メニューは作成しない。
     *
     * @param int $userId
     * @return void
     */
    public function setupDefaultData(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            foreach (self::DEFAULT_CATEGORIES as $content) {
                Category::create(['user_id' => $userId, 'content' => $content]);
            }

            foreach (self::DEFAULT_WEIGHT_TAGS as $content) {
                WeightTag::create(['user_id' => $userId, 'content' => $content]);
            }
        });
    }
}

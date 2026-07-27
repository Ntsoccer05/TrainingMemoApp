<?php

namespace Tests\Feature\Services\Weight;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use App\Services\Weight\WeightService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_weight_creates_new_record_state_when_none_exists(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', 65.5, '飲み会だった', []);

        $this->assertDatabaseHas('record_states', [
            'id' => $result->id,
            'user_id' => $user->id,
            'recorded_at' => '2026-07-27',
            'bodyWeight' => 65.5,
            'weight_memo' => '飲み会だった',
        ]);
    }

    public function test_record_weight_updates_existing_record_state_for_same_day(): void
    {
        $user = User::factory()->create();
        $existing = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-27']);
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', 66.0, '更新後メモ', []);

        $this->assertSame($existing->id, $result->id);
        $this->assertDatabaseHas('record_states', [
            'id' => $existing->id,
            'bodyWeight' => 66.0,
            'weight_memo' => '更新後メモ',
        ]);
    }

    public function test_record_weight_syncs_tags(): void
    {
        $user = User::factory()->create();
        $tagA = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $tagB = WeightTag::create(['user_id' => $user->id, 'content' => '生理']);
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', 65.5, null, [$tagA->id, $tagB->id]);

        $this->assertCount(2, $result->fresh()->weightTags);
    }

    public function test_record_weight_allows_null_body_weight_for_memo_only_record(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', null, 'メモだけ残す', []);

        $this->assertDatabaseHas('record_states', [
            'id' => $result->id,
            'bodyWeight' => null,
            'weight_memo' => 'メモだけ残す',
        ]);
    }

    public function test_get_weight_history_returns_records_with_body_weight_in_range(): void
    {
        $user = User::factory()->create();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-01', 'bodyWeight' => 64.0]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-01', 'bodyWeight' => 65.0]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-08-01', 'bodyWeight' => 66.0]);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-07-15')
        );

        $this->assertCount(1, $result);
        $this->assertEquals(65.0, $result->first()->bodyWeight);
    }

    public function test_get_weight_history_excludes_records_without_body_weight(): void
    {
        $user = User::factory()->create();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10']);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31')
        );

        $this->assertCount(0, $result);
    }

    public function test_get_weight_history_ignores_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 70.0]);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31')
        );

        $this->assertCount(0, $result);
    }

    public function test_get_weight_history_eager_loads_weight_tags(): void
    {
        $user = User::factory()->create();
        $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $recordState->weightTags()->sync([$tag->id]);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31')
        );

        $this->assertTrue($result->first()->relationLoaded('weightTags'));
        $this->assertCount(1, $result->first()->weightTags);
    }

    public function test_get_tag_statistics_calculates_average_diff_for_next_day(): void
    {
        $user = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);

        $day1 = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day1->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.6]);

        $day2 = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-20', 'bodyWeight' => 64.0]);
        $day2->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-21', 'bodyWeight' => 64.4]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $matched = collect($stats)->firstWhere('tag', '飲みすぎ');
        $this->assertNotNull($matched);
        $this->assertEquals(0.5, $matched['average_diff']);
        $this->assertEquals(2, $matched['sample_count']);
    }

    public function test_get_tag_statistics_skips_tags_with_no_next_day_record(): void
    {
        $user = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '運動']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $matched = collect($stats)->firstWhere('tag', '運動');
        $this->assertNull($matched);
    }

    public function test_get_tag_statistics_ignores_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '生理']);
        $day = RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.5]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $this->assertEmpty($stats);
    }

    public function test_update_target_weight_sets_users_target_weight(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->updateTargetWeight($user->id, 60.0);

        $this->assertEquals(60.0, $result->target_weight);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight' => 60.0]);
    }

    public function test_get_all_tags_returns_only_the_users_own_tags(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        WeightTag::create(['user_id' => $user->id, 'content' => '運動']);
        WeightTag::create(['user_id' => $otherUser->id, 'content' => '生理']);
        $service = new WeightService();

        $result = $service->getAllTags($user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('運動', $result->first()->content);
    }

    public function test_get_tag_statistics_only_considers_the_users_own_tags(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        // ユーザー自身は「飲みすぎ」というタグを持っていない(他ユーザーが同名タグを持つのみ)
        $otherUsersTag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.5]);
        // 他ユーザー所有のタグを誤ってこのユーザーの記録に紐付けても(データ不整合を想定した防御的なテスト)、
        // getTagStatisticsはuser_idのタグ一覧のみを走査するため、統計には出てこない
        $day->weightTags()->sync([$otherUsersTag->id]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $this->assertEmpty($stats);
    }

    public function test_add_tag_creates_a_new_tag_for_the_user(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->addTag($user->id, 'サウナ');

        $this->assertNotNull($result);
        $this->assertEquals('サウナ', $result->content);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => 'サウナ']);
    }

    public function test_add_tag_returns_null_when_user_already_has_five_tags(): void
    {
        $user = User::factory()->create();
        foreach (['タグ1', 'タグ2', 'タグ3', 'タグ4', 'タグ5'] as $content) {
            WeightTag::create(['user_id' => $user->id, 'content' => $content]);
        }
        $service = new WeightService();

        $result = $service->addTag($user->id, 'タグ6');

        $this->assertNull($result);
        $this->assertDatabaseMissing('weight_tags', ['user_id' => $user->id, 'content' => 'タグ6']);
    }

    public function test_add_tag_does_not_count_other_users_tags_toward_the_limit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        foreach (['タグ1', 'タグ2', 'タグ3', 'タグ4', 'タグ5'] as $content) {
            WeightTag::create(['user_id' => $otherUser->id, 'content' => $content]);
        }
        $service = new WeightService();

        $result = $service->addTag($user->id, '運動');

        $this->assertNotNull($result);
    }

    public function test_delete_tag_removes_the_users_own_tag(): void
    {
        $user = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $service = new WeightService();

        $service->deleteTag($user->id, $tag->id);

        $this->assertDatabaseMissing('weight_tags', ['id' => $tag->id]);
    }

    public function test_delete_tag_throws_when_tag_belongs_to_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);
        $service = new WeightService();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->deleteTag($user->id, $tag->id);
    }
}

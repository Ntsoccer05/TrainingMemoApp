<?php

namespace Tests\Feature;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    public function test_store_creates_weight_record(): void
    {
        $user = $this->actingAsUser();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);

        $response = $this->postJson('/api/weight', [
            'recorded_at' => '2026-07-27',
            'body_weight' => 65.5,
            'memo' => '飲み会だった',
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(200)->assertJson(['status_code' => 200]);
        $this->assertDatabaseHas('record_states', [
            'user_id' => $user->id,
            'recorded_at' => '2026-07-27',
            'bodyWeight' => 65.5,
        ]);
    }

    public function test_store_rejects_nonexistent_tag_id(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/weight', [
            'recorded_at' => '2026-07-27',
            'body_weight' => 65.5,
            'tag_ids' => [999999],
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_another_users_tag_id(): void
    {
        $this->actingAsUser();
        $otherUser = User::factory()->create();
        $otherUsersTag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);

        $response = $this->postJson('/api/weight', [
            'recorded_at' => '2026-07-27',
            'body_weight' => 65.5,
            'tag_ids' => [$otherUsersTag->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_dashboard_returns_records_target_weight_tags_and_stats(): void
    {
        $user = $this->actingAsUser();
        $user->target_weight = 60.0;
        $user->target_weight_date = '2026-12-31';
        $user->save();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.5]);

        $response = $this->getJson('/api/weight/dashboard?from=2026-07-01&to=2026-07-31&selected_date=2026-07-10');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('target_weight', 60.0)
            ->assertJsonPath('target_weight_date', '2026-12-31')
            ->assertJsonCount(2, 'records')
            ->assertJsonCount(1, 'tags')
            ->assertJsonCount(1, 'tag_stats')
            ->assertJsonPath('selected_date_record.recorded_at', '2026-07-10');
    }

    public function test_dashboard_returns_null_selected_date_record_when_not_recorded(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/weight/dashboard?from=2026-07-01&to=2026-07-31&selected_date=2026-07-15');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'records')
            ->assertJsonPath('selected_date_record', null);
    }

    public function test_dashboard_returns_empty_tags_and_stats_when_none_exist(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/weight/dashboard');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'tags')
            ->assertJsonCount(0, 'tag_stats');
    }

    public function test_update_target_weight(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/weight/targetWeight', ['target_weight' => 58.0]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight' => 58.0]);
    }

    public function test_update_target_weight_saves_target_weight_date(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/weight/targetWeight', [
            'target_weight' => 58.0,
            'target_weight_date' => '2026-12-31',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('target_weight_date', '2026-12-31');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight_date' => '2026-12-31']);
    }

    public function test_update_target_weight_allows_omitting_target_weight_date(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/weight/targetWeight', ['target_weight' => 58.0]);

        $response->assertStatus(200)
            ->assertJsonPath('target_weight_date', null);
    }

    public function test_store_tag_creates_a_new_tag(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/weight/tags', ['content' => 'サウナ']);

        $response->assertStatus(200)->assertJsonPath('tag.content', 'サウナ');
    }

    public function test_store_tag_returns_422_when_limit_exceeded(): void
    {
        $user = $this->actingAsUser();
        foreach (['タグ1', 'タグ2', 'タグ3', 'タグ4', 'タグ5'] as $content) {
            WeightTag::create(['user_id' => $user->id, 'content' => $content]);
        }

        $response = $this->postJson('/api/weight/tags', ['content' => 'タグ6']);

        $response->assertStatus(422);
    }

    public function test_destroy_tag_removes_the_tag(): void
    {
        $user = $this->actingAsUser();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);

        $response = $this->deleteJson("/api/weight/tags/{$tag->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('weight_tags', ['id' => $tag->id]);
    }

    public function test_destroy_tag_returns_404_for_another_users_tag(): void
    {
        $this->actingAsUser();
        $otherUser = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);

        $response = $this->deleteJson("/api/weight/tags/{$tag->id}");

        $response->assertStatus(404);
    }
}

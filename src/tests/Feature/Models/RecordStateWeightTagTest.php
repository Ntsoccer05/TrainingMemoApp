<?php

namespace Tests\Feature\Models;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordStateWeightTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_state_can_be_synced_with_multiple_weight_tags(): void
    {
        $user = User::factory()->create();
        $recordState = RecordState::create([
            'user_id' => $user->id,
            'recorded_at' => '2026-07-27',
            'bodyWeight' => 65.5,
            'weight_memo' => '飲み会だった',
        ]);
        $tagA = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $tagB = WeightTag::create(['user_id' => $user->id, 'content' => '体調不良']);

        $recordState->weightTags()->sync([$tagA->id, $tagB->id]);

        $this->assertCount(2, $recordState->fresh()->weightTags);
        $this->assertEquals('飲み会だった', $recordState->fresh()->weight_memo);
    }
}

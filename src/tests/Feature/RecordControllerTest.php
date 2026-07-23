<?php

namespace Tests\Feature;

use App\Models\RecordState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_latest_record_for_authenticated_user_only()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherRecord = RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => now()->toDateString()]);
        RecordState::where('id', $otherRecord->id)->update(['created_at' => now()]);

        $ownRecord = RecordState::create(['user_id' => $user->id, 'recorded_at' => now()->subDay()->toDateString()]);
        RecordState::where('id', $ownRecord->id)->update(['created_at' => now()->subDays(3)]);

        $response = $this->actingAs($user)->getJson('/api/record');

        $response->assertStatus(200);
        $response->assertJsonPath('latestRecord.id', $ownRecord->id);
    }

    public function test_response_does_not_contain_unused_fields()
    {
        $user = User::factory()->create();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => now()->toDateString()]);

        $response = $this->actingAs($user)->getJson('/api/record');

        $response->assertJsonMissingPath('isSetUpdated');
        $response->assertJsonMissingPath('updatedDateTime');
        $response->assertJsonMissingPath('createdDateTime');
        $response->assertJsonMissingPath('latestUpdated');
        $response->assertJsonMissingPath('latestCreated');
    }
}

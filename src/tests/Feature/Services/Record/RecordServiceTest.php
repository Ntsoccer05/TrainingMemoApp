<?php

namespace Tests\Feature\Services\Record;

use App\Models\RecordState;
use App\Models\User;
use App\Services\Record\RecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_user_has_no_records()
    {
        $user = User::factory()->create();
        $service = new RecordService();

        $result = $service->getLatestRecordState($user->id);

        $this->assertNull($result);
    }

    public function test_returns_latest_created_record_when_none_updated()
    {
        $user = User::factory()->create();

        $older = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-01']);
        RecordState::where('id', $older->id)->update(['created_at' => now()->subDays(2)]);

        $newer = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-02']);
        RecordState::where('id', $newer->id)->update(['created_at' => now()->subDay()]);

        $service = new RecordService();

        $result = $service->getLatestRecordState($user->id);

        $this->assertSame($newer->id, $result->id);
    }

    public function test_returns_updated_record_when_its_updated_at_is_newer_than_latest_created_at()
    {
        $user = User::factory()->create();

        $created = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-01']);
        RecordState::where('id', $created->id)->update(['created_at' => now()->subDays(5)]);

        $updated = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-20']);
        RecordState::where('id', $updated->id)->update([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDay(),
        ]);

        $service = new RecordService();

        $result = $service->getLatestRecordState($user->id);

        $this->assertSame($updated->id, $result->id);
    }

    public function test_ignores_other_users_records()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => now()->toDateString()]);

        $service = new RecordService();

        $result = $service->getLatestRecordState($user->id);

        $this->assertNull($result);
    }

    public function test_does_not_leak_other_users_more_recently_updated_record()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownRecord = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-01']);
        RecordState::where('id', $ownRecord->id)->update(['created_at' => now()->subDays(3)]);

        $otherRecord = RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-10']);
        RecordState::where('id', $otherRecord->id)->update([
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $service = new RecordService();

        $result = $service->getLatestRecordState($user->id);

        $this->assertSame($ownRecord->id, $result->id);
    }
}

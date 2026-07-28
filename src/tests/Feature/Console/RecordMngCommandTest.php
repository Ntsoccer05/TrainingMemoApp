<?php

namespace Tests\Feature\Console;

use App\Models\RecordState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordMngCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_deletes_record_states_older_than_two_years()
    {
        $user = User::factory()->create();

        // idが先になるよう、2年以上前に作成された古い記録を先に作る
        $oldRecord = RecordState::create(['user_id' => $user->id, 'recorded_at' => now()->subYears(3)->toDateString()]);
        RecordState::where('id', $oldRecord->id)->update(['created_at' => now()->subMonths(25), 'updated_at' => null]);

        // 直近の記録は削除されてはいけない
        $recentRecord = RecordState::create(['user_id' => $user->id, 'recorded_at' => now()->toDateString()]);
        RecordState::where('id', $recentRecord->id)->update(['created_at' => now()->subMonth(), 'updated_at' => null]);

        $this->artisan('command:deleteRecords')->assertExitCode(0);

        $this->assertDatabaseMissing('record_states', ['id' => $oldRecord->id]);
        $this->assertDatabaseHas('record_states', ['id' => $recentRecord->id]);
    }
}

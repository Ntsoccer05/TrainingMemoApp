<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Menu;
use App\Models\RecordMenu;
use App\Models\RecordState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordRankingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_ranking_without_crashing()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'content' => 'ベンチプレス',
            'oneSide' => false,
        ]);
        $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => now()->toDateString()]);
        // recorded_at はマスアサイン対象(fillable)外のため、直接プロパティに設定する
        $recordMenu = new RecordMenu([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'menu_id' => $menu->id,
            'record_state_id' => $recordState->id,
        ]);
        $recordMenu->recorded_at = now()->toDateString();
        $recordMenu->save();

        $this->artisan('command:updateRanking')->assertExitCode(0);

        $this->assertDatabaseCount('ranking_records', 1);
    }
}

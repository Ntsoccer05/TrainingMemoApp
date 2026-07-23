<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\RecordMenu;
use App\Models\RecordState;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordContentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_branch_excludes_records_outside_default_three_month_range()
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 23));
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $inRange = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-01']);
        RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $inRange->id, 'recorded_at' => '2026-06-01']);

        $outOfRange = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-01-01']);
        RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $outOfRange->id, 'recorded_at' => '2026-01-01']);

        $response = $this->actingAs($user)->getJson("/api/recordContent?user_id={$user->id}");

        $response->assertStatus(200);
        $recordedDates = collect($response->json('records'))->pluck('recorded_at.recorded_at')->all();
        $this->assertContains('2026-06-01', $recordedDates);
        $this->assertNotContains('2026-01-01', $recordedDates);

        Carbon::setTestNow();
    }

    public function test_home_branch_respects_explicit_from_to_range()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $target = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2025-03-15']);
        RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $target->id, 'recorded_at' => '2025-03-15']);

        $response = $this->actingAs($user)->getJson(
            "/api/recordContent?user_id={$user->id}&from=2025-03-01&to=2025-03-31"
        );

        $response->assertStatus(200);
        $recordedDates = collect($response->json('records'))->pluck('recorded_at.recorded_at')->all();
        $this->assertContains('2025-03-15', $recordedDates);
    }

    public function test_home_branch_rejects_from_without_to()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/recordContent?user_id={$user->id}&from=2025-03-01");

        $response->assertStatus(422);
    }

    public function test_marking_branch_returns_200_when_no_record_exists_for_the_date()
    {
        $user = User::factory()->create();

        // その日のRecordStateが存在しない状態(直接URLでアクセスした場合などを想定)
        $response = $this->actingAs($user)->getJson(
            "/api/recordContent?user_id={$user->id}&recorded_at=2026-7-23"
        );

        $response->assertStatus(200);
        $records = $response->json('records');
        $this->assertCount(1, $records);
        $this->assertNull($records[0]['recorded_at']['record_id']);
        $this->assertSame('2026-07-23', $records[0]['recorded_at']['recorded_at']);
        $this->assertArrayNotHasKey('menu', $records[0]);
        $this->assertArrayNotHasKey('category', $records[0]);
    }

    public function test_marking_branch_returns_menu_data_when_record_and_menu_exist()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-23']);
        RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $recordState->id, 'recorded_at' => '2026-07-23']);

        $response = $this->actingAs($user)->getJson(
            "/api/recordContent?user_id={$user->id}&recorded_at=2026-7-23"
        );

        $response->assertStatus(200);
        $records = $response->json('records');
        $this->assertCount(1, $records);
        $this->assertSame($recordState->id, $records[0]['recorded_at']['record_id']);
        $this->assertSame($menu->id, $records[0]['menu'][0]['menu_id']);
        $this->assertSame($category->id, $records[0]['category'][0]['category_id']);
    }
}

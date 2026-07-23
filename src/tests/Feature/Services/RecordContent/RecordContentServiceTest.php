<?php

namespace Tests\Feature\Services\RecordContent;

use App\Models\Category;
use App\Models\Menu;
use App\Models\RecordMenu;
use App\Models\RecordState;
use App\Models\User;
use App\Services\RecordContent\RecordContentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordContentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_excludes_records_outside_the_given_range()
    {
        $user = User::factory()->create();
        $inRange = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-15']);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-01-01']);

        $service = new RecordContentService();
        $result = $service->getRecordsInRange($user->id, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertCount(1, $result);
        $this->assertSame($inRange->id, $result->first()->id);
    }

    public function test_includes_boundary_dates()
    {
        $user = User::factory()->create();
        $onFrom = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-01']);
        $onTo = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-30']);

        $service = new RecordContentService();
        $result = $service->getRecordsInRange($user->id, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30')->endOfDay());

        $ids = $result->pluck('id')->all();
        $this->assertContains($onFrom->id, $ids);
        $this->assertContains($onTo->id, $ids);
    }

    public function test_ignores_other_users_records()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-06-15']);

        $service = new RecordContentService();
        $result = $service->getRecordsInRange($user->id, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        $this->assertCount(0, $result);
    }

    public function test_eager_loads_record_menus_with_menu_and_category()
    {
        $user = User::factory()->create();
        $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-15']);
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);
        // recorded_at はマスアサイン対象(fillable)外のため、直接プロパティに設定する
        $recordMenu = new RecordMenu([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'menu_id' => $menu->id,
            'record_state_id' => $recordState->id,
        ]);
        $recordMenu->recorded_at = '2026-06-15';
        $recordMenu->save();

        $service = new RecordContentService();
        $result = $service->getRecordsInRange($user->id, Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));

        DB::enableQueryLog();
        $loadedRecordMenu = $result->first()->recordMenus->first();
        $menuId = $loadedRecordMenu->menu->id;
        $categoryId = $loadedRecordMenu->category->id;
        $queriesAfterAccess = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($menu->id, $menuId);
        $this->assertSame($category->id, $categoryId);
        $this->assertSame(0, $queriesAfterAccess, 'Accessing eager-loaded relations should not trigger additional queries (N+1).');
    }
}

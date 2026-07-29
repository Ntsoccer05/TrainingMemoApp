<?php

namespace Tests\Feature\Services\RecordContent;

use App\Models\Category;
use App\Models\Menu;
use App\Models\RecordContent;
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

    public function test_get_menu_history_returns_up_to_five_records_before_the_given_date_in_descending_order()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        // 対象日より前の記録を6件作成(5件までしか返らないことを確認するため)
        foreach (range(1, 6) as $i) {
            $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => "2026-06-0{$i}"]);
            $recordMenu = new RecordMenu([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'menu_id' => $menu->id,
                'record_state_id' => $recordState->id,
            ]);
            $recordMenu->recorded_at = "2026-06-0{$i}";
            $recordMenu->save();
        }
        // 対象日以降の記録は含まれないことを確認するため
        $afterRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);
        $afterRecordMenu = new RecordMenu([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'menu_id' => $menu->id,
            'record_state_id' => $afterRecordState->id,
        ]);
        $afterRecordMenu->recorded_at = '2026-06-10';
        $afterRecordMenu->save();

        $service = new RecordContentService();
        $result = $service->getMenuHistory($user->id, $category->id, $menu->id, '2026-06-10');

        $this->assertCount(5, $result);
        $dates = $result->pluck('recorded_at')->map(fn ($d) => (string) $d)->all();
        $this->assertSame($dates, collect($dates)->sortByDesc(fn ($d) => $d)->values()->all());
        $this->assertNotContains('2026-06-10', $dates);
    }

    public function test_get_menu_history_eager_loads_record_contents_without_n_plus_one()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        foreach (range(1, 3) as $i) {
            $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => "2026-06-0{$i}", 'bodyWeight' => 70]);
            $recordMenu = new RecordMenu([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'menu_id' => $menu->id,
                'record_state_id' => $recordState->id,
            ]);
            $recordMenu->recorded_at = "2026-06-0{$i}";
            $recordMenu->save();
            $recordContent = new RecordContent([
                'record_menu_id' => $recordMenu->id,
                'weight' => 60,
                'set' => 1,
                'rep' => 10,
            ]);
            $recordContent->user_id = $user->id;
            $recordContent->save();
        }

        $service = new RecordContentService();
        $result = $service->getMenuHistory($user->id, $category->id, $menu->id, '2026-07-01');

        DB::enableQueryLog();
        foreach ($result as $historyTgtMenu) {
            $historyTgtMenu->recordContents->count();
            $historyTgtMenu->recordState->bodyWeight;
        }
        $queriesAfterAccess = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queriesAfterAccess, 'Accessing recordContents/recordState per history item should not trigger additional queries (N+1).');
    }

    public function test_get_current_and_previous_record_skips_todays_own_row_when_it_exists()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $previousRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-08', 'bodyWeight' => 68]);
        $previousRecordMenu = new RecordMenu(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $previousRecordState->id]);
        $previousRecordMenu->recorded_at = '2026-06-08';
        $previousRecordMenu->save();
        $previousContent = new RecordContent(['record_menu_id' => $previousRecordMenu->id, 'weight' => 55, 'rep' => 10, 'set' => 1]);
        $previousContent->user_id = $user->id;
        $previousContent->save();

        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);
        $todayRecordMenu = new RecordMenu(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $todayRecordState->id]);
        $todayRecordMenu->recorded_at = '2026-06-10';
        $todayRecordMenu->save();
        $todayContent = new RecordContent(['record_menu_id' => $todayRecordMenu->id, 'weight' => 60, 'rep' => 10, 'set' => 1]);
        $todayContent->user_id = $user->id;
        $todayContent->save();

        $service = new RecordContentService();
        $result = $service->getCurrentAndPreviousRecord($user->id, $category->id, $menu->id, $todayRecordState->id, '2026-06-10');

        $this->assertCount(1, $result['tgtRecords']);
        $this->assertEquals(60, $result['tgtRecords']->first()->weight);
        $this->assertSame($previousRecordState->id, $result['previousRecordState']->id);
        $this->assertCount(1, $result['previousRecords']);
        $this->assertEquals(55, $result['previousRecords']->first()->weight);
    }

    public function test_get_current_and_previous_record_returns_immediate_previous_record_when_todays_row_does_not_exist_yet()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $previousRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-08']);
        $previousRecordMenu = new RecordMenu(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $previousRecordState->id]);
        $previousRecordMenu->recorded_at = '2026-06-08';
        $previousRecordMenu->save();

        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);
        // 今日分のRecordMenuはまだ作らない(初回記録前を再現)

        $service = new RecordContentService();
        $result = $service->getCurrentAndPreviousRecord($user->id, $category->id, $menu->id, $todayRecordState->id, '2026-06-10');

        $this->assertNull($result['tgtRecords']);
        $this->assertSame($previousRecordState->id, $result['previousRecordState']->id);
    }

    public function test_get_current_and_previous_record_returns_null_when_no_previous_record_exists()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);
        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);

        $service = new RecordContentService();
        $result = $service->getCurrentAndPreviousRecord($user->id, $category->id, $menu->id, $todayRecordState->id, '2026-06-10');

        $this->assertNull($result['tgtRecords']);
        $this->assertNull($result['previousRecordState']);
        $this->assertNull($result['previousRecords']);
    }

    public function test_get_current_and_previous_record_skips_previous_lookup_when_recorded_at_is_null()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $previousRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-08']);
        $previousRecordMenu = new RecordMenu(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $previousRecordState->id]);
        $previousRecordMenu->recorded_at = '2026-06-08';
        $previousRecordMenu->save();

        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);

        $service = new RecordContentService();
        $result = $service->getCurrentAndPreviousRecord($user->id, $category->id, $menu->id, $todayRecordState->id, null);

        $this->assertNull($result['previousRecordState']);
        $this->assertNull($result['previousRecords']);
    }

    public function test_get_current_and_previous_record_executes_a_bounded_number_of_queries()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $previousRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-08', 'bodyWeight' => 68]);
        $previousRecordMenu = new RecordMenu(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $previousRecordState->id]);
        $previousRecordMenu->recorded_at = '2026-06-08';
        $previousRecordMenu->save();
        $previousContent = new RecordContent(['record_menu_id' => $previousRecordMenu->id, 'weight' => 55, 'rep' => 10, 'set' => 1]);
        $previousContent->user_id = $user->id;
        $previousContent->save();

        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);
        $todayRecordMenu = new RecordMenu(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $todayRecordState->id]);
        $todayRecordMenu->recorded_at = '2026-06-10';
        $todayRecordMenu->save();
        $todayContent = new RecordContent(['record_menu_id' => $todayRecordMenu->id, 'weight' => 60, 'rep' => 10, 'set' => 1]);
        $todayContent->user_id = $user->id;
        $todayContent->save();

        $service = new RecordContentService();

        DB::enableQueryLog();
        $result = $service->getCurrentAndPreviousRecord($user->id, $category->id, $menu->id, $todayRecordState->id, '2026-06-10');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // tgtRecordMenu lookup, tgtRecords fetch, previousRecordMenu lookup, recordState load, previousRecords fetch の5クエリのみで完結すること
        $this->assertSame(5, $queryCount, 'getCurrentAndPreviousRecordの呼び出しで想定外のクエリ数(N+1等)が発生しないこと');
        $this->assertEquals(55, $result['previousRecords']->first()->weight);
    }
}

# 前回記録の常時表示化 + API統合 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 種目記録画面(`recordContents.vue`)の「前回の記録を埋める」ボタンを廃止し、画面表示時点で前回の記録を自動的に表示する。今回分と前回分の記録取得を `GET /api/recordContent` 1本のAPIに統合する。

**Architecture:** `RecordContentController::index` の分岐条件を並び替え、種目記録画面向けの分岐で `RecordContentService::getCurrentAndPreviousRecord`(新設)を呼び出し、今回分・前回分をまとめて1レスポンスで返す。フロントエンドは新しい composable `useGetRecordContent.ts` に1本化し、`recordContents.vue`(親)がマウント時に1回だけ取得して `RecordTable.vue` へpropsで渡す。使われなくなる `GET /api/recordMenu` エンドポイントと関連ファイルは削除する。

**Tech Stack:** Laravel 9(PHPUnit)、Vue 3 + TypeScript(vitest)

参照仕様書: `.claude/specs/2026-07-29-auto-show-previous-record-design.md`

---

## ファイル構成

**Create:**
- `src/resources/js/composables/record/useGetRecordContent.ts`
- `src/resources/js/composables/record/useGetRecordContent.test.ts`

**Modify:**
- `src/app/Services/RecordContent/RecordContentService.php`
- `src/app/Http/Controllers/RecordContentController.php`
- `src/app/Http/Controllers/RecordMenuController.php`
- `src/routes/api.php`
- `src/resources/js/utils/menuContentSessionStorage.ts`
- `src/resources/js/components/record/RecordTable.vue`
- `src/resources/js/components/record/recordContents.vue`
- `src/tests/Feature/Services/RecordContent/RecordContentServiceTest.php`
- `src/tests/Feature/RecordContentControllerTest.php`

**Delete:**
- `src/resources/js/composables/record/useGetTgtRecordContent.ts`
- `src/resources/js/composables/record/useGetSecondRecordContent.ts`

---

### Task 1: RecordContentService に getCurrentAndPreviousRecord を追加

**Files:**
- Modify: `src/app/Services/RecordContent/RecordContentService.php`
- Test: `src/tests/Feature/Services/RecordContent/RecordContentServiceTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Feature/Services/RecordContent/RecordContentServiceTest.php` の末尾、閉じ括弧 `}` の直前に以下を追加する。

```php
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

    public function test_get_current_and_previous_record_eager_loads_previous_record_state_without_n_plus_one()
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

        $service = new RecordContentService();
        $result = $service->getCurrentAndPreviousRecord($user->id, $category->id, $menu->id, $todayRecordState->id, '2026-06-10');

        DB::enableQueryLog();
        $result['previousRecordState']->bodyWeight;
        $result['previousRecords']->count();
        $queriesAfterAccess = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queriesAfterAccess, 'previousRecordState/previousRecordsへのアクセスで追加クエリが発生しないこと(N+1)');
    }
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `docker exec trainingmemo-app-1 php artisan test --filter=test_get_current_and_previous_record tests/Feature/Services/RecordContent/RecordContentServiceTest.php`
Expected: FAIL(`Call to undefined method App\Services\RecordContent\RecordContentService::getCurrentAndPreviousRecord()`)

- [ ] **Step 3: getCurrentAndPreviousRecord を実装する**

`src/app/Services/RecordContent/RecordContentService.php` の `getMenuHistory` メソッドの直後(クラスの閉じ括弧の直前)に追加する。

```php
    /**
     * 種目記録画面向けに、今回の記録内容と前回の記録内容をまとめて取得する。
     * $recordedAt が指定されている場合のみ前回分を検索する。
     * 今回分のRecordMenuが既に存在する場合は自分自身を1件スキップして前回分を探す。
     *
     * @param int $userId
     * @param int $categoryId
     * @param int $menuId
     * @param int $recordStateId
     * @param string|null $recordedAt
     * @return array{tgtRecords: ?Collection, previousRecordState: ?\App\Models\RecordState, previousRecords: ?Collection}
     */
    public function getCurrentAndPreviousRecord(
        int $userId,
        int $categoryId,
        int $menuId,
        int $recordStateId,
        ?string $recordedAt
    ): array {
        $tgtRecordMenu = RecordMenu::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->where('menu_id', $menuId)
            ->where('record_state_id', $recordStateId)
            ->first();

        $tgtRecords = $tgtRecordMenu
            ? $tgtRecordMenu->recordContents()->orderBy('set', 'asc')->get()
            : null;

        $previousRecordMenu = null;
        $previousRecords = null;

        if ($recordedAt) {
            $query = RecordMenu::where('user_id', $userId)
                ->where('category_id', $categoryId)
                ->where('menu_id', $menuId)
                ->whereDate('recorded_at', $tgtRecordMenu ? '<=' : '<', $recordedAt)
                ->orderBy('recorded_at', 'desc');

            $previousRecordMenu = $tgtRecordMenu
                ? $query->skip(1)->first()
                : $query->first();

            if ($previousRecordMenu) {
                $previousRecordMenu->load('recordState');
                $previousRecords = $previousRecordMenu->recordContents()->orderBy('set', 'asc')->get();
            }
        }

        return [
            'tgtRecords' => $tgtRecords,
            'previousRecordState' => $previousRecordMenu?->recordState,
            'previousRecords' => $previousRecords,
        ];
    }
```

- [ ] **Step 4: テストを実行してパスを確認する**

Run: `docker exec trainingmemo-app-1 php artisan test --filter=test_get_current_and_previous_record tests/Feature/Services/RecordContent/RecordContentServiceTest.php`
Expected: PASS(5 tests)

- [ ] **Step 5: 同ファイルの既存テストが引き続きパスすることを確認する**

Run: `docker exec trainingmemo-app-1 php artisan test tests/Feature/Services/RecordContent/RecordContentServiceTest.php`
Expected: PASS(全テスト)

---

### Task 2: RecordContentController の分岐条件を並び替え、記録画面分岐をService呼び出しに置き換える

**Files:**
- Modify: `src/app/Http/Controllers/RecordContentController.php`
- Test: `src/tests/Feature/RecordContentControllerTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Feature/RecordContentControllerTest.php` の冒頭のuse文に以下を追加する(このファイルは現状 `RecordContent` モデルをimportしていないため)。

```php
use App\Models\RecordContent;
```

追加位置は `use App\Models\Menu;` の直後、`use App\Models\RecordMenu;` の直前とする。

続けて、同ファイルの末尾、閉じ括弧 `}` の直前に以下を追加する。

```php
    public function test_record_screen_branch_returns_tgt_and_previous_records()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $previousRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-08', 'bodyWeight' => 68]);
        $previousRecordMenu = RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $previousRecordState->id, 'recorded_at' => '2026-06-08']);
        $previousContent = new RecordContent(['record_menu_id' => $previousRecordMenu->id, 'weight' => 55, 'rep' => 10, 'set' => 1]);
        $previousContent->user_id = $user->id;
        $previousContent->save();

        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);
        $todayRecordMenu = RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $todayRecordState->id, 'recorded_at' => '2026-06-10']);
        $todayContent = new RecordContent(['record_menu_id' => $todayRecordMenu->id, 'weight' => 60, 'rep' => 10, 'set' => 1]);
        $todayContent->user_id = $user->id;
        $todayContent->save();

        $response = $this->actingAs($user)->getJson(
            "/api/recordContent?user_id={$user->id}&category_id={$category->id}&menu_id={$menu->id}&record_state_id={$todayRecordState->id}&recorded_at=2026-06-10"
        );

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertCount(1, $body['tgtRecords']);
        $this->assertEquals(60, $body['tgtRecords'][0]['weight']);
        $this->assertSame($previousRecordState->id, $body['previousRecordState']['id']);
        $this->assertCount(1, $body['previousRecords']);
        $this->assertEquals(55, $body['previousRecords'][0]['weight']);
    }

    public function test_record_screen_branch_returns_previous_record_when_no_record_exists_for_today()
    {
        $user = User::factory()->create();
        $category = Category::create(['user_id' => $user->id, 'content' => '胸']);
        $menu = Menu::create(['user_id' => $user->id, 'category_id' => $category->id, 'content' => 'ベンチプレス', 'oneSide' => 0]);

        $previousRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-08', 'bodyWeight' => 68]);
        RecordMenu::forceCreate(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $previousRecordState->id, 'recorded_at' => '2026-06-08']);

        $todayRecordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-10']);
        // 今日分のRecordMenuはまだ作らない

        $response = $this->actingAs($user)->getJson(
            "/api/recordContent?user_id={$user->id}&category_id={$category->id}&menu_id={$menu->id}&record_state_id={$todayRecordState->id}&recorded_at=2026-06-10"
        );

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertNull($body['tgtRecords']);
        $this->assertSame($previousRecordState->id, $body['previousRecordState']['id']);
    }
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `docker exec trainingmemo-app-1 php artisan test --filter=test_record_screen_branch tests/Feature/RecordContentControllerTest.php`
Expected: FAIL(`previousRecordState`キーが未定義、またはtgtRecordsの中身が期待と異なる)

- [ ] **Step 3: RecordContentController::index を書き換える**

`src/app/Http/Controllers/RecordContentController.php` の `index` メソッド全体を以下に置き換える(`show`/`create`/`delete` メソッドは変更しない)。

```php
    public function index(GetRecordContentsRequest $request, RecordMenu $recordMenu, RecordState $recordState, RecordContentService $recordContentService){
        $user_id = $request->user_id;
        $category_id = $request->category_id;
        $menu_id = $request->menu_id;
        $record_state_id = $request->record_state_id;
        $recorded_at = $request->recorded_at;
        $recordContents=[];
        $recordContent=[];
        $menu=[];
        $category=[];

        // ホーム画面で記録の詳細表示
        if(!$category_id && !$recorded_at){
            $records = $recordContentService->getRecordsInRange($user_id, $request->resolvedFrom(), $request->resolvedTo());
            //記録日の重複削除
            $records = $records->unique('recorded_at');
            foreach($records as $record){
                // 初期化(初期化しないと前回のデータに追加されてしまうため)
                $recordContent=[];
                $menu = [];
                $category = [];

                $tgtRecordMenu = $record->recordMenus;
                $recorded_at = [
                    "record_id"=>$record->id,
                    "recorded_at"=>$record->recorded_at
                ];
                $hasRecordMenu = $tgtRecordMenu->isNotEmpty();
                $recordContent['recorded_at']=$recorded_at;
                // メニュー登録がある場合
                if($hasRecordMenu){
                    foreach($tgtRecordMenu as $recordMenuContent){
                            $menuContent = $recordMenuContent->menu->content;
                            $menuId = $recordMenuContent->menu_id;
                            $categoryContent = $recordMenuContent->category->content;
                            $categoryId = $recordMenuContent->category_id;
                            $menu[] = [
                                "menu_id"=>$menuId,
                                "menu_content"=>$menuContent
                            ];
                            $category[] = [
                                "category_id"=>$categoryId,
                                "category_content"=>$categoryContent
                            ];
                            // array_unique：重複を削除
                            $menu = array_unique($menu, SORT_REGULAR);
                            $category = array_unique($category, SORT_REGULAR);
                            $recordContent['menu']=$menu;
                            $recordContent['category']=$category;
                        }
                }
                $recordContents[] = $recordContent;
            }
            return response()->json(["status_code" => 200, "message" => "記録した全てのデータを取得", 'records'=>$recordContents]);
        }

        // メニュー選択画面にて記録済みメニューをマーキング
        // (記録画面では record_state_id が必ず送られるため、ここでは record_state_id が無い場合のみ扱う)
        if(isset($recorded_at) && !$record_state_id){
            $record = $recordState->where('user_id', $user_id)->where('recorded_at', $recorded_at)->first();
            // 初期化
            $recordContent=[];
            // その日のRecordStateがまだ存在しない場合(直接URLでアクセスした場合など)は、
            // メニュー未登録の状態として返す(record_idはnullとし、日付は表示のためリクエストの値を整形して使う)
            if(is_null($record)){
                $recordContent['recorded_at'] = [
                    "record_id"=>null,
                    "recorded_at"=>Carbon::parse($recorded_at)->toDateString()
                ];
                $recordContents[] = $recordContent;
                return response()->json(["status_code" => 200, "message" => "選択した日付のデータを取得", 'records'=>$recordContents]);
            }
            $record->load(['recordMenus']);
            $recorded_at = [
                "record_id"=>$record->id,
                "recorded_at"=>$record->recorded_at
            ];
            $hasRecordMenu = $recordMenu->where('record_state_id', $record->id)->exists();
            $recordContent['recorded_at']=$recorded_at;
            // メニュー登録がある場合
            if($hasRecordMenu){
                $recordMenus = $record->recordMenus->load(['menu', 'category']);
                foreach($recordMenus as $i =>$recordMenu){
                    $menuContent = $recordMenu->menu->content;
                    $menuId = $recordMenu->menu_id;
                    $categoryContent = $recordMenu->category->content;
                    $categoryId = $recordMenu->category_id;
                    $menu[] = [
                        "menu_id"=>$menuId,
                        "menu_content"=>$menuContent
                    ];
                    $category[] = [
                        "category_id"=>$categoryId,
                        "category_content"=>$categoryContent
                    ];
                    // array_unique：重複を削除
                    $menu = array_unique($menu, SORT_REGULAR);
                    $category = array_unique($category, SORT_REGULAR);
                    $recordContent['menu']=$menu;
                    $recordContent['category']=$category;
                }
            }
            $recordContents[] = $recordContent;
            return response()->json(["status_code" => 200, "message" => "選択した日付のデータを取得", 'records'=>$recordContents]);
        }

        // 筋トレ記録画面にて記録済み内容と前回の記録を表示(上記2分岐に当てはまらない場合のフォールバック)
        $result = $recordContentService->getCurrentAndPreviousRecord(
            $user_id, $category_id, $menu_id, $record_state_id, $recorded_at
        );
        return response()->json([
            "status_code" => 200,
            "message" => "記録日と前回の記録データを取得",
            "tgtRecords" => $result['tgtRecords'],
            "previousRecordState" => $result['previousRecordState'],
            "previousRecords" => $result['previousRecords'],
        ]);
    }
```

- [ ] **Step 4: テストを実行してパスを確認する**

Run: `docker exec trainingmemo-app-1 php artisan test --filter=test_record_screen_branch tests/Feature/RecordContentControllerTest.php`
Expected: PASS(2 tests)

- [ ] **Step 5: 既存の回帰テストが引き続きパスすることを確認する**

Run: `docker exec trainingmemo-app-1 php artisan test tests/Feature/RecordContentControllerTest.php`
Expected: PASS(全テスト。ホーム画面カレンダー分岐・日付マーキング分岐が並び替え後も従来通り動作することを確認する)

---

### Task 3: RecordMenuController::index と GET /api/recordMenu ルートを削除する

**Files:**
- Modify: `src/app/Http/Controllers/RecordMenuController.php`
- Modify: `src/routes/api.php`

- [ ] **Step 1: RecordMenuController::index を削除する**

`src/app/Http/Controllers/RecordMenuController.php` を以下の内容に置き換える(`create` メソッドのみ残す)。

```php
<?php

namespace App\Http\Controllers;

use App\Models\RecordMenu;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;

class RecordMenuController extends Controller
{
    public function create(Request $request, RecordMenu $recordMenu){
        // updateOrCreate()でCreateすると、既にデータがあるとupdateし、なければcreateを自動で行ってくれる。
        // updateOrCreate(['探索カラム名' => 条件となる値], ['値を格納するカラム名' => 値],);
        $recordMenu->updateOrCreate([
                'user_id'=>$request->user_id,
                'category_id'=>$request->category_id,
                'menu_id'=>$request->menu_id,
                'record_state_id'=>$request->record_state_id,
                'recorded_at'=>$request->recorded_at
            ],[
                'user_id'=>$request->user_id,
                'category_id'=>$request->category_id,
                'menu_id'=>$request->menu_id,
                'record_state_id'=>$request->record_state_id,
                'recorded_at'=>$request->recorded_at
            ]
        );
        return response()->json(["status_code" => 200, "message" => "記録開始します"]);
    }
}
```

- [ ] **Step 2: ルート定義を削除する**

`src/routes/api.php` から以下の1行を削除する。

```php
    Route::get('/recordMenu', [RecordMenuController::class, 'index']);
```

(`Route::post('/recordMenu/create', ...)` の行は変更しない)

- [ ] **Step 3: バックエンドのテストスイート全体を実行し、影響がないことを確認する**

Run: `docker exec trainingmemo-app-1 php artisan test`
Expected: PASS(全テスト。`RecordMenuController::index`/`GET /api/recordMenu` を参照するテストは存在しないため影響なし)

---

### Task 4: menuContentSessionStorage.ts のキャッシュキーを置き換える

**Files:**
- Modify: `src/resources/js/utils/menuContentSessionStorage.ts`

- [ ] **Step 1: ファイル全体を書き換える**

`src/resources/js/utils/menuContentSessionStorage.ts` を以下の内容に置き換える。

```ts
import { LoginUser } from "../types/loginUser";

export default function userSessionStorage(
    categoryId,
    menuId,
    recordStateId = null
) {
    const menuContentKey = `menuContent_${categoryId}_${menuId}`;
    const recordDataKey = `recordData_${categoryId}_${menuId}_${recordStateId}`;
    const historyRecordsKey = `historyRecords_${categoryId}_${menuId}_${recordStateId}`;
    // 日付(recordStateId)を問わず、部位+種目単位で保持する
    const complementContentsKey = `complementContents_${categoryId}_${menuId}`;

    const setMenuContentSession = (menuContent) =>
        sessionStorage.setItem(menuContentKey, JSON.stringify(menuContent));
    const getMenuContentSession = () =>
        JSON.parse(sessionStorage.getItem(menuContentKey));
    const removeMenuContentSession = () =>
        sessionStorage.removeItem(menuContentKey);

    // 今回の記録＋前回の記録をまとめてキャッシュする(同一種目・同一日への再訪問時に再フェッチを避ける)
    const getRecordDataSession = () =>
        JSON.parse(sessionStorage.getItem(recordDataKey));
    const setRecordDataSession = (
        tgtRecords,
        hasTgtRecord,
        previousRecords,
        previousRecordState,
        hasPreviousRecord
    ) =>
        sessionStorage.setItem(
            recordDataKey,
            JSON.stringify({
                tgtRecords,
                hasTgtRecord,
                previousRecords,
                previousRecordState,
                hasPreviousRecord,
            })
        );
    const removeRecordDataSession = () =>
        sessionStorage.removeItem(recordDataKey);

    const getHistoryRecordSession = () =>
        JSON.parse(sessionStorage.getItem(historyRecordsKey));
    const setHistoryRecordSession = (
        historyRecords,
        historyMenus,
        hasHistoryRecord
    ) =>
        sessionStorage.setItem(
            historyRecordsKey,
            JSON.stringify({ historyRecords, historyMenus, hasHistoryRecord })
        );
    const removeHistoryRecordSession = () =>
        sessionStorage.removeItem(historyRecordsKey);

    // 「重量・回数を補完する」チェックボックスの状態を部位+種目単位で保持する
    const getComplementContentsSession = (): boolean =>
        sessionStorage.getItem(complementContentsKey) === "true";
    const setComplementContentsSession = (value: boolean) =>
        sessionStorage.setItem(complementContentsKey, String(value));

    return {
        setMenuContentSession,
        getMenuContentSession,
        removeMenuContentSession,
        getRecordDataSession,
        setRecordDataSession,
        removeRecordDataSession,
        getHistoryRecordSession,
        setHistoryRecordSession,
        removeHistoryRecordSession,
        getComplementContentsSession,
        setComplementContentsSession,
    };
}
```

(このファイルにvitestテストは存在しない。呼び出し元の変更はTask 7で行う)

---

### Task 5: useGetRecordContent.ts を新設する(今回分+前回分をまとめて取得する統合composable)

**Files:**
- Create: `src/resources/js/composables/record/useGetRecordContent.ts`
- Test: `src/resources/js/composables/record/useGetRecordContent.test.ts`

- [ ] **Step 1: 失敗するテストを書く**

`src/resources/js/composables/record/useGetRecordContent.test.ts` を新規作成する。

```ts
import { describe, it, expect, vi, afterEach } from "vitest";
import axios from "axios";
import useGetRecordContent from "./useGetRecordContent";

vi.mock("axios");

afterEach(() => {
  vi.clearAllMocks();
});

describe("useGetRecordContent", () => {
  it("tgtRecordsとpreviousRecordsが両方存在する場合、それぞれhasフラグがtrueになりデータが反映される", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: {
        tgtRecords: [{ set: 1, weight: 60, rep: 10 }],
        previousRecordState: { id: 1, bodyWeight: 70 },
        previousRecords: [{ set: 1, weight: 55, rep: 10 }],
      },
    });

    const {
      hasTgtRecord,
      hasPreviousRecord,
      tgtRecords,
      previousRecords,
      previousRecordState,
      getRecordContent,
    } = useGetRecordContent();
    await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(hasTgtRecord.value).toBe(true);
    expect(hasPreviousRecord.value).toBe(true);
    expect(tgtRecords.value).toHaveLength(1);
    expect(previousRecords.value).toHaveLength(1);
    expect(previousRecordState.value).toEqual({ id: 1, bodyWeight: 70 });
  });

  it("tgtRecordsもpreviousRecordsも存在しない場合、両方のhasフラグがfalseになる", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: { tgtRecords: null, previousRecordState: null, previousRecords: null },
    });

    const { hasTgtRecord, hasPreviousRecord, getRecordContent } = useGetRecordContent();
    await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(hasTgtRecord.value).toBe(false);
    expect(hasPreviousRecord.value).toBe(false);
  });

  it("tgtRecordsのみ存在しpreviousRecordsが存在しない場合、hasTgtRecordのみtrueになる", async () => {
    vi.mocked(axios.get).mockResolvedValue({
      data: {
        tgtRecords: [{ set: 1, weight: 60, rep: 10 }],
        previousRecordState: null,
        previousRecords: null,
      },
    });

    const { hasTgtRecord, hasPreviousRecord, getRecordContent } = useGetRecordContent();
    await getRecordContent(1, "1", "1", "1", "2026-07-27");

    expect(hasTgtRecord.value).toBe(true);
    expect(hasPreviousRecord.value).toBe(false);
  });
});
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `cd src && npx vitest run resources/js/composables/record/useGetRecordContent.test.ts`
Expected: FAIL(`Failed to resolve import "./useGetRecordContent"`)

- [ ] **Step 3: useGetRecordContent.ts を実装する**

`src/resources/js/composables/record/useGetRecordContent.ts` を新規作成する。

```ts
import { ref } from "vue";
import axios from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { TgtRecordContent, HistoryRecord, LatestRecord } from "../../types/record";

export default function useGetRecordContent() {
    const tgtRecords = ref<TgtRecordContent[]>([]);
    const hasTgtRecord = ref<boolean>(false);
    const previousRecords = ref<HistoryRecord[]>([]);
    const previousRecordState = ref<LatestRecord>(undefined);
    const hasPreviousRecord = ref<boolean>(false);

    // 今回の記録と前回の記録をまとめて取得する
    const getRecordContent = async (
        user_id: number,
        category_id: string,
        menu_id: string,
        record_state_id: string,
        recorded_at: string
    ) => {
        await axios
            .get("/api/recordContent", {
                params: {
                    user_id,
                    category_id,
                    menu_id,
                    record_state_id,
                    recorded_at,
                },
            })
            .then((res) => {
                if (res.data.tgtRecords) {
                    tgtRecords.value = res.data.tgtRecords;
                    hasTgtRecord.value = true;
                } else {
                    hasTgtRecord.value = false;
                }
                if (res.data.previousRecords) {
                    previousRecords.value = res.data.previousRecords;
                    previousRecordState.value = res.data.previousRecordState;
                    hasPreviousRecord.value = true;
                } else {
                    hasPreviousRecord.value = false;
                }
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
            });
    };

    return {
        tgtRecords,
        hasTgtRecord,
        previousRecords,
        previousRecordState,
        hasPreviousRecord,
        getRecordContent,
    };
}
```

- [ ] **Step 4: テストを実行してパスを確認する**

Run: `cd src && npx vitest run resources/js/composables/record/useGetRecordContent.test.ts`
Expected: PASS(3 tests)

---

### Task 6: RecordTable.vue を props 経由に変更し、useGetTgtRecordContent.ts を削除する

**Files:**
- Modify: `src/resources/js/components/record/RecordTable.vue`
- Delete: `src/resources/js/composables/record/useGetTgtRecordContent.ts`

- [ ] **Step 1: import文と型を更新する**

`src/resources/js/components/record/RecordTable.vue` の以下の行

```ts
import useGetTgtRecordContent from "../../composables/record/useGetTgtRecordContent.js";
import axios from "axios";
import { useStore } from "vuex";
import { HistoryRecord } from "../../types/record";
```

を以下に置き換える。

```ts
import axios from "axios";
import { useStore } from "vuex";
import { HistoryRecord, TgtRecordContent } from "../../types/record";
```

- [ ] **Step 2: props定義に tgtRecord / hasTgtRecord を追加する**

以下のブロック

```ts
const props = defineProps<{
  second_record: HistoryRecord[];
  hasSecondRecord: boolean;
  hasOneHand: boolean;
  category_id: string;
  menu_id: string;
  record_state_id: string;
  menu_content: string;
  beforeHeaderTxt: string;
  complementContents: boolean;
}>();
```

を以下に置き換える。

```ts
const props = defineProps<{
  second_record: HistoryRecord[];
  hasSecondRecord: boolean;
  tgtRecord: TgtRecordContent[];
  hasTgtRecord: boolean;
  hasOneHand: boolean;
  category_id: string;
  menu_id: string;
  record_state_id: string;
  menu_content: string;
  beforeHeaderTxt: string;
  complementContents: boolean;
}>();
```

- [ ] **Step 3: 自前フェッチをpropsからのcomputedに置き換える**

以下の行

```ts
const canClickFillBefore = ref<boolean>(false);
```

を削除する。

続けて、以下の行

```ts
//今回記録するデータの値を取得
const { tgtRecord, hasTgtRecord, getTgtRecords } = useGetTgtRecordContent();
```

を以下に置き換える。

```ts
//今回記録するデータの値(親から渡される)
const tgtRecord: ComputedRef<TgtRecordContent[]> = computed(() => props.tgtRecord);
const hasTgtRecord: ComputedRef<boolean> = computed(() => props.hasTgtRecord);
```

- [ ] **Step 4: defineEmitsからcanClickを削除する**

以下のブロック

```ts
const emits = defineEmits<{
  (e: "totalSet", value: string): void;
  (e: "beforeTotalSet", value: string): void;
  (e: "canClick", value: boolean): void;
}>();
```

を以下に置き換える。

```ts
const emits = defineEmits<{
  (e: "totalSet", value: string): void;
  (e: "beforeTotalSet", value: string): void;
}>();
```

- [ ] **Step 5: postRecordContentからcanClick関連の記述を削除する**

以下のブロック

```ts
const postRecordContent = (index: number) => {
  axios
    .post("/api/recordContent/create", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      weight: weight.value[index],
      right_weight: rightWeight.value[index],
      right_rep: rightRep.value[index],
      left_weight: leftWeight.value[index],
      left_rep: leftRep.value[index],
      rep: rep.value[index],
      set: index + 1,
      memo: memo.value[index],
    })
    .then((res) => {
      // 今回の合計セット数
      canClickFillBefore.value = true;
      // emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
      emits("totalSet", res.data.totalSet);
      if (res.data.totalSet > 0) {
        canClickFillBefore.value = true;
      } else {
        canClickFillBefore.value = false;
      }
      emits("canClick", canClickFillBefore.value);
    })
    .catch((err) => {
      canClickFillBefore.value = false;
      emits("canClick", canClickFillBefore.value);
    });
};
```

を以下に置き換える。

```ts
const postRecordContent = (index: number) => {
  axios
    .post("/api/recordContent/create", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      weight: weight.value[index],
      right_weight: rightWeight.value[index],
      right_rep: rightRep.value[index],
      left_weight: leftWeight.value[index],
      left_rep: leftRep.value[index],
      rep: rep.value[index],
      set: index + 1,
      memo: memo.value[index],
    })
    .then((res) => {
      // emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
      emits("totalSet", res.data.totalSet);
    })
    .catch((err) => {});
};
```

- [ ] **Step 6: tgtRecordのwatchをimmediate化し、canClick関連を削除する**

以下のブロック

```ts
//tgtRecordを初期レンダリング時に取得するため、変更を常にwatchする。
watch(tgtRecord, () => {
  if (hasTgtRecord.value) {
    canClickFillBefore.value = true;
    emits("canClick", canClickFillBefore.value);
    //emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
    emits("totalSet", tgtRecord.value.length.toString());
    tgtRecord.value.forEach((record) => {
      const index: number = record.set - 1;
      weight.value[index] = record.weight !== null ? record.weight : "";
      rep.value[index] = record.rep !== null ? record.rep : "";
      rightWeight.value[index] = record.right_weight !== null ? record.right_weight : "";
      rightRep.value[index] = record.right_rep !== null ? record.right_rep : "";
      leftWeight.value[index] = record.left_weight !== null ? record.left_weight : "";
      leftRep.value[index] = record.left_rep !== null ? record.left_rep : "";
      memo.value[index] = record.memo !== null ? record.memo : "";
      if (record.set > 5) {
        const tempObj = ref([]);
        for (let i = 6; i <= record.set; i++) {
          tempObj.value[i] = { set: record.set + i };
          contents.value = [...contents.value, tempObj.value[i]];
        }
      }
    });
  } else {
    canClickFillBefore.value = false;
    emits("canClick", canClickFillBefore.value);
  }
});
```

を以下に置き換える。

```ts
//tgtRecordを初期レンダリング時に取得するため、変更を常にwatchする。
watch(
  tgtRecord,
  () => {
    if (hasTgtRecord.value) {
      //emit()で親に値を渡す、第一引数：親側の@～の～の名前、第二引数：親に渡す値
      emits("totalSet", tgtRecord.value.length.toString());
      tgtRecord.value.forEach((record) => {
        const index: number = record.set - 1;
        weight.value[index] = record.weight !== null ? record.weight : "";
        rep.value[index] = record.rep !== null ? record.rep : "";
        rightWeight.value[index] = record.right_weight !== null ? record.right_weight : "";
        rightRep.value[index] = record.right_rep !== null ? record.right_rep : "";
        leftWeight.value[index] = record.left_weight !== null ? record.left_weight : "";
        leftRep.value[index] = record.left_rep !== null ? record.left_rep : "";
        memo.value[index] = record.memo !== null ? record.memo : "";
        if (record.set > 5) {
          const tempObj = ref([]);
          for (let i = 6; i <= record.set; i++) {
            tempObj.value[i] = { set: record.set + i };
            contents.value = [...contents.value, tempObj.value[i]];
          }
        }
      });
    } else {
      emits("totalSet", "0");
    }
  },
  { immediate: true }
);
```

- [ ] **Step 7: onMountedから自前フェッチを削除する**

以下のブロック

```ts
onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  await getTgtRecords(
    loginUser.value.id,
    props.category_id,
    props.menu_id,
    props.record_state_id
  );
  if (tgtRecord.value.length == 0) {
    emits("totalSet", "0");
  }
  store.commit("compGetData", true);

  thisMemo.value &&
    thisMemo.value.forEach((elm, index) => {
      elm.value !== "" && adjustHeight(elm, beforeMemo.value[index]);
    });
});
```

を以下に置き換える。

```ts
onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  store.commit("compGetData", true);

  thisMemo.value &&
    thisMemo.value.forEach((elm, index) => {
      elm.value !== "" && adjustHeight(elm, beforeMemo.value[index]);
    });
});
```

- [ ] **Step 8: useGetTgtRecordContent.ts を削除する**

`src/resources/js/composables/record/useGetTgtRecordContent.ts` を削除する。

- [ ] **Step 9: フロントエンドのテストスイート全体を実行し、影響がないことを確認する**

Run: `cd src && npm run test`
Expected: PASS(全テスト。`useGetTgtRecordContent`を参照するテストは存在しないため影響なし)

---

### Task 7: recordContents.vue のボタンを廃止し、統合composable+セッションキャッシュに置き換える

**Files:**
- Modify: `src/resources/js/components/record/recordContents.vue`
- Delete: `src/resources/js/composables/record/useGetSecondRecordContent.ts`

- [ ] **Step 1: テンプレートを書き換える**

`src/resources/js/components/record/recordContents.vue` の `<template>` ブロック全体を以下に置き換える。

```html
<template>
  <div>
    <template v-if="compGetData">
      <table class="border border-collapse table-fixed mx-auto">
        <caption
          class="p-5 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800"
        >
          <button
            class="block w-11/12 bg-green-500 hover:bg-green-700 text-white font-bold md:py-2 py-px px-4 border-2 border-black mt-3 mb-3 mx-auto"
            @click="confirmHistory()"
          >
            履歴を確認
          </button>
          <div class="text-center mt-5">
            <input
              class="bg-slate-100 border-black border-x border-y mr-2"
              id="complementContents"
              type="checkbox"
              v-model="complementContents"
            />
            <label for="complementContents" class="text-base align-[1px]"
              >重量・回数を補完する</label
            >
          </div>
          <div class="grid grid-cols-2 w-full">
            <div>
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の体重：{{ bodyWeight }}
              </p>
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の合計セット数：{{ thisTotalSet }}
              </p>
            </div>
            <template v-if="isBeforeData">
              <div>
                <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                  {{ BeforeWeightTxt }}：{{ beforeBodyWeight }}
                </p>
                <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                  {{ BeforeTotalSetTxt }}：{{ beforeTotalSet }}
                </p>
              </div>
            </template>
            <template v-else>
              <div>
                <p>{{ msgNoBeforeData }}</p>
              </div>
            </template>
          </div>
        </caption>

        <RecordTable
          :second_record="previousRecords"
          :hasSecondRecord="hasPreviousRecord"
          :tgtRecord="tgtRecords"
          :hasTgtRecord="hasTgtRecord"
          :hasOneHand="hasOneHand"
          :category_id="category_id"
          :menu_id="menu_id"
          :record_state_id="record_state_id"
          :menu_content="menuContent"
          :complementContents="complementContents"
          :beforeHeaderTxt="BeforeHeaderTxt"
          @beforeTotalSet="fillBeforeTodalSet"
          @totalSet="fillThisTodalSet"
        />
      </table>
    </template>
    <template v-else>
      <LoadingSpinner />
    </template>
    <Modal v-model="showModal" :title="menuContent" modal-class="scrollable-modal">
      <div class="scrollable-content">
        <HistoryRecordContents
          :historyMenus="historyMenus"
          :historyRecords="historyRecords"
          :hasHistoryRecord="hasHistoryRecord"
          :hasOneHand="hasOneHand"
        />
      </div>
      <div class="row scrollable-modal-footer">
        <div class="col-sm-12">
          <div class="text-center">
            <button
              class="block w-11/12 bg-blue-500 hover:bg-blue-700 text-white font-bold border-2 border-black mx-auto"
              type="button"
              @click="showModal = false"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Modal>
    <Modal
      v-model="dispAlertModal"
      title="権限エラー"
      wrapper-class="modal-wrapper"
      class="flex align-center"
      @closing="toHome()"
    >
      <p>画面表示するにはログインしてください。</p>
      <button
        class="col-12 mt-5 text-center inline-block w-full rounded px-6 pb-2 pt-2.5 text-base font-medium uppercase leading-normal text-white shadow-[0_4px_9px_-4px_rgba(0,0,0,0.2)] transition duration-150 ease-in-out hover:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)] focus:outline-none focus:ring-0 active:shadow-[0_8px_9px_-4px_rgba(0,0,0,0.1),0_4px_18px_0_rgba(0,0,0,0.2)]"
        style="background: linear-gradient(to right, #ee7724, #d8363a, #dd3675, #b44593)"
        @click="toLogin"
      >
        ログイン画面へ
      </button>
    </Modal>
  </div>
</template>
```

- [ ] **Step 2: scriptブロックを書き換える**

`<script setup lang="ts">` ブロック全体(`</script>`の直前まで)を以下に置き換える。

```ts
<script setup lang="ts">
import { ref, onMounted, computed, watch, ComputedRef } from "vue";
import {
  useRoute,
  useRouter,
  onBeforeRouteLeave,
  NavigationGuardNext,
  RouteLocationNormalized,
} from "vue-router";
import { useStore } from "vuex";
import useGetRecordState from "../../composables/record/useGetRecordState";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import useGetRecordContent from "../../composables/record/useGetRecordContent";
import RecordTable from "./RecordTable.vue";
import HistoryRecordContents from "./HistoryRecordContents.vue";
import LoadingSpinner from "../common/LoadingSpinner.vue";
import useGetHistoryRecordContent from "../../composables/record/useGetHistoryRecordContent.js";
import axios from "axios";
import userSessionStorage from "../../utils/userSessionStorage";
import menuContentSessionStorage from "../../utils/menuContentSessionStorage";

const route = useRoute();
const store = useStore();
const router = useRouter();

const category_id: string = route.query.categoryId as string;
const menu_id: string = route.query.menuId as string;
const record_state_id: string = route.query.recordId as string;

const {
  setMenuContentSession,
  getMenuContentSession,
  removeMenuContentSession,
  getRecordDataSession,
  setRecordDataSession,
  getHistoryRecordSession,
  setHistoryRecordSession,
  removeHistoryRecordSession,
  getComplementContentsSession,
  setComplementContentsSession,
} = menuContentSessionStorage(category_id, menu_id, record_state_id);

const hasOneHand = ref<boolean>(false);

const bodyWeight = ref<string>("");
const beforeBodyWeight = ref<string>("");

const thisTotalSet = ref<string>("");
const beforeTotalSet = ref<string>("");

const msgNoBeforeData = ref<string>("");

const compGetData = ref<boolean>(false);

const showModal = ref<boolean>(false);

const BeforeWeightTxt = "前回の体重";
const BeforeTotalSetTxt = "前回の合計セット数";
const BeforeHeaderTxt = "前回の記録";

const menuContent = ref<string>("");

const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

// 自動補完するか(部位+種目単位でsessionStorageに保存された値を初期値として復元する)
const complementContents = ref<boolean>(getComplementContentsSession());

// チェックボックスの状態が変わるたびに部位+種目単位で保存する
watch(complementContents, (value) => {
  setComplementContentsSession(value);
});

//前回データが存在するか？
const isBeforeData = ref<boolean>(false);

// 最新のレコード状態を取得
const { getLatestRecordState, latestRecord } = useGetRecordState();

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();

// 今回の記録と前回の記録をまとめて取得
const {
  tgtRecords,
  hasTgtRecord,
  previousRecords,
  previousRecordState,
  hasPreviousRecord,
  getRecordContent,
} = useGetRecordContent();

const toHome = (): void => {
  //router.pushが効かない
  window.location.href = "/";
};
const toLogin = (): void => {
  router.push("/login");
};

// 片方ずつ記録するかどうかmenusテーブルのoneSideカラムにて判断
const getMenuContent = async () => {
  const menuContentSession = getMenuContentSession();
  if (menuContentSession) {
    const data = menuContentSession;
    menuContent.value = data.content;
    hasOneHand.value = data.oneSide === 1;
    return;
  }
  await axios
    .get("/api/menus", {
      params: {
        user_id: loginUser.value.id,
        category_id: category_id,
        menu_id: menu_id,
      },
    })
    .then((res) => {
      menuContent.value = res.data.menu.content;
      setMenuContentSession(res.data.menu);
      if (res.data.menu.oneSide === 1) {
        hasOneHand.value = true;
      } else {
        hasOneHand.value = false;
      }
    })
    .catch((err) => {});
};

//第一引数に子供の値が入っている。
const fillThisTodalSet = (e: string): void => {
  thisTotalSet.value = e;
};

const fillBeforeTodalSet = (e: string) => {
  beforeTotalSet.value = e;
};

const {
  historyRecords,
  historyMenus,
  hasHistoryRecord,
  getHistoryRecords,
} = useGetHistoryRecordContent();

const confirmHistory = async () => {
  const historyRecordSession = getHistoryRecordSession();

  if (historyRecordSession) {
    const data = historyRecordSession;
    historyRecords.value = data.historyRecords;
    historyMenus.value = data.historyMenus;
    hasHistoryRecord.value = data.hasHistoryRecord;
    showModal.value = true;
    return;
  }

  //今回記録するデータの値を取得
  await getHistoryRecords(
    loginUser.value.id,
    category_id,
    menu_id,
    record_state_id,
    route.params.recordId as string
  );
  setHistoryRecordSession(
    historyRecords.value,
    historyMenus.value,
    hasHistoryRecord.value
  );
  showModal.value = true;
};

const deleteFirstRecord = async () => {
  await axios
    .post("/api/recordContent/delete", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      set: 0,
    })
    .then((res) => {})
    .catch((err) => {});
};

const firstRecord = async () => {
  await axios
    .post("/api/recordContent/create", {
      user_id: loginUser.value.id,
      category_id: route.query.categoryId,
      menu_id: route.query.menuId,
      record_state_id: route.query.recordId,
      recorded_at: route.params.recordId,
      set: 0,
    })
    .then((res) => {})
    .catch((err) => {});
};

onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  if (dispModal.value) {
    dispAlertModal.value = true;
  }
  await getLatestRecordState();
  await getMenuContent();

  const recordDataSession = getRecordDataSession();
  if (recordDataSession) {
    tgtRecords.value = recordDataSession.tgtRecords || [];
    hasTgtRecord.value = recordDataSession.hasTgtRecord;
    previousRecords.value = recordDataSession.previousRecords || [];
    previousRecordState.value = recordDataSession.previousRecordState;
    hasPreviousRecord.value = recordDataSession.hasPreviousRecord;
  } else {
    await getRecordContent(
      loginUser.value.id,
      category_id,
      menu_id,
      record_state_id,
      route.params.recordId as string
    );
    setRecordDataSession(
      tgtRecords.value,
      hasTgtRecord.value,
      previousRecords.value,
      previousRecordState.value,
      hasPreviousRecord.value
    );
  }
  isBeforeData.value = hasPreviousRecord.value;
  beforeBodyWeight.value = previousRecordState.value?.bodyWeight
    ? previousRecordState.value.bodyWeight.toString()
    : "";
  msgNoBeforeData.value = hasPreviousRecord.value ? "" : "記録がありません";

  compGetData.value = true;

  if (latestRecord.value.bodyWeight) {
    bodyWeight.value = `${latestRecord.value.bodyWeight} kg`;
  } else {
    // bodyWeight.value = "記録されていません";
  }
});
</script>
```

- [ ] **Step 3: useGetSecondRecordContent.ts を削除する**

`src/resources/js/composables/record/useGetSecondRecordContent.ts` を削除する。

- [ ] **Step 4: フロントエンドのテストスイート全体を実行し、影響がないことを確認する**

Run: `cd src && npm run test`
Expected: PASS(全テスト)

---

### Task 8: 動作確認

**Files:** なし(確認のみ)

- [ ] **Step 1: バックエンドのテストスイート全体を実行する**

Run: `docker exec trainingmemo-app-1 php artisan test`
Expected: PASS(全テスト)

- [ ] **Step 2: フロントエンドのテストスイート全体を実行する**

Run: `cd src && npm run test`
Expected: PASS(全テスト)

- [ ] **Step 3: フロントエンドをビルドし、型エラー・ビルドエラーがないことを確認する**

Run: `cd src && npm run build`
Expected: ビルドが正常終了する(エラー無し)

- [ ] **Step 4: chrome-screen-check スキルで実画面を確認する**

`chrome-screen-check` スキルを使用し、種目の記録画面を開いて以下を確認する。
- 「前回の記録を埋める」ボタンが表示されないこと
- 画面を開いた時点で前回の記録が右側に自動的に表示されること(前回データがある種目)
- 前回の記録が無い種目では「記録がありません」がボタン操作なしに表示されること
- 「今回にコピー」ボタン・重量回数の自動補完チェックボックスなど既存機能が引き続き動作すること
- 同じ種目・同じ日を再度開いた際に前回データが即座に表示されること(セッションキャッシュの動作確認)
- 「履歴を確認」ボタンが引き続き正常に動作すること

---

## 最終コミット

**注意:** このリポジトリには本計画の作業開始前から、本計画と無関係な未コミット変更が存在する(`git status`で確認できる `RegisterService.php` / `WeightService.php` / `WeightRecordForm.vue` / `docs/seo/` / `infra/bootstrap/` / `scratchpad/` など)。`git add -A` は使わず、本計画で触ったファイルのみを明示的に `git add` すること。

また `RecordContentController.php` / `RecordContentService.php` / `RecordContentServiceTest.php` の3ファイルは、本計画の作業開始前から(おそらく別セッションでの関連作業により)既に未コミットの変更が入っている状態だった。本計画のTask 1・2はその状態の上に追記する形で編集しているため、`git add` するとその既存の変更も一緒にコミットされる。コミット前に `git diff --stat` でこの3ファイルの差分内容を確認し、`getRecordsInRange`/`getMenuHistory` 関連の既存差分が含まれることを把握した上で進めること。もし既存差分を本計画のコミットに含めるべきでないとユーザーが判断した場合は、先にユーザーへ相談する。

全タスク完了後、以下を実行する。

```bash
git add \
  src/app/Services/RecordContent/RecordContentService.php \
  src/app/Http/Controllers/RecordContentController.php \
  src/app/Http/Controllers/RecordMenuController.php \
  src/routes/api.php \
  src/resources/js/utils/menuContentSessionStorage.ts \
  src/resources/js/components/record/RecordTable.vue \
  src/resources/js/components/record/recordContents.vue \
  src/resources/js/composables/record/useGetRecordContent.ts \
  src/resources/js/composables/record/useGetRecordContent.test.ts \
  src/tests/Feature/Services/RecordContent/RecordContentServiceTest.php \
  src/tests/Feature/RecordContentControllerTest.php

git rm src/resources/js/composables/record/useGetTgtRecordContent.ts
git rm src/resources/js/composables/record/useGetSecondRecordContent.ts

git status
```

`git status` の出力で、上記以外の無関係なファイル(`RegisterService.php` 等)がステージされていないことを確認してから、以下でコミットする。

```bash
git commit -m "$(cat <<'EOF'
feat: 前回記録を常時表示化しAPIを1本に統合

「前回の記録を埋める」ボタンを廃止し、種目記録画面を開いた時点で
前回の記録を自動的に表示するように変更。今回分・前回分の記録取得を
GET /api/recordContent 1本のAPIに統合し、不要になった
GET /api/recordMenu エンドポイントと関連コードを削除した。
EOF
)"
```

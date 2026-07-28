# 体重管理ページ API統合(dashboardエンドポイント) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 体重管理ページ(`weightManagement.vue`)の初期表示・保存後・期間変更・日付変更のたびに直列実行されている4本のAPI呼び出し(`GET /api/weight`(履歴)、`GET /api/weight/tagStats`、`GET /api/weight`(選択日レコード、2回目)、`GET /api/weight/tags`)を、1本の `GET /api/weight/dashboard` エンドポイントに統合し、リクエスト往復回数とレイテンシを削減する。

**Architecture:** `WeightService` に既存の `getWeightHistory` / `getAllTags` / `getTagStatistics` を内部で呼び出して1つのレスポンス形状に合成する `getDashboardData()` を追加する。選択日レコードは、取得済みの履歴配列(`from`〜`to`)に含まれていればそれを再利用し、範囲外の場合のみ追加で軽量な単日クエリを行う(既存の「範囲取得→フィルタ」より無駄なクエリを増やさない)。`WeightController` に新しい `dashboard()` アクションを追加し、既存の `index()` / `tags()` / `tagStats()` は他に利用箇所がないため削除する(`store()` / `storeTag()` / `destroyTag()` / `updateTargetWeight()` は変更なし)。フロントエンドは新しい `useGetWeightDashboard` コンポーザブルに一本化し、`weightManagement.vue` 内の4つの個別フェッチ処理を1つに統合する。

**Tech Stack:** Laravel 9 (PHP) / PHPUnit, Vue 3 + TypeScript (Composition API) / axios

**調査結果:** `GET /api/weight`・`/api/weight/tags`・`/api/weight/tagStats` は `weightManagement.vue` 以外から参照されていないことを確認済み(grep調査済み)。よって旧エンドポイントを安全に削除できる。データ量面では `record_states` テーブルが7列のみの軽量スキーマで `(user_id, recorded_at)` の複合indexが既にあり、`tagStats`が生涯全記録をスキャンする既存コスト(統合の影響を受けない)を含めても現実的な利用規模で問題にならないことを確認済み。

---

## ファイル構造

### バックエンド(新規)

- `app/Http/Requests/Weight/GetWeightDashboardRequest.php`
- `tests/Unit/Requests/Weight/GetWeightDashboardRequestTest.php`

### バックエンド(変更)

- `app/Services/Weight/WeightService.php` — `getDashboardData()` 追加
- `app/Http/Controllers/WeightController.php` — `dashboard()` 追加、`index()`/`tags()`/`tagStats()` 削除
- `routes/api.php` — `GET /weight`・`GET /weight/tags`・`GET /weight/tagStats` を `GET /weight/dashboard` に置き換え
- `tests/Feature/Services/Weight/WeightServiceTest.php` — `getDashboardData()` のテスト追加
- `tests/Feature/WeightControllerTest.php` — 旧3エンドポイントのテストを `dashboard` エンドポイントのテストに置き換え

### バックエンド(削除)

- `app/Http/Requests/Weight/GetWeightHistoryRequest.php`
- `tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php`

### フロントエンド(新規)

- `resources/js/composables/weight/useGetWeightDashboard.ts`

### フロントエンド(変更)

- `resources/js/types/weight.d.ts` — `WeightDashboardResponse` 型追加、未使用になる `WeightHistoryResponse`/`WeightTagsResponse`/`TagStatisticsResponse` 削除
- `resources/js/views/weight/weightManagement.vue` — 4本の個別フェッチを `useGetWeightDashboard` 1本に統合
- `resources/js/components/weight/WeightRecordForm.vue` — 内部で独自に呼んでいた `useGetWeightTags()` を削除し、親から`weightTags`をpropsで受け取る形に変更(**Task 5実装後に判明した追加要件**。詳細はTask 6内の追記を参照)

**【計画修正】Task 5実装中に判明した調査漏れ:** 当初の調査(`grep`で`/api/weight`のURL文字列を検索)では、`WeightRecordForm.vue`が`useGetWeightTags`コンポーザブルを内部で直接呼び出している(`WeightRecordForm.vue:60,74,116`)ことを見落としていた。これにより、`weightManagement.vue`が体重記録フォームを表示するたびに(日付変更のたびに`:key`が変わって再マウントされる)`WeightRecordForm`が独自に`GET /api/weight/tags`をもう1回叩いていた — つまり当初把握していた「4本」よりも実際には多くの重複呼び出しが発生していた。Task 5で`useGetWeightTags.ts`を削除したことで`WeightRecordForm.vue`が壊れるため、Task 6で合わせて修正する。

### フロントエンド(削除)

- `resources/js/composables/weight/useGetWeightHistory.ts`
- `resources/js/composables/weight/useGetWeightTags.ts`

---

## Task 1: `WeightService::getDashboardData()` を追加する

**Files:**
- Modify: `app/Services/Weight/WeightService.php:158-170`(末尾に追加)
- Test: `tests/Feature/Services/Weight/WeightServiceTest.php:308-319`(末尾に追加)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Services/Weight/WeightServiceTest.php` の末尾(317行目の `}` の直前、クラスの閉じ括弧の前)に追加する。

```php
    public function test_get_dashboard_data_returns_records_tags_and_stats(): void
    {
        $user = User::factory()->create();
        $user->target_weight = 60.0;
        $user->target_weight_date = '2026-12-31';
        $user->save();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.5]);
        $service = new WeightService();

        $result = $service->getDashboardData(
            $user->id,
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
            Carbon::parse('2026-07-10')->startOfDay()
        );

        $this->assertCount(2, $result['records']);
        $this->assertEquals(60.0, $result['target_weight']);
        $this->assertEquals('2026-12-31', $result['target_weight_date']);
        $this->assertCount(1, $result['tags']);
        $this->assertCount(1, $result['tag_stats']);
        $this->assertNotNull($result['selected_date_record']);
        $this->assertEquals('2026-07-10', Carbon::parse($result['selected_date_record']->recorded_at)->toDateString());
    }

    public function test_get_dashboard_data_finds_selected_date_record_outside_history_range(): void
    {
        $user = User::factory()->create();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-01-15', 'bodyWeight' => 70.0]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $service = new WeightService();

        $result = $service->getDashboardData(
            $user->id,
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
            Carbon::parse('2026-01-15')->startOfDay()
        );

        $this->assertCount(1, $result['records']);
        $this->assertNotNull($result['selected_date_record']);
        $this->assertEquals(70.0, $result['selected_date_record']->bodyWeight);
    }

    public function test_get_dashboard_data_returns_null_selected_date_record_when_not_recorded(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->getDashboardData(
            $user->id,
            Carbon::parse('2026-07-01')->startOfDay(),
            Carbon::parse('2026-07-31')->endOfDay(),
            Carbon::parse('2026-07-15')->startOfDay()
        );

        $this->assertCount(0, $result['records']);
        $this->assertNull($result['selected_date_record']);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`Call to undefined method App\Services\Weight\WeightService::getDashboardData()`)

- [ ] **Step 3: `getDashboardData()` を実装する**

`app/Services/Weight/WeightService.php` の `deleteTag()` メソッド(158-169行目)の直後、クラスの閉じ括弧(170行目)の前に追加する。

```php
    /**
     * 体重管理ページの初期表示に必要なデータ(履歴・タグ一覧・タグ統計・選択日レコード)をまとめて返す。
     * 選択日が履歴取得済みの範囲内であれば追加クエリなしでrecordsから探し、範囲外の場合のみ単日クエリを行う。
     *
     * @param int $userId
     * @param Carbon $from
     * @param Carbon $to
     * @param Carbon $selectedDate
     * @return array{records: Collection<int, RecordState>, target_weight: float|null, target_weight_date: string|null, tags: \Illuminate\Database\Eloquent\Collection<int, WeightTag>, tag_stats: array, selected_date_record: RecordState|null}
     */
    public function getDashboardData(int $userId, Carbon $from, Carbon $to, Carbon $selectedDate): array
    {
        $user = User::findOrFail($userId);
        $records = $this->getWeightHistory($userId, $from, $to);

        $selectedDateString = $selectedDate->toDateString();
        $selectedDateRecord = $records->first(
            fn ($record) => Carbon::parse($record->recorded_at)->toDateString() === $selectedDateString
        );

        if ($selectedDateRecord === null && ! $selectedDate->between($from, $to)) {
            $selectedDateRecord = $this->getWeightHistory(
                $userId,
                $selectedDate->copy()->startOfDay(),
                $selectedDate->copy()->endOfDay()
            )->first();
        }

        return [
            'records' => $records,
            'target_weight' => $user->target_weight,
            'target_weight_date' => $user->target_weight_date?->format('Y-m-d'),
            'tags' => $this->getAllTags($userId),
            'tag_stats' => $this->getTagStatistics($userId),
            'selected_date_record' => $selectedDateRecord,
        ];
    }
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(全テスト成功)

---

## Task 2: `GetWeightDashboardRequest` を作成する

**Files:**
- Create: `app/Http/Requests/Weight/GetWeightDashboardRequest.php`
- Create: `tests/Unit/Requests/Weight/GetWeightDashboardRequestTest.php`
- Delete: `app/Http/Requests/Weight/GetWeightHistoryRequest.php`
- Delete: `tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Unit/Requests/Weight/GetWeightDashboardRequestTest.php` を新規作成する。

```php
<?php

namespace Tests\Unit\Requests\Weight;

use App\Http\Requests\Weight\GetWeightDashboardRequest;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GetWeightDashboardRequestTest extends TestCase
{
    public function test_resolved_from_defaults_to_start_of_month_two_months_ago(): void
    {
        Carbon::setTestNow('2026-07-27');
        $request = new GetWeightDashboardRequest();

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-05-01', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_from_uses_provided_from_value(): void
    {
        $request = new GetWeightDashboardRequest();
        $request->merge(['from' => '2026-01-01']);

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-01-01', $result->toDateString());
    }

    public function test_resolved_to_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-07-27 15:00:00');
        $request = new GetWeightDashboardRequest();

        $result = $request->resolvedTo();

        $this->assertEquals('2026-07-27', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_selected_date_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-07-27 15:00:00');
        $request = new GetWeightDashboardRequest();

        $result = $request->resolvedSelectedDate();

        $this->assertEquals('2026-07-27', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_selected_date_uses_provided_value(): void
    {
        $request = new GetWeightDashboardRequest();
        $request->merge(['selected_date' => '2026-01-15']);

        $result = $request->resolvedSelectedDate();

        $this->assertEquals('2026-01-15', $result->toDateString());
    }
}
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Unit/Requests/Weight/GetWeightDashboardRequestTest.php`

Expected: FAIL(`Class "App\Http\Requests\Weight\GetWeightDashboardRequest" not found`)

- [ ] **Step 3: `GetWeightDashboardRequest` を実装する**

`app/Http/Requests/Weight/GetWeightDashboardRequest.php` を新規作成する。

```php
<?php

namespace App\Http\Requests\Weight;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class GetWeightDashboardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to' => 'nullable|date_format:Y-m-d|required_with:from|after_or_equal:from',
            'selected_date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function messages()
    {
        return [
            'from.required_with' => 'fromとtoは両方指定するか、両方省略してください。',
            'to.required_with' => 'fromとtoは両方指定するか、両方省略してください。',
            'from.date_format' => 'fromはYYYY-MM-DD形式で指定してください。',
            'to.date_format' => 'toはYYYY-MM-DD形式で指定してください。',
            'to.after_or_equal' => 'toはfrom以降の日付を指定してください。',
            'selected_date.date_format' => 'selected_dateはYYYY-MM-DD形式で指定してください。',
        ];
    }

    /**
     * 絞り込み開始日を返す。省略時は「当月を含む直近3ヶ月」の月初とする。
     *
     * @return Carbon
     */
    public function resolvedFrom(): Carbon
    {
        if ($this->filled('from')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay();
        }

        return Carbon::now()->subMonthsNoOverflow(2)->startOfMonth();
    }

    /**
     * 絞り込み終了日を返す。省略時は今日とする。
     *
     * @return Carbon
     */
    public function resolvedTo(): Carbon
    {
        if ($this->filled('to')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('to'))->endOfDay();
        }

        return Carbon::now()->endOfDay();
    }

    /**
     * 選択日を返す。省略時は今日とする。
     *
     * @return Carbon
     */
    public function resolvedSelectedDate(): Carbon
    {
        if ($this->filled('selected_date')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('selected_date'))->startOfDay();
        }

        return Carbon::now()->startOfDay();
    }
}
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Unit/Requests/Weight/GetWeightDashboardRequestTest.php`

Expected: PASS(全テスト成功)

- [ ] **Step 5: 旧`GetWeightHistoryRequest`とそのテストを削除する**

Task 3でコントローラーから参照を外した後に削除する(このステップではまだ削除しない。Task 3完了後に以下を実行):

```bash
rm "app/Http/Requests/Weight/GetWeightHistoryRequest.php"
rm "tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php"
```

---

## Task 3: `WeightController::dashboard()` を追加し旧エンドポイントを削除する

**Files:**
- Modify: `app/Http/Controllers/WeightController.php`
- Modify: `routes/api.php:48-53`
- Modify: `tests/Feature/WeightControllerTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/WeightControllerTest.php` の `test_index_returns_weight_history_and_target_weight`(70-83行目)、`test_tags_returns_all_weight_tags`(85-94行目)、`test_tag_stats_returns_statistics`(96-107行目)、`test_index_returns_target_weight_date`(143-153行目)の4メソッドを削除し、代わりに以下を追加する(挿入位置はどこでもよいが、削除した4メソッドがあった場所に置き換える形にする)。

```php
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
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: FAIL(`/api/weight/dashboard` が404を返す)

- [ ] **Step 3: `WeightController` を修正する**

`app/Http/Controllers/WeightController.php` を以下の内容に置き換える。

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Weight\GetWeightDashboardRequest;
use App\Http\Requests\Weight\StoreWeightRecordRequest;
use App\Http\Requests\Weight\StoreWeightTagRequest;
use App\Http\Requests\Weight\UpdateTargetWeightRequest;
use App\Services\Weight\WeightService;

class WeightController extends Controller
{
    public function store(StoreWeightRecordRequest $request, WeightService $weightService)
    {
        $recordState = $weightService->recordWeight(
            auth()->id(),
            $request->input('recorded_at'),
            $request->input('body_weight'),
            $request->input('memo'),
            $request->input('tag_ids', [])
        );

        return response()->json([
            'status_code' => 200,
            'message' => '体重記録を保存しました。',
            'record' => $recordState->load('weightTags'),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function dashboard(GetWeightDashboardRequest $request, WeightService $weightService)
    {
        $data = $weightService->getDashboardData(
            auth()->id(),
            $request->resolvedFrom(),
            $request->resolvedTo(),
            $request->resolvedSelectedDate()
        );

        return response()->json([
            'status_code' => 200,
            'records' => $data['records'],
            'target_weight' => $data['target_weight'],
            'target_weight_date' => $data['target_weight_date'],
            'tags' => $data['tags'],
            'tag_stats' => $data['tag_stats'],
            'selected_date_record' => $data['selected_date_record'],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function storeTag(StoreWeightTagRequest $request, WeightService $weightService)
    {
        $tag = $weightService->addTag(auth()->id(), $request->input('content'));

        if ($tag === null) {
            return response()->json([
                'status_code' => 422,
                'message' => 'タグは5個までです。',
            ], 422);
        }

        return response()->json([
            'status_code' => 200,
            'tag' => $tag,
        ]);
    }

    public function destroyTag(int $id, WeightService $weightService)
    {
        $weightService->deleteTag(auth()->id(), $id);

        return response()->json([
            'status_code' => 200,
            'message' => 'タグを削除しました。',
        ]);
    }

    public function updateTargetWeight(UpdateTargetWeightRequest $request, WeightService $weightService)
    {
        $user = $weightService->updateTargetWeight(
            auth()->id(),
            $request->input('target_weight'),
            $request->input('target_weight_date')
        );

        return response()->json([
            'status_code' => 200,
            'target_weight' => $user->target_weight,
            'target_weight_date' => $user->target_weight_date?->format('Y-m-d'),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
```

- [ ] **Step 4: ルートを修正する**

`routes/api.php:48-53` を以下のように置き換える(変更前後):

変更前:
```php
    Route::get('/weight', [WeightController::class, 'index']);
    Route::post('/weight', [WeightController::class, 'store']);
    Route::get('/weight/tags', [WeightController::class, 'tags']);
    Route::get('/weight/tagStats', [WeightController::class, 'tagStats']);
    Route::post('/weight/targetWeight', [WeightController::class, 'updateTargetWeight']);
    Route::post('/weight/tags', [WeightController::class, 'storeTag']);
```

変更後:
```php
    Route::post('/weight', [WeightController::class, 'store']);
    Route::get('/weight/dashboard', [WeightController::class, 'dashboard']);
    Route::post('/weight/targetWeight', [WeightController::class, 'updateTargetWeight']);
    Route::post('/weight/tags', [WeightController::class, 'storeTag']);
```

(直後の `Route::delete('/weight/tags/{id}', [WeightController::class, 'destroyTag']);` はそのまま残す)

- [ ] **Step 5: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: PASS(全テスト成功)

- [ ] **Step 6: Task 2で保留にした旧ファイルを削除する**

```bash
rm "app/Http/Requests/Weight/GetWeightHistoryRequest.php"
rm "tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php"
```

- [ ] **Step 7: バックエンドの全テストを実行し既存機能に影響がないことを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test`

Expected: PASS(全テスト成功。既存の`WeightMigrationsTest.php`・`RecordStateWeightTagTest.php`等も含めて影響なし)

---

## Task 4: フロントエンドの型定義を更新する

**Files:**
- Modify: `resources/js/types/weight.d.ts`

- [ ] **Step 1: 型定義を書き換える**

`resources/js/types/weight.d.ts` の内容全体を以下に置き換える。

```typescript
export declare type WeightTag = {
    id: number,
    content: string,
};

export declare type WeightRecord = {
    id: number,
    recorded_at: string,
    bodyWeight: number | null,
    weight_memo: string | null,
    weight_tags: WeightTag[],
};

export declare type TagStatistic = {
    tag: string,
    average_diff: number,
    sample_count: number,
};

export declare type WeightDashboardResponse = {
    status_code: number,
    records: WeightRecord[],
    target_weight: number | null,
    target_weight_date: string | null,
    tags: WeightTag[],
    tag_stats: TagStatistic[],
    selected_date_record: WeightRecord | null,
};
```

- [ ] **Step 2: 型チェックを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: `weightManagement.vue`でまだ`useGetWeightHistory`/`useGetWeightTags`を参照しているため、この時点ではエラーが出ない(Task 4は型定義ファイル単体の変更で、既存の参照側はTask 5・6で書き換えるため)。もしこの時点で`WeightHistoryResponse`等の削除済み型を参照しているファイルがあればエラーになるので確認する。

---

## Task 5: `useGetWeightDashboard` コンポーザブルを作成する

**Files:**
- Create: `resources/js/composables/weight/useGetWeightDashboard.ts`
- Delete: `resources/js/composables/weight/useGetWeightHistory.ts`
- Delete: `resources/js/composables/weight/useGetWeightTags.ts`

- [ ] **Step 1: コンポーザブルを実装する**

`resources/js/composables/weight/useGetWeightDashboard.ts` を新規作成する。

```typescript
import { ref, Ref } from "vue";
import axios from "axios";
import { WeightRecord, WeightTag, TagStatistic } from "../../types/weight";

export default function useGetWeightDashboard() {
  const weightRecords: Ref<WeightRecord[]> = ref([]);
  const targetWeight: Ref<number | null> = ref(null);
  const targetWeightDate: Ref<string | null> = ref(null);
  const weightTags: Ref<WeightTag[]> = ref([]);
  const tagStats: Ref<TagStatistic[]> = ref([]);
  const selectedDateRecord: Ref<WeightRecord | null> = ref(null);

  const getWeightDashboard = async (
    from: string,
    to: string,
    selectedDate: string
  ): Promise<void> => {
    await axios
      .get("/api/weight/dashboard", {
        params: { from, to, selected_date: selectedDate },
      })
      .then((res) => {
        weightRecords.value = res.data.records;
        targetWeight.value = res.data.target_weight;
        targetWeightDate.value = res.data.target_weight_date;
        weightTags.value = res.data.tags;
        tagStats.value = res.data.tag_stats;
        selectedDateRecord.value = res.data.selected_date_record;
      })
      .catch(() => {
        weightRecords.value = [];
        targetWeight.value = null;
        targetWeightDate.value = null;
        weightTags.value = [];
        tagStats.value = [];
        selectedDateRecord.value = null;
      });
  };

  return {
    weightRecords,
    targetWeight,
    targetWeightDate,
    weightTags,
    tagStats,
    selectedDateRecord,
    getWeightDashboard,
  };
}
```

- [ ] **Step 2: 旧コンポーザブルを削除する**

```bash
rm "src/resources/js/composables/weight/useGetWeightHistory.ts"
rm "src/resources/js/composables/weight/useGetWeightTags.ts"
```

(この時点で`weightManagement.vue`がまだこの2ファイルをimportしているため、Task 6を完了するまでビルド/型チェックはエラーになる。Task 6で直ちに解消する)

---

## Task 6: `weightManagement.vue` を新コンポーザブルに統合する

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`
- Modify: `resources/js/components/weight/WeightRecordForm.vue`(Task 5実装中に判明した追加対応。理由は本ファイル冒頭の「【計画修正】」を参照)

- [ ] **Step 1: script部分を書き換える**

`resources/js/views/weight/weightManagement.vue` の `<script setup>` ブロック全体を以下に置き換える(テンプレート部分は変更しない)。

```typescript
import { computed, ComputedRef, onMounted, ref, Ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import dayjs from "dayjs";
import useGetWeightDashboard from "../../composables/weight/useGetWeightDashboard";
import useGetLoginUser from "../../composables/certification/useGetLoginUser";
import userSessionStorage from "../../utils/userSessionStorage";
import LoadingSpinner from "../../components/common/LoadingSpinner.vue";
import WeightChart from "../../components/weight/WeightChart.vue";
import WeightTagStats from "../../components/weight/WeightTagStats.vue";
import WeightRecordForm from "../../components/weight/WeightRecordForm.vue";
import WeightRecordModal from "../../components/weight/WeightRecordModal.vue";
import WeightTargetSetting from "../../components/weight/WeightTargetSetting.vue";
import WeightTagEditor from "../../components/weight/WeightTagEditor.vue";
import { WeightRecord } from "../../types/weight";
import { setSeo } from "../../utils/setSeo";

setSeo("weight");

const router = useRouter();
const store = useStore();

const { getLoginUser, loginUser } = useGetLoginUser();
const { getSessionLoginUser } = userSessionStorage();
const dispModal: ComputedRef<boolean> = computed(() => store.getters.dispAlertModal);
const dispAlertModal = ref<boolean>(false);

const toHome = (): void => {
  window.location.href = "/";
};

const toLogin = (): void => {
  router.push("/login");
};

const today = dayjs().format("YYYY-MM-DD");

const selectedDate: Ref<string> = ref(today);
const tagsVersion: Ref<number> = ref(0);

const {
  weightRecords,
  targetWeight,
  targetWeightDate,
  weightTags,
  tagStats,
  selectedDateRecord,
  getWeightDashboard,
} = useGetWeightDashboard();

const periodOptions = [
  { label: "1ヶ月", months: 1 },
  { label: "3ヶ月", months: 3 },
  { label: "6ヶ月", months: 6 },
];
const PERIOD_STORAGE_KEY = "weightManagement.selectedMonths";

const getInitialSelectedMonths = (): number => {
  const stored = localStorage.getItem(PERIOD_STORAGE_KEY);
  const parsed = stored ? Number(stored) : NaN;
  return [1, 3, 6].includes(parsed) ? parsed : 1;
};

const selectedMonths: Ref<number> = ref(getInitialSelectedMonths());
const isLoading: Ref<boolean> = ref(true);

const showModal: Ref<boolean> = ref(false);
const selectedRecord: Ref<WeightRecord | null> = ref(null);

const openRecordModal = (record: WeightRecord): void => {
  selectedRecord.value = record;
  showModal.value = true;
};

const latestBodyWeight: ComputedRef<number | null> = computed(() => {
  if (weightRecords.value.length === 0) {
    return null;
  }
  return weightRecords.value[weightRecords.value.length - 1].bodyWeight;
});

const onTargetWeightUpdated = (value: { targetWeight: number; targetWeightDate: string | null }): void => {
  targetWeight.value = value.targetWeight;
  targetWeightDate.value = value.targetWeightDate;
};

const fetchDashboard = async (): Promise<void> => {
  const from = dayjs().subtract(selectedMonths.value, "month").format("YYYY-MM-DD");
  const to = dayjs().format("YYYY-MM-DD");
  await getWeightDashboard(from, to, selectedDate.value);
};

const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  localStorage.setItem(PERIOD_STORAGE_KEY, String(months));
  await fetchDashboard();
};

const onSaved = async (): Promise<void> => {
  await fetchDashboard();
};

const onTagsChanged = async (): Promise<void> => {
  await fetchDashboard();
  tagsVersion.value++;
};

watch(selectedDate, async () => {
  await fetchDashboard();
});

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
  try {
    await fetchDashboard();
  } finally {
    isLoading.value = false;
  }
});
```

- [ ] **Step 2: テンプレートで`WeightRecordForm`に`weightTags`をpropsで渡す**

`weightManagement.vue`のテンプレート内、`<WeightRecordForm>`タグ(既存では14-21行目付近)を以下のように書き換える(`:weightTags="weightTags"`を追加する点のみが変更点)。

変更前:
```vue
          <WeightRecordForm
            :key="`${selectedDate}-${tagsVersion}`"
            :recordedAt="selectedDate"
            :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
            :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
            :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
            @saved="onSaved"
          />
```

変更後:
```vue
          <WeightRecordForm
            :key="`${selectedDate}-${tagsVersion}`"
            :recordedAt="selectedDate"
            :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
            :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
            :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
            :weightTags="weightTags"
            @saved="onSaved"
          />
```

- [ ] **Step 3: `WeightRecordForm.vue`を修正し、独自の`useGetWeightTags`呼び出しをpropsに置き換える**

`WeightRecordForm.vue`は現在、内部で独自に`useGetWeightTags()`を呼んで`GET /api/weight/tags`を叩いている(`onMounted`内)。これは`weightManagement.vue`が既に`useGetWeightDashboard`経由で取得済みの`weightTags`と重複するAPI呼び出しなので、propsで受け取る形に変更する。

`resources/js/components/weight/WeightRecordForm.vue`の`<script setup>`ブロックを以下に置き換える(テンプレートは`v-for="tag in weightTags"`を`v-for="tag in props.weightTags"`に変更する1箇所のみ変更する)。

テンプレート内の変更(15行目付近):

変更前:
```vue
        <button
          v-for="tag in weightTags"
```

変更後:
```vue
        <button
          v-for="tag in props.weightTags"
```

`<script setup>`ブロック全体を以下に置き換える:

```typescript
import { ref, Ref } from "vue";
import usePostWeightRecord from "../../composables/weight/usePostWeightRecord";
import { WeightTag } from "../../types/weight";

const props = defineProps<{
  recordedAt: string;
  initialBodyWeight?: number | null;
  initialMemo?: string | null;
  initialTagIds?: number[];
  weightTags: WeightTag[];
}>();

const emits = defineEmits<{
  (e: "saved"): void;
}>();

const { isSaving, postWeightRecord } = usePostWeightRecord();

const bodyWeightInput: Ref<string> = ref(
  props.initialBodyWeight != null ? props.initialBodyWeight.toString() : ""
);
const memoInput: Ref<string> = ref(props.initialMemo ?? "");
const selectedTagIds: Ref<number[]> = ref(props.initialTagIds ? [...props.initialTagIds] : []);

const MEMO_EXPANDED_STORAGE_KEY = "weightRecordForm.memoExpanded";

const getInitialMemoExpanded = (): boolean => {
  const stored = localStorage.getItem(MEMO_EXPANDED_STORAGE_KEY);
  if (stored !== null) {
    return stored === "true";
  }
  return window.innerWidth >= 768;
};

const isMemoExpanded: Ref<boolean> = ref(getInitialMemoExpanded());

const toggleMemo = (): void => {
  isMemoExpanded.value = !isMemoExpanded.value;
  localStorage.setItem(MEMO_EXPANDED_STORAGE_KEY, String(isMemoExpanded.value));
};

const toggleTag = (tagId: number): void => {
  const index = selectedTagIds.value.indexOf(tagId);
  if (index === -1) {
    selectedTagIds.value.push(tagId);
  } else {
    selectedTagIds.value.splice(index, 1);
  }
};

const submit = async () => {
  const bodyWeight = bodyWeightInput.value !== "" ? parseFloat(bodyWeightInput.value) : null;
  await postWeightRecord(props.recordedAt, bodyWeight, memoInput.value || null, selectedTagIds.value);
  emits("saved");
};
```

(`onMounted`と`useGetWeightTags`のimport、および`getWeightTags`呼び出しを削除している点に注意。`weightTags`はローカルの`ref`ではなく`props.weightTags`をそのまま使うため、分割代入していた`const { weightTags, getWeightTags } = useGetWeightTags();`の行ごと削除する。)

- [ ] **Step 4: 型チェックを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: PASS(エラーなし)

- [ ] **Step 5: ビルドを実行する**

Run: `cd src && npm run build`

Expected: PASS(ビルド成功)

---

## Task 7: ブラウザでの動作確認

**Files:** なし(手動確認)

- [ ] **Step 1: バックエンドの全テストを再実行する**

Run: `docker exec trainingmemoapp-app-1 php artisan test`

Expected: PASS(全テスト成功)

- [ ] **Step 2: `chrome-screen-check`スキル、またはClaude in Chromeで以下を確認する**

1. ログイン状態で `/weight` にアクセスし、Networkタブで `/api/weight/dashboard` が1回だけ呼ばれ、`/api/weight`・`/api/weight/tags`・`/api/weight/tagStats` への個別リクエストが発生していないことを確認する
2. 体重・メモ・タグを入力して保存し、保存後にグラフ・タグ統計・タグ一覧が正しく更新されることを確認する
3. 期間(1ヶ月/3ヶ月/6ヶ月)を切り替えてグラフが更新されることを確認する
4. 日付入力欄で別の日付を選択し、その日の記録(または未記録なら空)が正しくフォームに反映されることを確認する
5. タグを追加・削除し、タグ統計とタグ一覧が正しく更新されることを確認する
6. ログアウト状態(またはセッション切れ状態)で `/weight` にアクセスし、「画面表示するにはログインしてください。」モーダルが表示され、閉じるとホームへリダイレクトされることを確認する(Task範囲外だが、前回修正した認証リダイレクトの回帰がないことも合わせて確認する)

---

## 最終コミット

すべてのタスク完了後、以下を実行する。

```bash
git add -A
git commit -m "$(cat <<'EOF'
refactor: 体重管理ページのAPI呼び出しをdashboardエンドポイントに統合

初期表示・保存後・期間変更・日付変更のたびに直列実行していた4本のAPI呼び出し
(履歴・タグ統計・選択日レコード・タグ一覧)を1本のGET /api/weight/dashboardに
統合し、リクエスト往復回数を削減する。

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

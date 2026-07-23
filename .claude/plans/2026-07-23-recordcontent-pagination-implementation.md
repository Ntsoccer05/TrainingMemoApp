# RecordContent/Record 全件取得解消 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `RecordController::index`と`RecordContentController::index`(ホーム画面ブランチ)の「無制限の全件取得」を解消し、Service層への切り出し・日付範囲フィルタ・カレンダーの月単位フェッチを実装する。

**Architecture:** Controller→FormRequest→Service→Modelのレイヤードアーキテクチャ(`.claude/rules/backend-architecture.md`準拠)。`RecordService`(最新レコード取得)と`RecordContentService`(範囲指定取得)を新設。フロントエンドは`Calendar.vue`を月単位フェッチ+蓄積方式に変更する。

**Tech Stack:** Laravel 9 / PHPUnit / Vue 3 + TypeScript / v-calendar v3

**設計書:** `docs/plans/2026-07-23-recordcontent-pagination-design.md`

---

## ⚠️ Task 1 を必ず最初に実行すること

このプロジェクトは過去に、テストDBが開発DBから分離されておらず`php artisan test`実行で開発DBのデータ(シード済みテストアカウント・パフォーマンス検証用に投入した約100万件のデータ)を消してしまった事故がある。Task 1(テストDB分離)を完了し、検証手順で「開発DBが無事であること」を確認してから、Task 3以降のDBを使うテストを実行すること。

### Task 1: テストDBを開発DBから分離する

**Files:**
- Modify: `src/tests/CreatesApplication.php`

- [ ] **Step 1: 開発DBに目印(sentinel)レコードを作成する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="App\Models\User::updateOrCreate(['email'=>'sentinel-task1@example.com'],['name'=>'sentinel','password'=>bcrypt('password')]); echo 'created';"
```
Expected: `created` が出力される

- [ ] **Step 2: テスト用データベースを作成する**

Run:
```bash
docker exec db mysql -uroot -ptrainingroot -e "CREATE DATABASE IF NOT EXISTS trainingmemo_test; GRANT ALL PRIVILEGES ON trainingmemo_test.* TO 'training0512'@'%'; FLUSH PRIVILEGES;"
```
Expected: エラーなく終了する(パスワード`trainingroot`はルートの`.env`の`DB_ROOT_PASSWORD`、ユーザー名`training0512`は`DB_USER`)

- [ ] **Step 3: `CreatesApplication.php`を修正してテスト用DBへ切り替える**

`src/tests/CreatesApplication.php`を以下に置き換える:

```php
<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // docker-compose.ymlのappサービスがDB_DATABASE等をコンテナの実環境変数として
        // 注入しているため、.env.testingやphpunit.xmlの<env>では上書きできない。
        // ここで直接テスト用DBに切り替えることで、テストが開発用DBに接続するのを防ぐ。
        config(['database.connections.mysql.database' => 'trainingmemo_test']);

        return $app;
    }
}
```

- [ ] **Step 4: 何もテストが無い状態でマイグレーションが分離DBに対して走ることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test --filter=ExampleTest
```
Expected: `Tests: 2 passed`

- [ ] **Step 5: 開発DBのsentinelレコードが生き残っていることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="echo App\Models\User::where('email','sentinel-task1@example.com')->exists() ? 'OK: 残っている' : 'NG: 消えた!';"
```
Expected: `OK: 残っている` が出力される。`NG`が出た場合は絶対に先に進まず、Task 1のStep 3の設定を見直すこと。

- [ ] **Step 6: sentinelレコードを削除する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="App\Models\User::where('email','sentinel-task1@example.com')->delete(); echo 'removed';"
```
Expected: `removed` が出力される

---

## Task 2: `record_states`テーブルへの複合インデックス追加

**Files:**
- Create: `src/database/migrations/2026_07_23_000002_add_user_recorded_at_index_to_record_states_table.php`

- [ ] **Step 1: マイグレーションファイルを作成する**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RecordContentService::getRecordsInRange() の
     * WHERE user_id = ? AND recorded_at BETWEEN ? AND ? を高速化するための複合インデックス。
     *
     * @return void
     */
    public function up()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->index(['user_id', 'recorded_at'], 'idx_record_states_user_recorded_at');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->dropIndex('idx_record_states_user_recorded_at');
        });
    }
};
```

- [ ] **Step 2: 開発DBにマイグレーションを適用する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan migrate --path=database/migrations/2026_07_23_000002_add_user_recorded_at_index_to_record_states_table.php --force
```
Expected: `DONE` と表示される

- [ ] **Step 3: インデックスが作成されたことを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="foreach (DB::select('SHOW INDEX FROM record_states') as \$r) { echo \$r->Key_name.' '.\$r->Column_name.PHP_EOL; }"
```
Expected: `idx_record_states_user_recorded_at user_id` と `idx_record_states_user_recorded_at recorded_at` の行が含まれる

---

## Task 3: `RecordService`の作成(TDD)

**Files:**
- Create: `src/app/Services/Record/RecordService.php`
- Test: `src/tests/Feature/Services/Record/RecordServiceTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Feature/Services/Record/RecordServiceTest.php`を作成:

```php
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
}
```

- [ ] **Step 2: テストを実行し失敗することを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/Services/Record/RecordServiceTest.php
```
Expected: FAIL(`Class "App\Services\Record\RecordService" not found`)

- [ ] **Step 3: `RecordService`を実装する**

`src/app/Services/Record/RecordService.php`を作成:

```php
<?php

namespace App\Services\Record;

use App\Models\RecordState;

class RecordService
{
    /**
     * ユーザーの最新のRecordStateを返す。
     * 「作成日時が最新のレコード」と「更新日時が最新のレコード」を比較し、
     * より新しい方を返す(全件取得はしない)。
     *
     * @param int $userId
     * @return RecordState|null
     */
    public function getLatestRecordState(int $userId): ?RecordState
    {
        $latestCreated = RecordState::where('user_id', $userId)
            ->latest('created_at')
            ->first();

        if (is_null($latestCreated)) {
            return null;
        }

        $latestUpdated = RecordState::where('user_id', $userId)
            ->whereNotNull('updated_at')
            ->latest('updated_at')
            ->first();

        if (! is_null($latestUpdated) && $latestUpdated->updated_at->gt($latestCreated->created_at)) {
            return $latestUpdated;
        }

        return $latestCreated;
    }
}
```

- [ ] **Step 4: テストを実行しパスすることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/Services/Record/RecordServiceTest.php
```
Expected: `Tests: 4 passed`

---

## Task 4: `RecordController::index`を`RecordService`経由に書き換え

**Files:**
- Modify: `src/app/Http/Controllers/RecordController.php:17-39`(`index`メソッドのみ)
- Test: `src/tests/Feature/RecordControllerTest.php`

現状の`index`メソッドは`user_id`によるスコープが無く、全ユーザー中で最新のレコードを返してしまうバグがある。今回`RecordService`を経由する形に書き換える際に、認証済みユーザー(`auth()->id()`)でスコープするよう修正する。

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Feature/RecordControllerTest.php`を作成:

```php
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

        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => now()->toDateString()]);
        $ownRecord = RecordState::create(['user_id' => $user->id, 'recorded_at' => now()->subDay()->toDateString()]);

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
```

- [ ] **Step 2: テストを実行し失敗することを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/RecordControllerTest.php
```
Expected: FAIL(`latestRecord.id`が別ユーザーのレコードのIDになっている、または`isSetUpdated`等が含まれている)

- [ ] **Step 3: `RecordController::index`を書き換える**

`src/app/Http/Controllers/RecordController.php`の`index`メソッド(17〜39行目)を以下に置き換える(他のメソッド・importは変更しない):

```php
    public function index(\App\Services\Record\RecordService $recordService){
        $latestRecord = $recordService->getLatestRecordState(auth()->id());

        return response()->json(["status_code" => 200, "latestRecord" => $latestRecord]);
    }
```

- [ ] **Step 4: テストを実行しパスすることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/RecordControllerTest.php
```
Expected: `Tests: 2 passed`

---

## Task 5: `GetRecordContentsRequest`の作成(TDD)

**Files:**
- Create: `src/app/Http/Requests/RecordContent/GetRecordContentsRequest.php`
- Test: `src/tests/Unit/Requests/RecordContent/GetRecordContentsRequestTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Unit/Requests/RecordContent/GetRecordContentsRequestTest.php`を作成:

```php
<?php

namespace Tests\Unit\Requests\RecordContent;

use App\Http\Requests\RecordContent\GetRecordContentsRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class GetRecordContentsRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new GetRecordContentsRequest())->rules();
    }

    public function test_passes_when_both_from_and_to_are_omitted()
    {
        $validator = Validator::make([], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_passes_when_both_from_and_to_are_valid()
    {
        $validator = Validator::make(['from' => '2026-01-01', 'to' => '2026-01-31'], $this->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_fails_when_only_from_is_present()
    {
        $validator = Validator::make(['from' => '2026-01-01'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_fails_when_only_to_is_present()
    {
        $validator = Validator::make(['to' => '2026-01-31'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_fails_when_to_is_before_from()
    {
        $validator = Validator::make(['from' => '2026-02-01', 'to' => '2026-01-01'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_fails_when_date_format_is_invalid()
    {
        $validator = Validator::make(['from' => '2026/01/01', 'to' => '2026/01/31'], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_resolved_from_defaults_to_two_months_before_start_of_month_when_omitted()
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 23));
        $request = new GetRecordContentsRequest();

        $this->assertTrue($request->resolvedFrom()->isSameDay(Carbon::create(2026, 5, 1)));

        Carbon::setTestNow();
    }

    public function test_resolved_to_defaults_to_today_when_omitted()
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 23));
        $request = new GetRecordContentsRequest();

        $this->assertTrue($request->resolvedTo()->isSameDay(Carbon::create(2026, 7, 23)));

        Carbon::setTestNow();
    }

    public function test_resolved_from_uses_given_from_when_present()
    {
        $request = new GetRecordContentsRequest();
        $request->merge(['from' => '2026-01-15', 'to' => '2026-01-20']);

        $this->assertTrue($request->resolvedFrom()->isSameDay(Carbon::create(2026, 1, 15)));
        $this->assertTrue($request->resolvedTo()->isSameDay(Carbon::create(2026, 1, 20)));
    }
}
```

- [ ] **Step 2: テストを実行し失敗することを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Unit/Requests/RecordContent/GetRecordContentsRequestTest.php
```
Expected: FAIL(`Class "App\Http\Requests\RecordContent\GetRecordContentsRequest" not found`)

- [ ] **Step 3: `GetRecordContentsRequest`を実装する**

`src/app/Http/Requests/RecordContent/GetRecordContentsRequest.php`を作成:

```php
<?php

namespace App\Http\Requests\RecordContent;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class GetRecordContentsRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * fromとtoは両方指定するか、両方省略するかのどちらか。
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to' => 'nullable|date_format:Y-m-d|required_with:from|after_or_equal:from',
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
}
```

- [ ] **Step 4: テストを実行しパスすることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Unit/Requests/RecordContent/GetRecordContentsRequestTest.php
```
Expected: `Tests: 9 passed`

---

## Task 6: `RecordContentService`の作成(TDD)

**Files:**
- Create: `src/app/Services/RecordContent/RecordContentService.php`
- Test: `src/tests/Feature/Services/RecordContent/RecordContentServiceTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Feature/Services/RecordContent/RecordContentServiceTest.php`を作成:

```php
<?php

namespace Tests\Feature\Services\RecordContent;

use App\Models\RecordState;
use App\Models\User;
use App\Services\RecordContent\RecordContentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
```

- [ ] **Step 2: テストを実行し失敗することを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/Services/RecordContent/RecordContentServiceTest.php
```
Expected: FAIL(`Class "App\Services\RecordContent\RecordContentService" not found`)

- [ ] **Step 3: `RecordContentService`を実装する**

`src/app/Services/RecordContent/RecordContentService.php`を作成:

```php
<?php

namespace App\Services\RecordContent;

use App\Models\RecordState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RecordContentService
{
    /**
     * ホーム画面のカレンダー表示用に、指定期間内のRecordStateを
     * 関連するrecordMenus/menu/categoryごとEager Loadして取得する。
     *
     * @param int $userId
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection<int, RecordState>
     */
    public function getRecordsInRange(int $userId, Carbon $from, Carbon $to): Collection
    {
        return RecordState::where('user_id', $userId)
            ->whereBetween('recorded_at', [$from, $to])
            ->with(['recordMenus.menu', 'recordMenus.category'])
            ->get();
    }
}
```

- [ ] **Step 4: テストを実行しパスすることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/Services/RecordContent/RecordContentServiceTest.php
```
Expected: `Tests: 3 passed`

---

## Task 7: `RecordContentController::index`をService経由に書き換え

**Files:**
- Modify: `src/app/Http/Controllers/RecordContentController.php:1-72`
- Test: `src/tests/Feature/RecordContentControllerTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`src/tests/Feature/RecordContentControllerTest.php`を作成:

```php
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
        RecordMenu::create(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $inRange->id, 'recorded_at' => '2026-06-01']);

        $outOfRange = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-01-01']);
        RecordMenu::create(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $outOfRange->id, 'recorded_at' => '2026-01-01']);

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
        RecordMenu::create(['user_id' => $user->id, 'category_id' => $category->id, 'menu_id' => $menu->id, 'record_state_id' => $target->id, 'recorded_at' => '2025-03-15']);

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
}
```

- [ ] **Step 2: テストを実行し失敗することを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/RecordContentControllerTest.php
```
Expected: FAIL(現状は全件取得のため範囲外レコードも含まれてしまう、または`from`単独指定でも422にならない)

- [ ] **Step 3: `RecordContentController`を書き換える**

`src/app/Http/Controllers/RecordContentController.php`の先頭(1〜13行目、クラス定義とuse文・`index`メソッドの宣言部分)を以下に置き換える:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordContent\GetRecordContentsRequest;
use App\Models\RecordMenu;
use App\Models\RecordContent;
use App\Models\RecordState;
use App\Services\RecordContent\RecordContentService;
use Illuminate\Http\Request;

class RecordContentController extends Controller
{
    public function index(GetRecordContentsRequest $request, RecordMenu $recordMenu, RecordState $recordState, RecordContentService $recordContentService){
        $tgtRecords = Null;

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
```

この後、既存の`foreach($tgtRecordMenu as $recordMenuContent){`ブロックの中身(現行ファイルの該当箇所、`$menuContent = $recordMenuContent->menu->content;`から`$recordContents[] = $recordContent;`と`return response()->json(...)`まで)は変更しない。既存の「メニュー選択画面にて記録済みメニューをマーキング」ブランチ以降(`if(isset($recorded_at)){`から末尾まで)も変更しない。

- [ ] **Step 4: テストを実行しパスすることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test tests/Feature/RecordContentControllerTest.php
```
Expected: `Tests: 3 passed`

- [ ] **Step 5: 既存のFeature/Unitテストが壊れていないことを確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test
```
Expected: 追加した分も含め全てPASS

---

## Task 8: フロントエンド `useGetRecords.ts` に `from`/`to` を追加

**Files:**
- Modify: `src/resources/js/composables/record/useGetRecords.ts`

- [ ] **Step 1: `getRecords`の引数と`params`を変更する**

`src/resources/js/composables/record/useGetRecords.ts`の36〜55行目を以下に置き換える:

```typescript
    const getRecords = async (
        user_id: Number,
        recorded_at: String = "",
        from?: String,
        to?: String
    ) => {
        await axios
            .get("/api/recordContent", {
                // get時にパラメータを渡す際はparamsで指定が必要
                params: {
                    // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                    user_id,
                    recorded_at,
                    from,
                    to,
                },
            })
            .then((res: AxiosResponse<Data>) => {
                records.value = res.data.records;
                compGetData.value = true;
                isLoaded.value = true;
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
                isLoaded.value = true;
            });
    };
```

- [ ] **Step 2: 型チェックが通ることを確認する**

Run:
```bash
docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html
```
Expected: エラーなく終了する(既存の型エラーが元々ある場合は、`useGetRecords.ts`に関するエラーが増えていないことを確認する)

---

## Task 9: フロントエンド `Calendar.vue` の月単位フェッチ実装

**Files:**
- Modify: `src/resources/js/components/record/Calendar.vue`

- [ ] **Step 1: 蓄積用stateとヘルパー関数を追加する**

`Calendar.vue`の75〜81行目(`// 当日をハイライト`から`let data = reactive<Data>({});`まで)を以下に置き換える:

```typescript
// 当日をハイライト・選択日ハイライトなど、記録データ以外の属性
const extraAttrs = ref<(Attrs | Obj)[]>([
  { key: "today", highlight: true, dates: new Date() },
]);

const holidays = ref<string[]>([]);
let data = reactive<Data>({});

// 取得済みレコードの蓄積(月をまたいで追加取得した結果をマージしていく)
const allRecords = ref<DispRecords[]>([]);
// 取得済み月("YYYY-MM")を記録し、同じ月を二重に取得しないようにする
const fetchedMonths = new Set<string>();

const monthKey = (year: number, month: number): string =>
  `${year}-${String(month).padStart(2, "0")}`;

const pad2 = (n: number): string => String(n).padStart(2, "0");
```

型`DispRecords`は`useGetRecords`の戻り値`records`と同じ型のため、ファイル先頭のimportに追加する:

`Calendar.vue`の9行目(`import useGetRecords from "../../composables/record/useGetRecords";`)の直後に以下を追加:

```typescript
import { DispRecords } from "../../types/record";
```

- [ ] **Step 2: `attrs`をcomputedに変更する**

`Calendar.vue`の76〜78行目(旧`const attrs = ref<(Attrs | Event | Obj)[]>([{ key: "today", highlight: true, dates: new Date() },]);`、Step 1で`extraAttrs`に置き換え済みの箇所の少し後、`const dispAlertModal = ref(false);`より前)に、`recordEvents`と`attrs`のcomputedを追加する。具体的には、`const dispAlertModal = ref(false);`の直前に挿入:

```typescript
const recordEvents = computed<Event[]>(() => {
  return allRecords.value.map((record) => {
    const label =
      record.menu !== undefined ? record.menu[0].menu_content : "記録がありません";
    return {
      popover: {
        label: label,
        visibility: "click",
        autoHide: false,
      },
      bar: {
        style: {
          backgroundColor: "red",
        },
      },
      dates: new Date(record.recorded_at.recorded_at.replace(/-/g, "/") as string),
    };
  });
});

const attrs = computed<(Attrs | Event | Obj)[]>(() => [
  ...extraAttrs.value,
  ...recordEvents.value,
]);
```

- [ ] **Step 3: 旧`watch(records, ...)`を「蓄積へのマージのみ」に変更する**

`Calendar.vue`の108〜132行目(`watch(records, () => { ... });`ブロック全体)を以下に置き換える:

```typescript
watch(records, () => {
  const existingIds = new Set(allRecords.value.map((r) => r.recorded_at.record_id));
  const toAdd = records.value.filter((r) => !existingIds.has(r.recorded_at.record_id));
  if (toAdd.length > 0) {
    allRecords.value = [...allRecords.value, ...toAdd];
  }
});
```

- [ ] **Step 4: `watch(holidays.value, ...)`を`extraAttrs`ベースに変更する**

`Calendar.vue`の134〜145行目(`watch(holidays.value, () => { ... });`ブロック全体)を以下に置き換える:

```typescript
watch(holidays.value, () => {
  const holidayObjs: Obj[] = holidays.value.map((holiday) => ({
    dot: true,
    // Text styles
    content: "red",
    // safariだと年-月-日だとNanとなるため年/月/日に変更
    dates: new Date(holiday.replace(/-/g, "/") as string),
  }));
  extraAttrs.value = [...extraAttrs.value, ...holidayObjs];
});
```

- [ ] **Step 5: `menuScroll`内の`attrs.value`への直接pushを`extraAttrs`に変更する**

`Calendar.vue`の183〜189行目(`menuScroll`関数内の`const obj = {...}; attrs.value = [...attrs.value, obj];`部分)を以下に置き換える:

```typescript
    const obj = {
      key: "selected_day",
      highlight: "green",
      // safariだと年-月-日だとNanとなるため年/月/日に変更
      dates: new Date((fromPath.value as string).replace(/-/g, "/")),
    };
    extraAttrs.value = [...extraAttrs.value, obj];
```

- [ ] **Step 6: 月範囲計算のヘルパーと`onPageChange`を追加する**

`Calendar.vue`の`menuScroll`関数の直後(`onMounted`の直前)に以下を追加:

```typescript
// year/monthからその月の月初・月末の"YYYY-MM-DD"文字列を計算する
const monthRange = (year: number, month: number): { from: string; to: string } => {
  const from = `${year}-${pad2(month)}-01`;
  const lastDay = new Date(year, month, 0).getDate();
  const to = `${year}-${pad2(month)}-${pad2(lastDay)}`;
  return { from, to };
};

// v-calendarの月移動を検知し、未取得の月であれば追加取得する
const onPageChange = async (page: { month: number; year: number }) => {
  const key = monthKey(page.year, page.month);
  if (fetchedMonths.has(key)) {
    return;
  }
  fetchedMonths.add(key);
  const { from, to } = monthRange(page.year, page.month);
  await getRecords(loginUser.value.id || 0, "", from, to);
};
```

- [ ] **Step 7: `onMounted`内の初期取得を「直近3ヶ月」に変更する**

`Calendar.vue`の197〜231行目の`onMounted`内、以下の部分:

```typescript
  if (loginUser.value.id) {
    await getRecords(loginUser.value.id);
  } else {
    await getRecords(0);
  }
```

を以下に置き換える:

```typescript
  const today = new Date();
  const startMonth = new Date(today.getFullYear(), today.getMonth() - 2, 1);
  const initialFrom = `${startMonth.getFullYear()}-${pad2(startMonth.getMonth() + 1)}-01`;
  const initialTo = `${today.getFullYear()}-${pad2(today.getMonth() + 1)}-${pad2(today.getDate())}`;
  if (loginUser.value.id) {
    await getRecords(loginUser.value.id, "", initialFrom, initialTo);
  } else {
    await getRecords(0, "", initialFrom, initialTo);
  }
  // 直近3ヶ月分(当月+過去2ヶ月)を取得済みとして記録し、二重取得を防ぐ
  for (let i = 0; i <= 2; i++) {
    const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
    fetchedMonths.add(monthKey(d.getFullYear(), d.getMonth() + 1));
  }
```

- [ ] **Step 8: `selectedDay`内の重複判定を`allRecords`ベースに変更する**

`Calendar.vue`の260〜265行目付近、`selectedDay`関数内の以下の部分:

```typescript
    const isRecord = ref(false);
    for (let record of records.value) {
      if (record.recorded_at.recorded_at === selected_day.value) {
        isRecord.value = true;
      }
    }
```

を以下に置き換える:

```typescript
    const isRecord = ref(false);
    for (let record of allRecords.value) {
      if (record.recorded_at.recorded_at === selected_day.value) {
        isRecord.value = true;
      }
    }
```

- [ ] **Step 9: テンプレートの`records`参照を`allRecords`に変更し、`update:to-page`を追加する**

`Calendar.vue`のテンプレート内、`<template v-if="compGetData && isLoaded">`ブロックの`<v-calendar>`タグに`@update:to-page="onPageChange"`を追加する:

```html
      <v-calendar
        ref="calendar"
        locale="ja-jp"
        :attributes="attrs"
        @click="selectedDay($event.target)"
        @update:to-page="onPageChange"
      >
```

同ブロック内の`#day-popover`スロット内、以下2箇所の`v-for="record in records"`を`v-for="record in allRecords"`に変更する(部位表示部分・メニュー表示部分の両方):

```html
            <span v-for="record in allRecords" :key="record.recorded_at.recorded_at">
```

- [ ] **Step 10: 型チェックを実行する**

Run:
```bash
docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html
```
Expected: `Calendar.vue`に関する新規の型エラーが無いこと

---

## Task 10: フロントエンド `RecordToday.vue` の重複API呼び出し解消

**Files:**
- Modify: `src/resources/js/components/record/RecordToday.vue`

- [ ] **Step 1: `useGetRecords`の呼び出しを削除し`compGetData` propを使うようにする**

`RecordToday.vue`のテンプレート(5行目)の`v-if="isLoaded && compGetData"`を`v-if="compGetData"`に変更する:

```html
  <template v-if="compGetData">
```

40行目の`import useGetRecords from "../../composables/record/useGetRecords";`を削除する。

58〜72行目の`onMounted`内、以下の部分:

```typescript
onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  // ログイン状態をVuexより取得<-このタイミングだとカレンダーの描画が完了しているためVuexの値を取得できる。
  isLogined.value = computed(() => store.state.isLogined);
  if (loginUser.value.id) {
    await getRecords(loginUser.value.id);
  } else {
    await getRecords(0);
  }
});
```

を以下に置き換える(`getRecords`呼び出しを削除):

```typescript
onMounted(async () => {
  const sessionLoginUser = getSessionLoginUser();
  if (sessionLoginUser) {
    loginUser.value = sessionLoginUser;
  } else {
    await getLoginUser();
  }
  // ログイン状態をVuexより取得<-このタイミングだとカレンダーの描画が完了しているためVuexの値を取得できる。
  isLogined.value = computed(() => store.state.isLogined);
});
```

87行目の`const { isLoaded, getRecords } = useGetRecords();`を削除する。

- [ ] **Step 2: 型チェックを実行する**

Run:
```bash
docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html
```
Expected: `RecordToday.vue`に関する新規の型エラーが無いこと

---

## Task 11: バックエンド全体テストの再実行

**Files:** なし(検証のみ)

- [ ] **Step 1: 開発DBのsentinelレコードで分離確認する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="App\Models\User::updateOrCreate(['email'=>'sentinel-task11@example.com'],['name'=>'sentinel','password'=>bcrypt('password')]); echo 'created';"
```
Expected: `created`

- [ ] **Step 2: 全テストを実行する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan test
```
Expected: 全件PASS(Task 1〜7で追加したテストを含む)

- [ ] **Step 3: 開発DBのsentinelレコードが生き残っていることを確認し削除する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="
echo App\Models\User::where('email','sentinel-task11@example.com')->exists() ? 'OK: 残っている' : 'NG: 消えた!';
echo PHP_EOL;
App\Models\User::where('email','sentinel-task11@example.com')->delete();
"
```
Expected: `OK: 残っている`

---

## Task 12: `chrome-screen-check`による画面確認

**Files:** なし(検証のみ)

- [ ] **Step 1: `chrome-screen-check`スキルを使い、ホーム画面のカレンダーを確認する**

`test@gmail.com`でログインし、ホーム画面へ遷移。開発者ツールのネットワークタブで`GET /api/recordContent`が**1回だけ**発行されていること(Task 10で解消した重複呼び出しが無いこと)を確認する。

- [ ] **Step 2: カレンダーの月移動を確認する**

カレンダーの「戻る」矢印を数回クリックし、直近3ヶ月より前の月に移動する。その都度`GET /api/recordContent`が発行され(`from`/`to`パラメータ付き)、該当月のバー・ポップオーバー(部位・メニュー名)が正しく表示されることを確認する。同じ月に再度移動した際は追加のAPI呼び出しが発生しない(ネットワークタブで確認)ことも確認する。

- [ ] **Step 3: 「記録済みか」の判定を確認する**

直近3ヶ月より前の月にある、既に記録済みの日をクリックし、二重登録の確認ダイアログ等が正しく動作する(記録済みなら新規POSTが飛ばない)ことを確認する。

- [ ] **Step 4: コンソール・ネットワークエラーが無いことを確認する**

`read_console_messages`・`read_network_requests`で、JSエラーや4xx/5xxが発生していないことを確認する。

---

## Task 13: `performance-investigation`手法による再計測

**Files:** なし(検証のみ)

- [ ] **Step 1: user_id=1(約1,192日分・10万件超のデータ)に対し、`GET /api/record`と`GET /api/recordContent`のクエリ数・所要時間を計測する**

Run:
```bash
docker exec trainingmemo-app-1 php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
DB::enableQueryLog();
\$start = microtime(true);
\$service = new App\Services\RecordContent\RecordContentService();
\$req = new App\Http\Requests\RecordContent\GetRecordContentsRequest();
\$result = \$service->getRecordsInRange(1, \$req->resolvedFrom(), \$req->resolvedTo());
\$elapsed = (microtime(true) - \$start) * 1000;
echo 'elapsed_ms=' . round(\$elapsed,2) . PHP_EOL;
echo 'query_count=' . count(DB::getQueryLog()) . PHP_EOL;
echo 'record_count=' . \$result->count() . PHP_EOL;
"
```
Expected: `record_count`が直近3ヶ月分(概ね90件以下)に収まっており、`elapsed_ms`が数十〜数百ms程度であること(全期間取得時の630〜2000msから改善していること)

- [ ] **Step 2: 記録日数が今後さらに増えても計測結果が変わらないことを確認する(オプション)**

`resolvedFrom()`/`resolvedTo()`を全期間になるよう手動で引数を変えて計測し、範囲を絞った場合との差を比較する(範囲を絞った場合は取得期間に依存し、ユーザーの利用期間の長さに依存しないことを確認する)。

---

## 最終コミット

全タスク完了後、以下でコミットする:

```bash
cd "//wsl.localhost/Ubuntu-22.04/tmp/trainingMemo"
git add -A
git commit -m "$(cat <<'EOF'
perf: RecordController/RecordContentControllerの無制限全件取得を解消

ホーム画面のカレンダーが利用期間に比例して遅くなる問題(パターンC)を解消。
RecordService/RecordContentServiceへロジックを切り出し、ホーム画面APIに
from/to日付範囲パラメータ(デフォルト直近3ヶ月)を追加。フロントエンドの
カレンダーは月単位で追加取得する方式に変更した。あわせてRecordController::index
のuser_idスコープ漏れ(全ユーザー中で最新記録を返してしまうバグ)も修正。

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

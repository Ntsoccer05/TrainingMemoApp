# 体重管理画面 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 体重を「独立記録」または「トレーニング記録画面からも」記録できるようにし、タグ付きメモ・折れ線グラフ・タグ別集計・目標体重ライン・達成バッジを持つ体重管理画面を新規追加する。

**Architecture:** 既存の `record_states` テーブル(bodyWeight, recorded_at)を体重記録の土台として拡張する。体重メモ用に `weight_memo` カラムを追加し、タグは `weight_tags` マスタ + 中間テーブルで多対多を持たせる。バックエンドは新規ドメイン `Weight`(Controller → Service → Model)を `.claude/rules/backend-architecture.md` のレイヤードアーキテクチャに従って追加する。フロントエンドはApexChartsを新規導入し、体重推移グラフ・タグ集計・グラフ⇔メモ相互参照(モーダル)を持つ `weightManagement.vue` を作成する。

**Tech Stack:** Laravel 9 (PHP), MySQL, Vue 3 + TypeScript (Composition API), Vuex, ApexCharts (`apexcharts` + `vue3-apexcharts`), `@kouts/vue-modal`(既存導入済み)

**関連仕様書:** `.claude/specs/2026-07-27-weight-record-analysis-design.md`

**この計画に含まないもの(別計画「記録管理画面拡張」で扱う):** トレーニング記録側の全体サマリー(体重×ボリューム相関グラフ)・種目別グラフ。ただし本計画で作る `WeightChart.vue` や `/api/weight` のレスポンス形式は、そちらの計画から再利用される前提で設計する。

---

## ファイル構造

### バックエンド(新規)

- `database/migrations/2026_07_27_000001_create_weight_tags_table.php`
- `database/migrations/2026_07_27_000002_create_record_state_weight_tag_table.php`
- `database/migrations/2026_07_27_000003_add_weight_memo_to_record_states_table.php`
- `database/migrations/2026_07_27_000004_add_target_weight_to_users_table.php`
- `database/seeders/WeightTagSeeder.php`
- `app/Models/WeightTag.php`
- `app/Services/Weight/WeightService.php`
- `app/Http/Requests/Weight/StoreWeightRecordRequest.php`
- `app/Http/Requests/Weight/GetWeightHistoryRequest.php`
- `app/Http/Requests/Weight/UpdateTargetWeightRequest.php`
- `app/Http/Controllers/WeightController.php`
- `tests/Feature/Services/Weight/WeightServiceTest.php`
- `tests/Feature/WeightControllerTest.php`
- `tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php`

### バックエンド(変更)

- `app/Models/RecordState.php` — `weight_memo` を fillable に追加、`weightTags()` リレーション追加
- `app/Models/User.php` — `target_weight` を fillable に追加
- `database/seeders/DatabaseSeeder.php` — `WeightTagSeeder::class` を呼び出しリストに追加
- `routes/api.php` — `/weight` 系エンドポイントを追加

### フロントエンド(新規)

- `resources/js/types/weight.d.ts`
- `resources/js/types/vue3-apexcharts.d.ts`
- `resources/js/composables/weight/useGetWeightHistory.ts`
- `resources/js/composables/weight/usePostWeightRecord.ts`
- `resources/js/composables/weight/useGetWeightTags.ts`
- `resources/js/components/weight/WeightChart.vue`
- `resources/js/components/weight/WeightRecordModal.vue`
- `resources/js/components/weight/WeightTagStats.vue`
- `resources/js/components/weight/WeightRecordForm.vue`
- `resources/js/components/weight/WeightTargetSetting.vue`
- `resources/js/views/weight/weightManagement.vue`

### フロントエンド(変更)

- `package.json` — `apexcharts`, `vue3-apexcharts` (と、未導入なら`dayjs`) を追加
- `resources/js/router/index.ts` — `/weight` ルート追加
- `resources/js/components/headerMenu/Header.vue` — 「体重管理」ナビゲーションリンク追加
- `resources/js/config/seo.ts` — `weight` エントリ追加
- `resources/js/components/record/recordContents.vue` — `WeightRecordForm` を埋め込み

---

## Task 1: ApexChartsの導入

**Files:**
- Modify: `package.json`
- Create: `resources/js/types/vue3-apexcharts.d.ts`

- [ ] **Step 1: パッケージをインストールする**

Run: `cd src && npm install apexcharts vue3-apexcharts`

Expected: `package.json` の `dependencies` に `apexcharts` と `vue3-apexcharts` が追加される

- [ ] **Step 2: vue3-apexchartsの型宣言ファイルを作成する**

`vue3-apexcharts` はTypeScript型定義を同梱していないため、モジュール宣言を追加する。

```typescript
declare module "vue3-apexcharts";
```

- [ ] **Step 3: importできることを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep -i apexcharts || echo "NO_APEXCHARTS_ERROR"`

Expected: `NO_APEXCHARTS_ERROR`(vue3-apexchartsの型解決エラーが出ないこと。他の既存の型エラーは無視してよい)

---

## Task 2: DBマイグレーション

**Files:**
- Create: `database/migrations/2026_07_27_000001_create_weight_tags_table.php`
- Create: `database/migrations/2026_07_27_000002_create_record_state_weight_tag_table.php`
- Create: `database/migrations/2026_07_27_000003_add_weight_memo_to_record_states_table.php`
- Create: `database/migrations/2026_07_27_000004_add_target_weight_to_users_table.php`
- Test: `tests/Feature/WeightMigrationsTest.php`

- [ ] **Step 1: マイグレーションのテストを書く(失敗させる)**

`tests/Feature/CacheTableMigrationTest.php` と同じパターンで、既存テーブルの構造を検証するテストを先に書く。

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WeightMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weight_tags_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('weight_tags'));
        $this->assertTrue(Schema::hasColumns('weight_tags', ['id', 'content', 'created_at', 'updated_at']));
    }

    public function test_record_state_weight_tag_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('record_state_weight_tag'));
        $this->assertTrue(Schema::hasColumns('record_state_weight_tag', [
            'id', 'record_state_id', 'weight_tag_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_record_states_table_has_weight_memo_column(): void
    {
        $this->assertTrue(Schema::hasColumn('record_states', 'weight_memo'));
    }

    public function test_users_table_has_target_weight_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'target_weight'));
    }
}
```

- [ ] **Step 2: テストを実行して失敗を確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightMigrationsTest.php`

Expected: FAIL(テーブル・カラムがまだ存在しないため4件とも失敗)

- [ ] **Step 3: `weight_tags` テーブルのマイグレーションを作成する**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('weight_tags', function (Blueprint $table) {
            $table->id();
            $table->string('content');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('weight_tags');
    }
};
```

- [ ] **Step 4: 中間テーブル `record_state_weight_tag` のマイグレーションを作成する**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('record_state_weight_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weight_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['record_state_id', 'weight_tag_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('record_state_weight_tag');
    }
};
```

- [ ] **Step 5: `record_states` に `weight_memo` を追加するマイグレーションを作成する**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->text('weight_memo')->nullable()->after('bodyWeight');
        });
    }

    public function down()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->dropColumn('weight_memo');
        });
    }
};
```

- [ ] **Step 6: `users` に `target_weight` を追加するマイグレーションを作成する**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->float('target_weight', 8, 1)->nullable()->after('is_admin');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('target_weight');
        });
    }
};
```

- [ ] **Step 7: マイグレーションを実行し、テストが通ることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan migrate`
Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightMigrationsTest.php`

Expected: PASS(4件とも成功)

---

## Task 3: WeightTagシーダー

**Files:**
- Create: `database/seeders/WeightTagSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: シーダーを作成する**

```php
<?php

namespace Database\Seeders;

use App\Models\WeightTag;
use Illuminate\Database\Seeder;

class WeightTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = ['食べすぎ', '飲みすぎ', '体調不良', '生理', '運動'];

        foreach ($tags as $tag) {
            WeightTag::firstOrCreate(['content' => $tag]);
        }
    }
}
```

- [ ] **Step 2: `DatabaseSeeder` に登録する**

`database/seeders/DatabaseSeeder.php:16-23` の `call()` 配列に `WeightTagSeeder::class` を追加する。

```php
    public function run()
    {
        $this -> call([
            UserSeeder::class,
            CategorySeeder::class,
            MenuSeeder::class,
            RecordStateSeeder::class,
            RecordMenuSeeder::class,
            RecordContentSeeder::class,
            WeightTagSeeder::class
        ]);
    }
```

- [ ] **Step 3: シーダーを実行して確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan db:seed --class=WeightTagSeeder`
Run: `docker exec -it db mysql -u root -p -e "SELECT content FROM training_memo.weight_tags;"`

Expected: 食べすぎ・飲みすぎ・体調不良・生理・運動 の5件が表示される(DB名は`.env`の`DB_DATABASE`に合わせて読み替える)

---

## Task 4: モデルの作成・拡張

**Files:**
- Create: `app/Models/WeightTag.php`
- Modify: `app/Models/RecordState.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Models/RecordStateWeightTagTest.php`

- [ ] **Step 1: リレーションのテストを書く(失敗させる)**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordStateWeightTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_state_can_be_synced_with_multiple_weight_tags(): void
    {
        $user = User::factory()->create();
        $recordState = RecordState::create([
            'user_id' => $user->id,
            'recorded_at' => '2026-07-27',
            'bodyWeight' => 65.5,
            'weight_memo' => '飲み会だった',
        ]);
        $tagA = WeightTag::create(['content' => '飲みすぎ']);
        $tagB = WeightTag::create(['content' => '体調不良']);

        $recordState->weightTags()->sync([$tagA->id, $tagB->id]);

        $this->assertCount(2, $recordState->fresh()->weightTags);
        $this->assertEquals('飲み会だった', $recordState->fresh()->weight_memo);
    }
}
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Models/RecordStateWeightTagTest.php`

Expected: FAIL(`weightTags`メソッド未定義、`weight_memo`がfillableでない)

- [ ] **Step 3: `WeightTag` モデルを作成する**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeightTag extends Model
{
    use HasFactory;

    protected $fillable = ['content'];

    public function recordStates(): BelongsToMany
    {
        return $this->belongsToMany(RecordState::class, 'record_state_weight_tag');
    }
}
```

- [ ] **Step 4: `RecordState` モデルを拡張する**

`app/Models/RecordState.php` を以下のように変更する。

```php
<?php

namespace App\Models;

use App\Models\RecordMenu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecordState extends Model
{
    use HasFactory;

    // 初期データ入力時にupdated_atカラムへのデータ挿入させなくする
    const UPDATED_AT = NULL;

    protected $fillable = ['user_id','recorded_at', 'weight_memo'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordMenus():HasMany
    {
        return $this->hasMany(RecordMenu::class);
    }

    public function recordContents():HasMany
    {
        return $this->hasMany(RecordContent::class);
    }

    public function menu():BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function weightTags():BelongsToMany
    {
        return $this->belongsToMany(WeightTag::class, 'record_state_weight_tag');
    }
}
```

- [ ] **Step 5: `User` モデルに `target_weight` を追加する**

`app/Models/User.php:27-32` の `$fillable` を変更する。

```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'target_weight'
    ];
```

- [ ] **Step 6: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Models/RecordStateWeightTagTest.php`

Expected: PASS

---

## Task 5: WeightService — 体重記録の保存

**Files:**
- Create: `app/Services/Weight/WeightService.php`
- Test: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: `recordWeight()` の失敗するテストを書く**

```php
<?php

namespace Tests\Feature\Services\Weight;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use App\Services\Weight\WeightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_weight_creates_new_record_state_when_none_exists(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', 65.5, '飲み会だった', []);

        $this->assertDatabaseHas('record_states', [
            'id' => $result->id,
            'user_id' => $user->id,
            'recorded_at' => '2026-07-27',
            'bodyWeight' => 65.5,
            'weight_memo' => '飲み会だった',
        ]);
    }

    public function test_record_weight_updates_existing_record_state_for_same_day(): void
    {
        $user = User::factory()->create();
        $existing = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-27']);
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', 66.0, '更新後メモ', []);

        $this->assertSame($existing->id, $result->id);
        $this->assertDatabaseHas('record_states', [
            'id' => $existing->id,
            'bodyWeight' => 66.0,
            'weight_memo' => '更新後メモ',
        ]);
    }

    public function test_record_weight_syncs_tags(): void
    {
        $user = User::factory()->create();
        $tagA = WeightTag::create(['content' => '飲みすぎ']);
        $tagB = WeightTag::create(['content' => '生理']);
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', 65.5, null, [$tagA->id, $tagB->id]);

        $this->assertCount(2, $result->fresh()->weightTags);
    }

    public function test_record_weight_allows_null_body_weight_for_memo_only_record(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->recordWeight($user->id, '2026-07-27', null, 'メモだけ残す', []);

        $this->assertDatabaseHas('record_states', [
            'id' => $result->id,
            'bodyWeight' => null,
            'weight_memo' => 'メモだけ残す',
        ]);
    }
}
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`App\Services\Weight\WeightService` が存在しない)

- [ ] **Step 3: `WeightService::recordWeight()` を実装する**

```php
<?php

namespace App\Services\Weight;

use App\Models\RecordState;
use Carbon\Carbon;

class WeightService
{
    /**
     * 指定日の体重・メモ・タグを記録する。既にその日のRecordStateがあれば更新する。
     *
     * @param int $userId
     * @param string $recordedAt Y-m-d形式
     * @param float|null $bodyWeight
     * @param string|null $memo
     * @param array<int> $tagIds
     * @return RecordState
     */
    public function recordWeight(int $userId, string $recordedAt, ?float $bodyWeight, ?string $memo, array $tagIds = []): RecordState
    {
        $recordState = RecordState::firstOrNew([
            'user_id' => $userId,
            'recorded_at' => $recordedAt,
        ]);
        $isExisting = $recordState->exists;

        $recordState->bodyWeight = $bodyWeight;
        $recordState->weight_memo = $memo;
        if ($isExisting) {
            $recordState->updated_at = Carbon::now();
        }
        $recordState->save();

        $recordState->weightTags()->sync($tagIds);

        return $recordState;
    }
}
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(4件成功)

---

## Task 6: WeightService — 体重推移の取得

**Files:**
- Modify: `app/Services/Weight/WeightService.php`
- Modify: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: `getWeightHistory()` の失敗するテストを追加する**

`tests/Feature/Services/Weight/WeightServiceTest.php` に以下のテストメソッドを追加する。

```php
    public function test_get_weight_history_returns_records_with_body_weight_in_range(): void
    {
        $user = User::factory()->create();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-06-01', 'bodyWeight' => 64.0]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-01', 'bodyWeight' => 65.0]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-08-01', 'bodyWeight' => 66.0]);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-06-15'),
            Carbon::parse('2026-07-15')
        );

        $this->assertCount(1, $result);
        $this->assertEquals(65.0, $result->first()->bodyWeight);
    }

    public function test_get_weight_history_excludes_records_without_body_weight(): void
    {
        $user = User::factory()->create();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10']);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31')
        );

        $this->assertCount(0, $result);
    }

    public function test_get_weight_history_ignores_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 70.0]);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31')
        );

        $this->assertCount(0, $result);
    }

    public function test_get_weight_history_eager_loads_weight_tags(): void
    {
        $user = User::factory()->create();
        $recordState = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $tag = WeightTag::create(['content' => '飲みすぎ']);
        $recordState->weightTags()->sync([$tag->id]);
        $service = new WeightService();

        $result = $service->getWeightHistory(
            $user->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31')
        );

        $this->assertTrue($result->first()->relationLoaded('weightTags'));
        $this->assertCount(1, $result->first()->weightTags);
    }
```

`use Carbon\Carbon;` を先頭の `use` 群に追加する。

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`getWeightHistory`メソッド未定義)

- [ ] **Step 3: `getWeightHistory()` を実装する**

`app/Services/Weight/WeightService.php` に以下のメソッドを追加し、ファイル先頭の `use` に `Illuminate\Database\Eloquent\Collection` を追加する。

```php
use Illuminate\Database\Eloquent\Collection;

    /**
     * 指定期間内の、体重が記録されているRecordStateを日付昇順で返す。
     *
     * @param int $userId
     * @param Carbon $from
     * @param Carbon $to
     * @return Collection<int, RecordState>
     */
    public function getWeightHistory(int $userId, Carbon $from, Carbon $to): Collection
    {
        return RecordState::where('user_id', $userId)
            ->whereBetween('recorded_at', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('bodyWeight')
            ->with('weightTags')
            ->orderBy('recorded_at', 'asc')
            ->get();
    }
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(全件成功)

---

## Task 7: WeightService — タグ別体重変動の集計

**Files:**
- Modify: `app/Services/Weight/WeightService.php`
- Modify: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: `getTagStatistics()` の失敗するテストを追加する**

```php
    public function test_get_tag_statistics_calculates_average_diff_for_next_day(): void
    {
        $user = User::factory()->create();
        $tag = WeightTag::create(['content' => '飲みすぎ']);

        $day1 = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day1->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.6]);

        $day2 = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-20', 'bodyWeight' => 64.0]);
        $day2->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-21', 'bodyWeight' => 64.4]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $matched = collect($stats)->firstWhere('tag', '飲みすぎ');
        $this->assertNotNull($matched);
        $this->assertEquals(0.5, $matched['average_diff']);
        $this->assertEquals(2, $matched['sample_count']);
    }

    public function test_get_tag_statistics_skips_tags_with_no_next_day_record(): void
    {
        $user = User::factory()->create();
        $tag = WeightTag::create(['content' => '運動']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $matched = collect($stats)->firstWhere('tag', '運動');
        $this->assertNull($matched);
    }

    public function test_get_tag_statistics_ignores_other_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tag = WeightTag::create(['content' => '生理']);
        $day = RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $otherUser->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.5]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $this->assertEmpty($stats);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`getTagStatistics`メソッド未定義)

- [ ] **Step 3: `getTagStatistics()` を実装する**

`app/Services/Weight/WeightService.php` に以下のメソッドを追加し、ファイル先頭の `use` に `App\Models\WeightTag` を追加する。

```php
use App\Models\WeightTag;

    /**
     * タグ別に「そのタグが付いた日の翌日」の体重変動平均を計算する。
     * 翌日の体重記録が存在しないタグはスキップする。
     *
     * @param int $userId
     * @return array<int, array{tag: string, average_diff: float, sample_count: int}>
     */
    public function getTagStatistics(int $userId): array
    {
        $tags = WeightTag::all();
        $stats = [];

        foreach ($tags as $tag) {
            $records = RecordState::where('record_states.user_id', $userId)
                ->whereNotNull('bodyWeight')
                ->whereHas('weightTags', function ($query) use ($tag) {
                    $query->where('weight_tags.id', $tag->id);
                })
                ->get(['id', 'recorded_at', 'bodyWeight']);

            $diffs = [];
            foreach ($records as $record) {
                $nextDay = Carbon::parse($record->recorded_at)->addDay()->toDateString();
                $nextRecord = RecordState::where('user_id', $userId)
                    ->whereDate('recorded_at', $nextDay)
                    ->whereNotNull('bodyWeight')
                    ->first();

                if ($nextRecord) {
                    $diffs[] = $nextRecord->bodyWeight - $record->bodyWeight;
                }
            }

            if (count($diffs) > 0) {
                $stats[] = [
                    'tag' => $tag->content,
                    'average_diff' => round(array_sum($diffs) / count($diffs), 2),
                    'sample_count' => count($diffs),
                ];
            }
        }

        return $stats;
    }
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(全件成功)

---

## Task 8: WeightService — 目標体重の更新

**Files:**
- Modify: `app/Services/Weight/WeightService.php`
- Modify: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: `updateTargetWeight()` の失敗するテストを追加する**

```php
    public function test_update_target_weight_sets_users_target_weight(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->updateTargetWeight($user->id, 60.0);

        $this->assertEquals(60.0, $result->target_weight);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight' => 60.0]);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`updateTargetWeight`メソッド未定義)

- [ ] **Step 3: `updateTargetWeight()` を実装する**

`app/Services/Weight/WeightService.php` に以下のメソッドを追加し、ファイル先頭の `use` に `App\Models\User` を追加する。

```php
use App\Models\User;

    /**
     * ユーザーの目標体重を更新する。
     *
     * @param int $userId
     * @param float $targetWeight
     * @return User
     */
    public function updateTargetWeight(int $userId, float $targetWeight): User
    {
        $user = User::findOrFail($userId);
        $user->target_weight = $targetWeight;
        $user->save();

        return $user;
    }
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(全件成功)

---

## Task 9: FormRequestの作成

**Files:**
- Create: `app/Http/Requests/Weight/StoreWeightRecordRequest.php`
- Create: `app/Http/Requests/Weight/GetWeightHistoryRequest.php`
- Create: `app/Http/Requests/Weight/UpdateTargetWeightRequest.php`
- Test: `tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php`

- [ ] **Step 1: `GetWeightHistoryRequest` の期間解決ロジックのテストを書く(失敗させる)**

`tests/Unit/Requests/RecordContent/GetRecordContentsRequestTest.php` と同じパターンで書く。

```php
<?php

namespace Tests\Unit\Requests\Weight;

use App\Http\Requests\Weight\GetWeightHistoryRequest;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class GetWeightHistoryRequestTest extends TestCase
{
    public function test_resolved_from_defaults_to_start_of_month_two_months_ago(): void
    {
        Carbon::setTestNow('2026-07-27');
        $request = new GetWeightHistoryRequest();

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-05-01', $result->toDateString());
        Carbon::setTestNow();
    }

    public function test_resolved_from_uses_provided_from_value(): void
    {
        $request = new GetWeightHistoryRequest();
        $request->merge(['from' => '2026-01-01']);

        $result = $request->resolvedFrom();

        $this->assertEquals('2026-01-01', $result->toDateString());
    }

    public function test_resolved_to_defaults_to_today(): void
    {
        Carbon::setTestNow('2026-07-27 15:00:00');
        $request = new GetWeightHistoryRequest();

        $result = $request->resolvedTo();

        $this->assertEquals('2026-07-27', $result->toDateString());
        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php`

Expected: FAIL(`App\Http\Requests\Weight\GetWeightHistoryRequest` が存在しない)

- [ ] **Step 3: `GetWeightHistoryRequest` を作成する**

`app/Http/Requests/RecordContent/GetRecordContentsRequest.php` と同じ構造。デフォルト期間は仕様上の初期表示「1ヶ月」に合わせて広めの2ヶ月とし、期間切替は`from`/`to`をフロントから明示指定させる。

```php
<?php

namespace App\Http\Requests\Weight;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class GetWeightHistoryRequest extends FormRequest
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

    public function resolvedFrom(): Carbon
    {
        if ($this->filled('from')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay();
        }

        return Carbon::now()->subMonthsNoOverflow(2)->startOfMonth();
    }

    public function resolvedTo(): Carbon
    {
        if ($this->filled('to')) {
            return Carbon::createFromFormat('Y-m-d', $this->input('to'))->endOfDay();
        }

        return Carbon::now()->endOfDay();
    }
}
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Unit/Requests/Weight/GetWeightHistoryRequestTest.php`

Expected: PASS(3件成功)

- [ ] **Step 5: `StoreWeightRecordRequest` を作成する**

```php
<?php

namespace App\Http\Requests\Weight;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightRecordRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'recorded_at' => 'required|date_format:Y-m-d',
            'body_weight' => 'nullable|numeric|min:0|max:999.9',
            'memo' => 'nullable|string|max:2000',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:weight_tags,id',
        ];
    }
}
```

- [ ] **Step 6: `UpdateTargetWeightRequest` を作成する**

```php
<?php

namespace App\Http\Requests\Weight;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTargetWeightRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'target_weight' => 'required|numeric|min:0|max:999.9',
        ];
    }
}
```

---

## Task 10: WeightControllerとルーティング

**Files:**
- Create: `app/Http/Controllers/WeightController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/WeightControllerTest.php`

- [ ] **Step 1: コントローラーの失敗するテストを書く**

```php
<?php

namespace Tests\Feature;

use App\Models\RecordState;
use App\Models\User;
use App\Models\WeightTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    public function test_store_creates_weight_record(): void
    {
        $user = $this->actingAsUser();
        $tag = WeightTag::create(['content' => '飲みすぎ']);

        $response = $this->postJson('/api/weight', [
            'recorded_at' => '2026-07-27',
            'body_weight' => 65.5,
            'memo' => '飲み会だった',
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(200)->assertJson(['status_code' => 200]);
        $this->assertDatabaseHas('record_states', [
            'user_id' => $user->id,
            'recorded_at' => '2026-07-27',
            'bodyWeight' => 65.5,
        ]);
    }

    public function test_index_returns_weight_history_and_target_weight(): void
    {
        $user = $this->actingAsUser();
        $user->target_weight = 60.0;
        $user->save();
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);

        $response = $this->getJson('/api/weight?from=2026-07-01&to=2026-07-31');

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('target_weight', 60.0)
            ->assertJsonCount(1, 'records');
    }

    public function test_tags_returns_all_weight_tags(): void
    {
        $this->actingAsUser();
        WeightTag::create(['content' => '飲みすぎ']);
        WeightTag::create(['content' => '生理']);

        $response = $this->getJson('/api/weight/tags');

        $response->assertStatus(200)->assertJsonCount(2, 'tags');
    }

    public function test_tag_stats_returns_statistics(): void
    {
        $user = $this->actingAsUser();
        $tag = WeightTag::create(['content' => '飲みすぎ']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        $day->weightTags()->sync([$tag->id]);
        RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-11', 'bodyWeight' => 65.5]);

        $response = $this->getJson('/api/weight/tagStats');

        $response->assertStatus(200)->assertJsonCount(1, 'stats');
    }

    public function test_update_target_weight(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/weight/targetWeight', ['target_weight' => 58.0]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight' => 58.0]);
    }
}
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: FAIL(ルート・コントローラーが存在せず404)

- [ ] **Step 3: `WeightController` を実装する**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Weight\GetWeightHistoryRequest;
use App\Http\Requests\Weight\StoreWeightRecordRequest;
use App\Http\Requests\Weight\UpdateTargetWeightRequest;
use App\Models\WeightTag;
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
        ]);
    }

    public function index(GetWeightHistoryRequest $request, WeightService $weightService)
    {
        $records = $weightService->getWeightHistory(
            auth()->id(),
            $request->resolvedFrom(),
            $request->resolvedTo()
        );

        return response()->json([
            'status_code' => 200,
            'records' => $records,
            'target_weight' => auth()->user()->target_weight,
        ]);
    }

    public function tags()
    {
        return response()->json([
            'status_code' => 200,
            'tags' => WeightTag::all(),
        ]);
    }

    public function tagStats(WeightService $weightService)
    {
        return response()->json([
            'status_code' => 200,
            'stats' => $weightService->getTagStatistics(auth()->id()),
        ]);
    }

    public function updateTargetWeight(UpdateTargetWeightRequest $request, WeightService $weightService)
    {
        $user = $weightService->updateTargetWeight(auth()->id(), $request->input('target_weight'));

        return response()->json([
            'status_code' => 200,
            'target_weight' => $user->target_weight,
        ]);
    }
}
```

- [ ] **Step 4: ルーティングを追加する**

`routes/api.php` の `auth:sanctum` ミドルウェアグループ内(`Route::get('/recordRanking/user', ...)` の次の行)に追加する。

```php
    use App\Http\Controllers\WeightController;
```

を先頭の `use` 群に追加し、グループ内に以下を追加する。

```php
    Route::get('/weight', [WeightController::class, 'index']);
    Route::post('/weight', [WeightController::class, 'store']);
    Route::get('/weight/tags', [WeightController::class, 'tags']);
    Route::get('/weight/tagStats', [WeightController::class, 'tagStats']);
    Route::post('/weight/targetWeight', [WeightController::class, 'updateTargetWeight']);
```

- [ ] **Step 5: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: PASS(全件成功)

---

## Task 11: フロントエンド型定義

**Files:**
- Create: `resources/js/types/weight.d.ts`

- [ ] **Step 1: 型定義ファイルを作成する**

```typescript
export declare type WeightTag = {
  id: number;
  content: string;
};

export declare type WeightRecord = {
  id: number;
  recorded_at: string;
  bodyWeight: number | null;
  weight_memo: string | null;
  weightTags: WeightTag[];
};

export declare type WeightHistoryResponse = {
  status_code: number;
  records: WeightRecord[];
  target_weight: number | null;
};

export declare type WeightTagsResponse = {
  status_code: number;
  tags: WeightTag[];
};

export declare type TagStatistic = {
  tag: string;
  average_diff: number;
  sample_count: number;
};

export declare type TagStatisticsResponse = {
  status_code: number;
  stats: TagStatistic[];
};
```

---

## Task 12: composables — API呼び出し

**Files:**
- Create: `resources/js/composables/weight/useGetWeightHistory.ts`
- Create: `resources/js/composables/weight/usePostWeightRecord.ts`
- Create: `resources/js/composables/weight/useGetWeightTags.ts`

`resources/js/composables/record/useGetRecordState.ts` と同じ、ref+async関数を返すパターンに合わせる。

- [ ] **Step 1: 既存パターンを確認する**

Read: `resources/js/composables/record/useGetRecordState.ts` (このタスクの実装者は着手前に一度読むこと。`ref`を返し、axios呼び出しを行う関数を返す構造になっている)

- [ ] **Step 2: `useGetWeightHistory.ts` を作成する**

```typescript
import { ref, Ref } from "vue";
import axios from "axios";
import { WeightRecord } from "../../types/weight";

export default function useGetWeightHistory() {
  const weightRecords: Ref<WeightRecord[]> = ref([]);
  const targetWeight: Ref<number | null> = ref(null);

  const getWeightHistory = async (from?: string, to?: string): Promise<void> => {
    await axios
      .get("/api/weight", {
        params: from && to ? { from, to } : {},
      })
      .then((res) => {
        weightRecords.value = res.data.records;
        targetWeight.value = res.data.target_weight;
      })
      .catch(() => {
        weightRecords.value = [];
        targetWeight.value = null;
      });
  };

  return { weightRecords, targetWeight, getWeightHistory };
}
```

- [ ] **Step 3: `usePostWeightRecord.ts` を作成する**

```typescript
import { ref, Ref } from "vue";
import axios from "axios";

export default function usePostWeightRecord() {
  const isSaving: Ref<boolean> = ref(false);
  const hasError: Ref<boolean> = ref(false);

  const postWeightRecord = async (
    recordedAt: string,
    bodyWeight: number | null,
    memo: string | null,
    tagIds: number[]
  ): Promise<void> => {
    isSaving.value = true;
    hasError.value = false;
    await axios
      .post("/api/weight", {
        recorded_at: recordedAt,
        body_weight: bodyWeight,
        memo: memo,
        tag_ids: tagIds,
      })
      .catch(() => {
        hasError.value = true;
      })
      .finally(() => {
        isSaving.value = false;
      });
  };

  return { isSaving, hasError, postWeightRecord };
}
```

- [ ] **Step 4: `useGetWeightTags.ts` を作成する**

```typescript
import { ref, Ref } from "vue";
import axios from "axios";
import { WeightTag } from "../../types/weight";

export default function useGetWeightTags() {
  const weightTags: Ref<WeightTag[]> = ref([]);

  const getWeightTags = async (): Promise<void> => {
    await axios
      .get("/api/weight/tags")
      .then((res) => {
        weightTags.value = res.data.tags;
      })
      .catch(() => {
        weightTags.value = [];
      });
  };

  return { weightTags, getWeightTags };
}
```

---

## Task 13: WeightChart.vue — 体重推移グラフ

**Files:**
- Create: `resources/js/components/weight/WeightChart.vue`

- [ ] **Step 1: コンポーネントを作成する**

ApexChartsの折れ線グラフに目標体重の水平線(annotation)を重ね、プロット点クリックでその日の`WeightRecord`をemitする。

```vue
<template>
  <VueApexCharts type="line" height="280" :options="chartOptions" :series="series" />
</template>

<script setup lang="ts">
import { computed, ComputedRef } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { WeightRecord } from "../../types/weight";

const props = defineProps<{
  records: WeightRecord[];
  targetWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "pointClick", record: WeightRecord): void;
}>();

const series: ComputedRef<{ name: string; data: (number | null)[] }[]> = computed(() => [
  {
    name: "体重(kg)",
    data: props.records.map((r) => r.bodyWeight),
  },
]);

const chartOptions = computed(() => {
  const annotations =
    props.targetWeight !== null
      ? {
          yaxis: [
            {
              y: props.targetWeight,
              borderColor: "#f97316",
              label: {
                text: `目標体重 ${props.targetWeight}kg`,
                style: { color: "#fff", background: "#f97316" },
              },
            },
          ],
        }
      : {};

  return {
    chart: {
      id: "weight-chart",
      toolbar: { show: false },
      events: {
        dataPointSelection: (
          _event: unknown,
          _chartContext: unknown,
          config: { dataPointIndex: number }
        ) => {
          const record = props.records[config.dataPointIndex];
          if (record) {
            emits("pointClick", record);
          }
        },
      },
    },
    xaxis: {
      categories: props.records.map((r) => r.recorded_at),
    },
    yaxis: {
      labels: {
        formatter: (val: number) => `${val}kg`,
      },
    },
    stroke: { curve: "smooth", width: 2 },
    markers: { size: 4 },
    annotations,
  };
});
</script>
```

---

## Task 14: WeightRecordModal.vue — 日付クリック時のメモ表示

**Files:**
- Create: `resources/js/components/weight/WeightRecordModal.vue`

- [ ] **Step 1: コンポーネントを作成する**

既存の`@kouts/vue-modal`(グローバル登録済み `Modal` コンポーネント)を使い、A案(モーダルで簡易表示)として実装する。

```vue
<template>
  <Modal
    v-model="showModal"
    :title="record ? `${record.recorded_at} の記録` : ''"
    wrapper-class="modal-wrapper"
  >
    <template v-if="record">
      <p class="mb-2">体重: {{ record.bodyWeight }}kg</p>
      <div class="flex flex-wrap gap-1 mb-2">
        <span
          v-for="tag in record.weightTags"
          :key="tag.id"
          class="px-2 py-0.5 bg-gray-200 rounded text-sm"
        >
          {{ tag.content }}
        </span>
      </div>
      <p class="whitespace-pre-wrap">{{ record.weight_memo || "メモはありません" }}</p>
    </template>
  </Modal>
</template>

<script setup lang="ts">
import { computed, WritableComputedRef } from "vue";
import { WeightRecord } from "../../types/weight";

const props = defineProps<{
  modelValue: boolean;
  record: WeightRecord | null;
}>();

const emits = defineEmits<{
  (e: "update:modelValue", value: boolean): void;
}>();

const showModal: WritableComputedRef<boolean> = computed({
  get: () => props.modelValue,
  set: (value) => emits("update:modelValue", value),
});
</script>
```

---

## Task 15: WeightTagStats.vue — タグ別集計表示

**Files:**
- Create: `resources/js/components/weight/WeightTagStats.vue`

- [ ] **Step 1: コンポーネントを作成する**

```vue
<template>
  <div v-if="stats.length > 0" class="mt-4">
    <h3 class="font-semibold mb-2">タグ別の翌日体重変動</h3>
    <ul>
      <li v-for="stat in stats" :key="stat.tag" class="text-sm mb-1">
        {{ stat.tag }}の翌日は平均{{ stat.average_diff > 0 ? "+" : "" }}{{ stat.average_diff }}kg
        <span class="text-gray-500">({{ stat.sample_count }}件)</span>
      </li>
    </ul>
  </div>
  <p v-else class="mt-4 text-sm text-gray-500">
    タグ別の集計を表示するには、タグ付きの記録が必要です。
  </p>
</template>

<script setup lang="ts">
import { TagStatistic } from "../../types/weight";

defineProps<{
  stats: TagStatistic[];
}>();
</script>
```

---

## Task 16: WeightRecordForm.vue — 体重・タグ・メモの共通入力フォーム

**Files:**
- Create: `resources/js/components/weight/WeightRecordForm.vue`

このコンポーネントは体重管理画面と、既存のトレーニング記録画面(`recordContents.vue`)の両方から埋め込まれる想定のため、外部から`recordedAt`を受け取り、初期値・タグ一覧はコンポーネント内部で取得する。

- [ ] **Step 1: コンポーネントを作成する**

```vue
<template>
  <div class="border p-3 rounded">
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">体重(kg)</label>
      <input
        type="text"
        class="border w-full p-1"
        placeholder="例: 65.5"
        v-model="bodyWeightInput"
      />
    </div>
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">タグ</label>
      <div class="flex flex-wrap gap-2">
        <label
          v-for="tag in weightTags"
          :key="tag.id"
          class="flex items-center gap-1 text-sm border rounded px-2 py-1 cursor-pointer"
        >
          <input type="checkbox" :value="tag.id" v-model="selectedTagIds" />
          {{ tag.content }}
        </label>
      </div>
    </div>
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">メモ</label>
      <textarea class="border w-full p-1" rows="3" v-model="memoInput"></textarea>
    </div>
    <button
      type="button"
      class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-4 rounded"
      :disabled="isSaving"
      @click="submit"
    >
      保存する
    </button>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, Ref } from "vue";
import useGetWeightTags from "../../composables/weight/useGetWeightTags";
import usePostWeightRecord from "../../composables/weight/usePostWeightRecord";

const props = defineProps<{
  recordedAt: string;
  initialBodyWeight?: number | null;
  initialMemo?: string | null;
  initialTagIds?: number[];
}>();

const emits = defineEmits<{
  (e: "saved"): void;
}>();

const { weightTags, getWeightTags } = useGetWeightTags();
const { isSaving, postWeightRecord } = usePostWeightRecord();

const bodyWeightInput: Ref<string> = ref(
  props.initialBodyWeight != null ? props.initialBodyWeight.toString() : ""
);
const memoInput: Ref<string> = ref(props.initialMemo ?? "");
const selectedTagIds: Ref<number[]> = ref(props.initialTagIds ? [...props.initialTagIds] : []);

const submit = async () => {
  const bodyWeight = bodyWeightInput.value !== "" ? parseFloat(bodyWeightInput.value) : null;
  await postWeightRecord(props.recordedAt, bodyWeight, memoInput.value || null, selectedTagIds.value);
  emits("saved");
};

onMounted(async () => {
  await getWeightTags();
});
</script>
```

---

## Task 17: weightManagement.vue — 画面統合とルーティング

**Files:**
- Create: `resources/js/views/weight/weightManagement.vue`
- Modify: `resources/js/router/index.ts`
- Modify: `resources/js/components/headerMenu/Header.vue`

- [ ] **Step 1: 画面本体を作成する**

期間切替(1ヶ月/3ヶ月/6ヶ月)ボタン、`WeightChart`、`WeightTagStats`、`WeightRecordForm`、`WeightRecordModal`を組み合わせる。

```vue
<template>
  <div class="max-w-3xl mx-auto mt-8 px-2">
    <h2 class="text-xl font-bold mb-4">体重管理</h2>

    <div class="flex gap-2 mb-4">
      <button
        v-for="option in periodOptions"
        :key="option.months"
        class="px-3 py-1 border rounded text-sm"
        :class="selectedMonths === option.months ? 'bg-blue-500 text-white' : 'bg-white'"
        @click="changePeriod(option.months)"
      >
        {{ option.label }}
      </button>
    </div>

    <WeightChart
      :records="weightRecords"
      :targetWeight="targetWeight"
      @pointClick="openRecordModal"
    />

    <WeightTagStats :stats="tagStats" />

    <div class="mt-6">
      <h3 class="font-semibold mb-2">今日の体重を記録</h3>
      <WeightRecordForm :recordedAt="today" @saved="onSaved" />
    </div>

    <WeightRecordModal v-model="showModal" :record="selectedRecord" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, Ref } from "vue";
import dayjs from "dayjs";
import useGetWeightHistory from "../../composables/weight/useGetWeightHistory";
import axios from "axios";
import WeightChart from "../../components/weight/WeightChart.vue";
import WeightTagStats from "../../components/weight/WeightTagStats.vue";
import WeightRecordForm from "../../components/weight/WeightRecordForm.vue";
import WeightRecordModal from "../../components/weight/WeightRecordModal.vue";
import { WeightRecord, TagStatistic } from "../../types/weight";
import { setSeo } from "../../utils/setSeo";

setSeo("weight");

const today = dayjs().format("YYYY-MM-DD");

const periodOptions = [
  { label: "1ヶ月", months: 1 },
  { label: "3ヶ月", months: 3 },
  { label: "6ヶ月", months: 6 },
];
const selectedMonths: Ref<number> = ref(1);

const { weightRecords, targetWeight, getWeightHistory } = useGetWeightHistory();
const tagStats: Ref<TagStatistic[]> = ref([]);

const showModal: Ref<boolean> = ref(false);
const selectedRecord: Ref<WeightRecord | null> = ref(null);

const openRecordModal = (record: WeightRecord): void => {
  selectedRecord.value = record;
  showModal.value = true;
};

const fetchHistory = async (): Promise<void> => {
  const from = dayjs().subtract(selectedMonths.value, "month").format("YYYY-MM-DD");
  const to = dayjs().format("YYYY-MM-DD");
  await getWeightHistory(from, to);
};

const fetchTagStats = async (): Promise<void> => {
  await axios
    .get("/api/weight/tagStats")
    .then((res) => {
      tagStats.value = res.data.stats;
    })
    .catch(() => {
      tagStats.value = [];
    });
};

const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  await fetchHistory();
};

const onSaved = async (): Promise<void> => {
  await fetchHistory();
  await fetchTagStats();
};

onMounted(async () => {
  await fetchHistory();
  await fetchTagStats();
});
</script>
```

`dayjs`が未導入の場合は`npm install dayjs`を先に実行する(既存プロジェクトの日付操作ライブラリを`package.json`で確認し、無ければ追加する)。

- [ ] **Step 2: 既存プロジェクトに日付ライブラリがあるか確認する**

Run: `cd src && grep -E "\"dayjs\"|\"moment\"|\"date-fns\"" package.json`

Expected: いずれかがヒットすればそれを使うようStep 1のimportを合わせる。何もヒットしなければ `npm install dayjs` を実行する。

- [ ] **Step 3: SEO設定に`weight`エントリを追加する**

`resources/js/config/seo.ts` の `SEO` オブジェクトに、他のページと同じ構造で追加する(`home`エントリの直後などに追加する)。

```typescript
    weight: {
        title: "体重管理 | トレメモ",
        description:
            "トレメモの体重管理機能で、日々の体重をグラフとタグ付きメモで記録・分析できます。トレーニング量との相関も確認可能。",
        keywords: mergeKeywords("体重管理, 体重記録, ダイエット記録, 体重グラフ"),
        robots: "noindex, nofollow",
    },
```

ログイン必須ページのため、既存の`login`/`register`と同様に`robots: "noindex, nofollow"`とする。

- [ ] **Step 4: ルーティングを追加する**

`resources/js/router/index.ts` に遅延ロードの定義を追加する。

```typescript
const WeightManagement = () => import("../views/weight/weightManagement.vue");
```

`routes`配列に以下を追加する(`userRecordRanking`ルートの直後)。

```typescript
    {
        path: "/weight",
        name: "weightManagement",
        component: WeightManagement,
        meta: { requiresAuth: true },
    },
```

- [ ] **Step 5: ヘッダーにナビゲーションリンクを追加する**

`resources/js/components/headerMenu/Header.vue:171-178` の「メニュー別最高記録」リンクの直後に追加する。

```html
              <li class="border-b md:border-none">
                <router-link
                  class="block px-8 py-2 my-4 hover:bg-gray-600 rounded cursor-pointer"
                  to="/weight"
                  >体重管理</router-link
                >
              </li>
```

- [ ] **Step 6: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

---

## Task 18: 目標体重の設定・達成バッジ表示

**Files:**
- Create: `resources/js/components/weight/WeightTargetSetting.vue`
- Modify: `resources/js/views/weight/weightManagement.vue`

- [ ] **Step 1: 目標体重設定コンポーネントを作成する**

演出は最小限とし、目標体重との差が0.5kg以内になったら達成バッジを表示する(絶対値判定。増量・減量どちらの目標でも同じロジックで扱えるシンプルな方式)。

```vue
<template>
  <div class="border p-3 rounded mb-4">
    <label class="block text-sm font-medium mb-1">目標体重(kg)</label>
    <div class="flex gap-2 items-center">
      <input type="text" class="border p-1 w-24" v-model="targetWeightInput" />
      <button
        type="button"
        class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-1 px-3 rounded"
        @click="save"
      >
        設定する
      </button>
      <span
        v-if="isAchieved"
        class="ml-2 text-sm font-bold text-orange-600 border border-orange-400 rounded px-2 py-0.5"
      >
        目標達成！
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ComputedRef, ref, Ref, watch } from "vue";
import axios from "axios";

const props = defineProps<{
  targetWeight: number | null;
  latestBodyWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "updated", value: number): void;
}>();

const targetWeightInput: Ref<string> = ref(props.targetWeight?.toString() ?? "");

watch(
  () => props.targetWeight,
  (value) => {
    targetWeightInput.value = value?.toString() ?? "";
  }
);

const isAchieved: ComputedRef<boolean> = computed(() => {
  if (props.targetWeight === null || props.latestBodyWeight === null) {
    return false;
  }
  return Math.abs(props.latestBodyWeight - props.targetWeight) <= 0.5;
});

const save = async (): Promise<void> => {
  if (targetWeightInput.value === "") {
    return;
  }
  const value = parseFloat(targetWeightInput.value);
  await axios
    .post("/api/weight/targetWeight", { target_weight: value })
    .then(() => {
      emits("updated", value);
    })
    .catch(() => {});
};
</script>
```

- [ ] **Step 2: `weightManagement.vue` に組み込む**

`resources/js/views/weight/weightManagement.vue` の `<template>` に、グラフの直前(期間切替ボタンの下)へ以下を追加する。

```html
    <WeightTargetSetting
      :targetWeight="targetWeight"
      :latestBodyWeight="latestBodyWeight"
      @updated="onTargetWeightUpdated"
    />
```

`<script setup>` に以下を追加する。

```typescript
import WeightTargetSetting from "../../components/weight/WeightTargetSetting.vue";
import { computed, ComputedRef } from "vue";

const latestBodyWeight: ComputedRef<number | null> = computed(() => {
  if (weightRecords.value.length === 0) {
    return null;
  }
  return weightRecords.value[weightRecords.value.length - 1].bodyWeight;
});

const onTargetWeightUpdated = (value: number): void => {
  targetWeight.value = value;
};
```

(`computed`, `ComputedRef` が既に他のimportと重複する場合は1つのimport文にまとめる)

- [ ] **Step 3: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

---

## Task 19: トレーニング記録画面への体重記録フォーム埋め込み

**Files:**
- Modify: `resources/js/components/record/recordContents.vue`

仕様書の「入力コンポーネントはトレーニング記録画面にも共通で埋め込み、体重だけ先行/後追いで入力できる」を満たすため、既存のトレーニング記録画面(`recordContents.vue`)に`WeightRecordForm`を埋め込む。

- [ ] **Step 1: 現状のテンプレート構造を確認する**

Read: `resources/js/components/record/recordContents.vue`(既にこの計画の調査時に全文を読んでいるため、`<caption>`内、体重表示(`{{ bodyWeight }}`)の直後に埋め込むのが自然な位置)

- [ ] **Step 2: `WeightRecordForm` を埋め込む**

`resources/js/components/record/recordContents.vue` の `<template>` 内、`<caption>` の体重表示行(`今回の体重：{{ bodyWeight }}`)の直後に追加する。

```html
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の体重：{{ bodyWeight }}
              </p>
              <div class="mt-2">
                <WeightRecordForm
                  v-if="recordedAtParam"
                  :recordedAt="recordedAtParam"
                  @saved="onWeightSaved"
                />
              </div>
```

- [ ] **Step 3: `<script setup>` にimportと呼び出しを追加する**

`resources/js/components/record/recordContents.vue` の `<script setup lang="ts">` に以下を追加する。

```typescript
import WeightRecordForm from "../weight/WeightRecordForm.vue";
import { computed, ComputedRef } from "vue";

const recordedAtParam: ComputedRef<string> = computed(() => route.params.recordId as string);

const onWeightSaved = (): void => {
  store.commit("invalidateLatestRecordState");
};
```

(`computed`, `ComputedRef` は既存のimport文に既にある場合、重複させずそのimport文に追記する)

- [ ] **Step 4: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 5: ブラウザで動作確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- トレーニング記録画面(`/record/:recordId`)で体重・タグ・メモを入力して保存できる
- 保存後、体重管理画面(`/weight`)のグラフに反映される

---

## 最終コミット

すべてのタスク完了後、以下を実行する。

```bash
git add -A
git commit -m "feat: 体重管理画面を新規追加(グラフ・タグ付きメモ・目標体重)"
```

# 体重管理・記録画面 改善 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 前回実装した体重管理機能へのフィードバック5点(トレーニング記録画面のUI/UX復元、ローディングスピナー、体重管理画面の日付選択、新規登録時のデフォルトカテゴリー、体重タグのユーザー別化+編集機能)を実装する。

**Architecture:** バックエンドは既存の`WeightService`/`WeightController`を拡張し、`weight_tags`にユーザースコープを追加する。新規登録時のデフォルトデータ作成は`App\Services\Auth\RegisterService`に集約する。フロントエンドは共通の`LoadingSpinner.vue`を新設し、既存の複数画面のテキストローディング表示を置き換える。

**Tech Stack:** Laravel 9 (PHP), MySQL, Vue 3 + TypeScript (Composition API), 既存のWeight機能一式

**関連仕様書:** `.claude/specs/2026-07-27-weight-feature-improvements-design.md`

---

## ファイル構造

### バックエンド(新規)

- `database/migrations/2026_07_27_000005_add_user_id_to_weight_tags_table.php`
- `app/Services/Auth/RegisterService.php`
- `app/Http/Requests/Weight/StoreWeightTagRequest.php`
- `tests/Feature/Services/Auth/RegisterServiceTest.php`

### バックエンド(変更)

- `app/Models/WeightTag.php` — `user_id`を`$fillable`に追加、`user()`リレーション追加
- `app/Services/Weight/WeightService.php` — `getAllTags(int $userId)`, `getTagStatistics`のuser_idスコープ化, `addTag`, `deleteTag`追加
- `app/Http/Controllers/WeightController.php` — `tags()`修正、`storeTag()`, `destroyTag()`追加
- `app/Http/Requests/Weight/StoreWeightRecordRequest.php` — `tag_ids.*`のexists検証をユーザースコープに変更
- `app/Http/Controllers/Auth/RegisterController.php` — `RegisterService`呼び出し追加
- `routes/api.php` — タグCRUDルート追加
- `database/seeders/WeightTagSeeder.php` — 「生理」削除、user_id対応
- `tests/Feature/WeightControllerTest.php` — 既存タグ作成箇所にuser_id追加
- `tests/Feature/Models/RecordStateWeightTagTest.php` — 既存タグ作成箇所にuser_id追加
- `tests/Feature/Services/Weight/WeightServiceTest.php` — 既存タグ作成箇所にuser_id追加、getAllTags/getTagStatisticsのスコープテスト追加

### フロントエンド(新規)

- `resources/js/components/common/LoadingSpinner.vue`
- `resources/js/composables/weight/usePostWeightTag.ts`
- `resources/js/composables/weight/useDeleteWeightTag.ts`
- `resources/js/components/weight/WeightTagEditor.vue`

### フロントエンド(変更)

- `resources/js/components/record/recordContents.vue` — `WeightRecordForm`埋め込みを削除
- `resources/js/components/record/Calendar.vue` — ローディングテキストを`LoadingSpinner`に置換
- `resources/js/views/ranking/userRecordRanking.vue` — ローディングテキストを`LoadingSpinner`に置換
- `resources/js/views/weight/weightManagement.vue` — ローディングスピナー・日付選択・タグ編集セクション追加

---

## Task 1: `weight_tags`にuser_idを追加するマイグレーション

**Files:**
- Create: `database/migrations/2026_07_27_000005_add_user_id_to_weight_tags_table.php`
- Test: `tests/Feature/WeightMigrationsTest.php`

- [ ] **Step 1: マイグレーションのテストを追加する(失敗させる)**

`tests/Feature/WeightMigrationsTest.php`に以下のテストメソッドを追加する。

```php
    public function test_weight_tags_table_has_user_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('weight_tags', 'user_id'));
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightMigrationsTest.php`

Expected: FAIL(`weight_tags`に`user_id`カラムがまだ存在しない)

- [ ] **Step 3: マイグレーションを作成する**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('weight_tags', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('weight_tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
```

- [ ] **Step 4: マイグレーションを実行し、テストが通ることを確認する**

`weight_tags`は既存レコードがあり、`user_id`は`NOT NULL`の外部キーなので、既存レコードがあるままでは`migrate`が失敗する。開発DBを作り直す。

Run: `docker exec trainingmemoapp-app-1 php artisan migrate:fresh`
Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightMigrationsTest.php`

Expected: PASS(5件成功)

---

## Task 2: `WeightTag`モデルの更新

**Files:**
- Modify: `app/Models/WeightTag.php`

- [ ] **Step 1: `user_id`を`$fillable`に追加し、`user()`リレーションを追加する**

`app/Models/WeightTag.php`を以下のように変更する。

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeightTag extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recordStates(): BelongsToMany
    {
        return $this->belongsToMany(RecordState::class, 'record_state_weight_tag');
    }
}
```

- [ ] **Step 2: 型チェックのため既存テストを実行し、影響範囲を確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php tests/Feature/Models/RecordStateWeightTagTest.php tests/Feature/WeightControllerTest.php`

Expected: FAIL(既存の`WeightTag::create(['content' => ...])`が`user_id`カラムのNOT NULL制約違反でSQLエラーになる。これはTask 3・Task 5で修正する)

---

## Task 3: 既存テストへの`user_id`追加、`WeightService::getAllTags`のユーザースコープ化

**Files:**
- Modify: `app/Services/Weight/WeightService.php`
- Modify: `tests/Feature/Services/Weight/WeightServiceTest.php`
- Modify: `tests/Feature/Models/RecordStateWeightTagTest.php`
- Modify: `tests/Feature/WeightControllerTest.php`

- [ ] **Step 1: 既存テストの`WeightTag::create`呼び出しに`user_id`を追加する**

`tests/Feature/Services/Weight/WeightServiceTest.php`の以下の行を修正する。

52-53行目:
```php
        $tagA = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $tagB = WeightTag::create(['user_id' => $user->id, 'content' => '生理']);
```

128行目:
```php
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
```

145行目:
```php
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
```

167行目:
```php
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '運動']);
```

182行目(このテストは`$otherUser`のタグを検証する意図なので、`$otherUser->id`を使う):
```php
        $tag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '生理']);
```

`tests/Feature/Models/RecordStateWeightTagTest.php`の24-25行目を修正する。

```php
        $tagA = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $tagB = WeightTag::create(['user_id' => $user->id, 'content' => '体調不良']);
```

`tests/Feature/WeightControllerTest.php`の25行目、73-74行目、84行目を修正する。25行目:

```php
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
```

73-74行目(`actingAsUser()`の戻り値を変数に受け取る必要がある。現在`$this->actingAsUser();`のように戻り値を使っていないため、`$user = $this->actingAsUser();`に変更する):

```php
    public function test_tags_returns_all_weight_tags(): void
    {
        $user = $this->actingAsUser();
        WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        WeightTag::create(['user_id' => $user->id, 'content' => '生理']);

        $response = $this->getJson('/api/weight/tags');

        $response->assertStatus(200)->assertJsonCount(2, 'tags');
    }
```

84行目:
```php
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
```

- [ ] **Step 2: `getAllTags`のユーザースコープ化テストを追加する(失敗させる)**

`tests/Feature/Services/Weight/WeightServiceTest.php`に以下のテストメソッドを追加する(`test_update_target_weight_sets_users_target_weight`の後)。

```php
    public function test_get_all_tags_returns_only_the_users_own_tags(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        WeightTag::create(['user_id' => $user->id, 'content' => '運動']);
        WeightTag::create(['user_id' => $otherUser->id, 'content' => '生理']);
        $service = new WeightService();

        $result = $service->getAllTags($user->id);

        $this->assertCount(1, $result);
        $this->assertEquals('運動', $result->first()->content);
    }
```

- [ ] **Step 3: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`getAllTags()`が引数を受け取らないため`ArgumentCountError`、または既存の`getAllTags()`呼び出し元でのシグネチャ不一致)

- [ ] **Step 4: `getAllTags`をユーザースコープ化する**

`app/Services/Weight/WeightService.php`の`getAllTags`メソッドを以下のように変更する。

```php
    /**
     * ユーザー自身の体重タグをすべて返す。
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection<int, WeightTag>
     */
    public function getAllTags(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return WeightTag::where('user_id', $userId)->get();
    }
```

- [ ] **Step 5: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php tests/Feature/Models/RecordStateWeightTagTest.php`

Expected: PASS(全件成功)

---

## Task 4: `WeightService::getTagStatistics`のユーザースコープ化

**Files:**
- Modify: `app/Services/Weight/WeightService.php`
- Modify: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: 他ユーザーのタグが混ざらないことを確認するテストを追加する(失敗させる)**

`tests/Feature/Services/Weight/WeightServiceTest.php`に以下のテストメソッドを追加する。

```php
    public function test_get_tag_statistics_only_considers_the_users_own_tags(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        // ユーザー自身は「飲みすぎ」というタグを持っていない(他ユーザーが同名タグを持つのみ)
        $otherUsersTag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);
        $day = RecordState::create(['user_id' => $user->id, 'recorded_at' => '2026-07-10', 'bodyWeight' => 65.0]);
        // 他ユーザー所有のタグを誤ってこのユーザーの記録に紐付けても(データ不整合を想定した防御的なテスト)、
        // getTagStatisticsはuser_idのタグ一覧のみを走査するため、統計には出てこない
        $day->weightTags()->sync([$otherUsersTag->id]);

        $service = new WeightService();
        $stats = $service->getTagStatistics($user->id);

        $this->assertEmpty($stats);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php --filter test_get_tag_statistics_only_considers_the_users_own_tags`

Expected: FAIL(`getTagStatistics`が`WeightTag::all()`で全ユーザーのタグを走査しているため、他ユーザー所有の「飲みすぎ」タグも対象になってしまう)

- [ ] **Step 3: `getTagStatistics`をユーザースコープ化する**

`app/Services/Weight/WeightService.php`の`getTagStatistics`メソッド内、`$tags = WeightTag::all();`の行を以下に変更する。

```php
        $tags = WeightTag::where('user_id', $userId)->get();
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(全件成功)

---

## Task 5: `WeightService::addTag`/`deleteTag`の実装

**Files:**
- Modify: `app/Services/Weight/WeightService.php`
- Modify: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: `addTag`/`deleteTag`の失敗するテストを追加する**

```php
    public function test_add_tag_creates_a_new_tag_for_the_user(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->addTag($user->id, 'サウナ');

        $this->assertNotNull($result);
        $this->assertEquals('サウナ', $result->content);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => 'サウナ']);
    }

    public function test_add_tag_returns_null_when_user_already_has_five_tags(): void
    {
        $user = User::factory()->create();
        foreach (['タグ1', 'タグ2', 'タグ3', 'タグ4', 'タグ5'] as $content) {
            WeightTag::create(['user_id' => $user->id, 'content' => $content]);
        }
        $service = new WeightService();

        $result = $service->addTag($user->id, 'タグ6');

        $this->assertNull($result);
        $this->assertDatabaseMissing('weight_tags', ['user_id' => $user->id, 'content' => 'タグ6']);
    }

    public function test_add_tag_does_not_count_other_users_tags_toward_the_limit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        foreach (['タグ1', 'タグ2', 'タグ3', 'タグ4', 'タグ5'] as $content) {
            WeightTag::create(['user_id' => $otherUser->id, 'content' => $content]);
        }
        $service = new WeightService();

        $result = $service->addTag($user->id, '運動');

        $this->assertNotNull($result);
    }

    public function test_delete_tag_removes_the_users_own_tag(): void
    {
        $user = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);
        $service = new WeightService();

        $service->deleteTag($user->id, $tag->id);

        $this->assertDatabaseMissing('weight_tags', ['id' => $tag->id]);
    }

    public function test_delete_tag_throws_when_tag_belongs_to_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);
        $service = new WeightService();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $service->deleteTag($user->id, $tag->id);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`addTag`/`deleteTag`メソッド未定義)

- [ ] **Step 3: `addTag`/`deleteTag`を実装する**

`app/Services/Weight/WeightService.php`に以下の2メソッドを追加する。

```php
    /**
     * ユーザーの体重タグを追加する。既に5個保持している場合はnullを返す。
     *
     * @param int $userId
     * @param string $content
     * @return WeightTag|null
     */
    public function addTag(int $userId, string $content): ?WeightTag
    {
        if (WeightTag::where('user_id', $userId)->count() >= 5) {
            return null;
        }

        return WeightTag::create(['user_id' => $userId, 'content' => $content]);
    }

    /**
     * ユーザー自身の体重タグを削除する。
     *
     * @param int $userId
     * @param int $tagId
     * @return void
     */
    public function deleteTag(int $userId, int $tagId): void
    {
        $tag = WeightTag::where('user_id', $userId)->findOrFail($tagId);
        $tag->delete();
    }
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(全件成功)

---

## Task 6: タグCRUDのFormRequest・Controller・ルーティング

**Files:**
- Create: `app/Http/Requests/Weight/StoreWeightTagRequest.php`
- Modify: `app/Http/Controllers/WeightController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/WeightControllerTest.php`

- [ ] **Step 1: `StoreWeightTagRequest`を作成する**

```php
<?php

namespace App\Http\Requests\Weight;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightTagRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'content' => 'required|string|max:50',
        ];
    }
}
```

- [ ] **Step 2: コントローラーテストを追加する(失敗させる)**

`tests/Feature/WeightControllerTest.php`に以下のテストメソッドを追加する。

```php
    public function test_store_tag_creates_a_new_tag(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/weight/tags', ['content' => 'サウナ']);

        $response->assertStatus(200)->assertJsonPath('tag.content', 'サウナ');
    }

    public function test_store_tag_returns_422_when_limit_exceeded(): void
    {
        $user = $this->actingAsUser();
        foreach (['タグ1', 'タグ2', 'タグ3', 'タグ4', 'タグ5'] as $content) {
            WeightTag::create(['user_id' => $user->id, 'content' => $content]);
        }

        $response = $this->postJson('/api/weight/tags', ['content' => 'タグ6']);

        $response->assertStatus(422);
    }

    public function test_destroy_tag_removes_the_tag(): void
    {
        $user = $this->actingAsUser();
        $tag = WeightTag::create(['user_id' => $user->id, 'content' => '飲みすぎ']);

        $response = $this->deleteJson("/api/weight/tags/{$tag->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('weight_tags', ['id' => $tag->id]);
    }

    public function test_destroy_tag_returns_404_for_another_users_tag(): void
    {
        $this->actingAsUser();
        $otherUser = User::factory()->create();
        $tag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);

        $response = $this->deleteJson("/api/weight/tags/{$tag->id}");

        $response->assertStatus(404);
    }
```

- [ ] **Step 3: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: FAIL(ルート未定義で404、または`tags()`が未修正で既存テストが失敗)

- [ ] **Step 4: `WeightController`にメソッドを追加し、`tags()`を修正する**

`app/Http/Controllers/WeightController.php`を以下のように変更する。

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Weight\GetWeightHistoryRequest;
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
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function tags(WeightService $weightService)
    {
        return response()->json([
            'status_code' => 200,
            'tags' => $weightService->getAllTags(auth()->id()),
        ]);
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

    public function tagStats(WeightService $weightService)
    {
        return response()->json([
            'status_code' => 200,
            'stats' => $weightService->getTagStatistics(auth()->id()),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function updateTargetWeight(UpdateTargetWeightRequest $request, WeightService $weightService)
    {
        $user = $weightService->updateTargetWeight(auth()->id(), $request->input('target_weight'));

        return response()->json([
            'status_code' => 200,
            'target_weight' => $user->target_weight,
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
```

- [ ] **Step 5: ルーティングを追加する**

`routes/api.php`の`Route::post('/weight/targetWeight', ...)`の直後に追加する。

```php
    Route::post('/weight/tags', [WeightController::class, 'storeTag']);
    Route::delete('/weight/tags/{id}', [WeightController::class, 'destroyTag']);
```

- [ ] **Step 6: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: PASS(全件成功)

---

## Task 7: `StoreWeightRecordRequest`のタグ検証をユーザースコープに変更

**Files:**
- Modify: `app/Http/Requests/Weight/StoreWeightRecordRequest.php`
- Modify: `tests/Feature/WeightControllerTest.php`

- [ ] **Step 1: 他ユーザーのタグIDを拒否するテストを追加する(失敗させる)**

`tests/Feature/WeightControllerTest.php`に以下のテストメソッドを追加する。

```php
    public function test_store_rejects_another_users_tag_id(): void
    {
        $this->actingAsUser();
        $otherUser = User::factory()->create();
        $otherUsersTag = WeightTag::create(['user_id' => $otherUser->id, 'content' => '飲みすぎ']);

        $response = $this->postJson('/api/weight', [
            'recorded_at' => '2026-07-27',
            'body_weight' => 65.5,
            'tag_ids' => [$otherUsersTag->id],
        ]);

        $response->assertStatus(422);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php --filter test_store_rejects_another_users_tag_id`

Expected: FAIL(現在の`exists:weight_tags,id`は所有者を問わず存在すれば通ってしまうため、200が返る)

- [ ] **Step 3: `StoreWeightRecordRequest`のバリデーションルールをユーザースコープに変更する**

`app/Http/Requests/Weight/StoreWeightRecordRequest.php`を以下のように変更する。

```php
<?php

namespace App\Http\Requests\Weight;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'tag_ids.*' => [
                'integer',
                Rule::exists('weight_tags', 'id')->where('user_id', auth()->id()),
            ],
        ];
    }
}
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: PASS(全件成功)

---

## Task 8: `WeightTagSeeder`の更新

**Files:**
- Modify: `database/seeders/WeightTagSeeder.php`

- [ ] **Step 1: `WeightTagSeeder`を更新する**

「生理」を削除し、`test@gmail.com`ユーザー(`UserSeeder`が作成)に紐づけて4タグを作成するよう変更する。

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WeightTag;
use Illuminate\Database\Seeder;

class WeightTagSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@gmail.com')->first();

        if ($user === null) {
            return;
        }

        $tags = ['食べすぎ', '飲みすぎ', '体調不良', '運動'];

        foreach ($tags as $tag) {
            WeightTag::firstOrCreate(['user_id' => $user->id, 'content' => $tag]);
        }
    }
}
```

- [ ] **Step 2: 動作確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan db:seed --class=WeightTagSeeder`
Run: `docker exec trainingmemoapp-app-1 php artisan tinker --execute="echo App\Models\WeightTag::pluck('content');"`

Expected: `["食べすぎ","飲みすぎ","体調不良","運動"]`(4件、生理を含まない)

---

## Task 9: `RegisterService`の新規作成とユーザー登録への統合

**Files:**
- Create: `app/Services/Auth/RegisterService.php`
- Test: `tests/Feature/Services/Auth/RegisterServiceTest.php`
- Modify: `app/Http/Controllers/Auth/RegisterController.php`

- [ ] **Step 1: `RegisterService`の失敗するテストを書く**

```php
<?php

namespace Tests\Feature\Services\Auth;

use App\Models\User;
use App\Services\Auth\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_default_data_creates_five_categories(): void
    {
        $user = User::factory()->create();
        $service = new RegisterService();

        $service->setupDefaultData($user->id);

        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '胸']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '背中']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '足']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '腕']);
        $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'content' => '腹筋']);
        $this->assertEquals(5, \App\Models\Category::where('user_id', $user->id)->count());
    }

    public function test_setup_default_data_does_not_create_any_menus(): void
    {
        $user = User::factory()->create();
        $service = new RegisterService();

        $service->setupDefaultData($user->id);

        $this->assertEquals(0, \App\Models\Menu::where('user_id', $user->id)->count());
    }

    public function test_setup_default_data_creates_four_weight_tags(): void
    {
        $user = User::factory()->create();
        $service = new RegisterService();

        $service->setupDefaultData($user->id);

        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '食べすぎ']);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '飲みすぎ']);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '体調不良']);
        $this->assertDatabaseHas('weight_tags', ['user_id' => $user->id, 'content' => '運動']);
        $this->assertEquals(4, \App\Models\WeightTag::where('user_id', $user->id)->count());
    }
}
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Auth/RegisterServiceTest.php`

Expected: FAIL(`App\Services\Auth\RegisterService`が存在しない)

- [ ] **Step 3: `RegisterService`を実装する**

```php
<?php

namespace App\Services\Auth;

use App\Models\Category;
use App\Models\WeightTag;

class RegisterService
{
    private const DEFAULT_CATEGORIES = ['胸', '背中', '足', '腕', '腹筋'];

    private const DEFAULT_WEIGHT_TAGS = ['食べすぎ', '飲みすぎ', '体調不良', '運動'];

    /**
     * 新規ユーザーにデフォルトのカテゴリー・体重タグを作成する。メニューは作成しない。
     *
     * @param int $userId
     * @return void
     */
    public function setupDefaultData(int $userId): void
    {
        foreach (self::DEFAULT_CATEGORIES as $content) {
            Category::create(['user_id' => $userId, 'content' => $content]);
        }

        foreach (self::DEFAULT_WEIGHT_TAGS as $content) {
            WeightTag::create(['user_id' => $userId, 'content' => $content]);
        }
    }
}
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Auth/RegisterServiceTest.php`

Expected: PASS(3件成功)

- [ ] **Step 5: `RegisterController`から呼び出す**

`app/Http/Controllers/Auth/RegisterController.php`を以下のように変更する。

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegisterService;

class RegisterController extends Controller
{

    //ユーザ登録処理
    public function register(RegisterRequest $request, RegisterService $registerService)
    {
        $user = User::Create([
            'name' => $request['name'],
            'email' => $request['email'],
            'password' => Hash::make($request['password'])
        ]);

        if(!is_null($user)) {
            $registerService->setupDefaultData($user->id);
            Auth::guard()->login($user, true);
            return response()->json(["status_code" => 200, "message" => "登録しました", "user" => $user]);
        }
        else{
            return response()->json(["status_code" => 500, "message" => "登録失敗しました"]);
        }
    }

    //Googleユーザ登録処理
    public function registerProviderUser(RegisterRequest $request, string $provider, RegisterService $registerService)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'unique:users'],
            'token' => ['required', 'string']
        ]);

        $token = $request->token;

        $providerUser = Socialite::driver($provider)->userFromToken($token);

        $user = User::create([
            'name' => $request->name,
            'email' => $providerUser->getEmail(),
            'password' => null,
        ]);

        $registerService->setupDefaultData($user->id);

        Auth::guard()->login($user, true);

        return response()->json(["status_code" => 200, "message" => "登録しました", "user" => $user]);
    }
}
```

- [ ] **Step 6: 既存の認証系テストが壊れていないか確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test --filter Register`

Expected: PASS(既存の登録関連テストがあれば全て成功。無ければ0件でエラーなく終了)

---

## Task 10: 全体のマイグレーション・シーダー再実行と回帰確認

**Files:**
- なし(確認のみ)

- [ ] **Step 1: 開発DBを作り直し、全シーダーを実行する**

Run: `docker exec trainingmemoapp-app-1 php artisan migrate:fresh --seed`

Expected: エラーなく完了

- [ ] **Step 2: 全体のバックエンドテストを実行する**

Run: `docker exec trainingmemoapp-app-1 php artisan test`

Expected: PASS(全件成功、実行前の件数から今回追加したテスト分だけ増えていること)

---

## Task 11: トレーニング記録画面から体重記録フォームの埋め込みを削除

**Files:**
- Modify: `resources/js/components/record/recordContents.vue`

- [ ] **Step 1: テンプレートから`WeightRecordForm`のブロックを削除する**

`resources/js/components/record/recordContents.vue`の以下の部分(現在45-51行目)を削除する。

削除前:
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
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の合計セット数：{{ thisTotalSet }}
              </p>
```

削除後:
```html
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の体重：{{ bodyWeight }}
              </p>
              <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                今回の合計セット数：{{ thisTotalSet }}
              </p>
```

- [ ] **Step 2: `<script setup>`から`WeightRecordForm`関連のimportと未使用コードを削除する**

以下の行(現在152行目)を削除する。

```typescript
import WeightRecordForm from "../weight/WeightRecordForm.vue";
```

以下の行(現在205-209行目)を削除する。

```typescript
const recordedAtParam: ComputedRef<string> = computed(() => route.params.recordId as string);

const onWeightSaved = (): void => {
  store.commit("invalidateLatestRecordState");
};
```

`import { ref, onMounted, computed, watch, ComputedRef } from "vue";`はこのファイルの他の箇所(`dispModal`)で`computed`/`ComputedRef`を使い続けるため、削除しない。

- [ ] **Step 3: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する(`WeightRecordForm.vue`自体は`weightManagement.vue`から引き続き使われるため、コンポーネントファイル自体は削除しない)

---

## Task 12: 共通ローディングスピナーコンポーネント

**Files:**
- Create: `resources/js/components/common/LoadingSpinner.vue`

- [ ] **Step 1: コンポーネントを作成する**

```vue
<template>
  <div class="flex justify-center items-center py-8">
    <div
      class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"
      role="status"
      aria-label="読み込み中"
    ></div>
  </div>
</template>

<script setup lang="ts"></script>
```

- [ ] **Step 2: 型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep -i "LoadingSpinner" || echo "NO_SPINNER_TYPE_ERROR"`

Expected: `NO_SPINNER_TYPE_ERROR`

---

## Task 13: 既存3画面のローディング表示をスピナーに置換

**Files:**
- Modify: `resources/js/components/record/recordContents.vue`
- Modify: `resources/js/views/ranking/userRecordRanking.vue`
- Modify: `resources/js/components/record/Calendar.vue`

- [ ] **Step 1: `recordContents.vue`のローディングテキストを置き換える**

`resources/js/components/record/recordContents.vue`の以下の部分を変更する。

変更前:
```html
    <template v-else>
      <p class="mx-auto mt-10 md:w-6/12 w-11/12 mb-5 font-bold text-center">
        データ取得中です。しばらくお待ちください。
      </p>
    </template>
```

変更後:
```html
    <template v-else>
      <LoadingSpinner />
    </template>
```

`<script setup lang="ts">`の先頭付近(既存の`import HistoryRecordContents`の直後)に追加する。

```typescript
import LoadingSpinner from "../common/LoadingSpinner.vue";
```

- [ ] **Step 2: `userRecordRanking.vue`のローディングテキストを置き換える**

`resources/js/views/ranking/userRecordRanking.vue`の以下の部分を変更する。

変更前:
```html
    <template v-else>
      <p class="mx-auto mt-10 md:w-6/12 w-11/12 mb-5 font-bold md:text-center">
        データ取得中です。しばらくお待ちください。
      </p>
    </template>
```

変更後:
```html
    <template v-else>
      <LoadingSpinner />
    </template>
```

`<script setup lang="ts">`の先頭のimport群に追加する。

```typescript
import LoadingSpinner from "../../components/common/LoadingSpinner.vue";
```

- [ ] **Step 3: `Calendar.vue`のローディングテキストを置き換える**

`resources/js/components/record/Calendar.vue`の以下の部分(現在521-523行目)を変更する。

変更前:
```html
    <template v-else>
      <p class="text-center mt-5">データ読み込み中です。少々お待ちください。</p>
    </template>
```

変更後:
```html
    <template v-else>
      <LoadingSpinner />
    </template>
```

`<script setup lang="ts">`のimport群に追加する(このファイルのimportブロックを確認し、他のコンポーネントimportと同じ場所に追加する)。

```typescript
import LoadingSpinner from "../common/LoadingSpinner.vue";
```

- [ ] **Step 4: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

---

## Task 14: `weightManagement.vue`へのローディングスピナー追加

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`

- [ ] **Step 1: `isLoading`状態を追加し、初回データ取得完了までスピナーを表示する**

`resources/js/views/weight/weightManagement.vue`の`<template>`全体を`isLoading`で出し分けるよう変更する。

```html
<template>
  <div class="max-w-3xl mx-auto mt-8 px-2">
    <h2 class="text-xl font-bold mb-4">体重管理</h2>

    <LoadingSpinner v-if="isLoading" />
    <template v-else>
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

      <WeightTargetSetting
        :targetWeight="targetWeight"
        :latestBodyWeight="latestBodyWeight"
        @updated="onTargetWeightUpdated"
      />

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
    </template>
  </div>
</template>
```

`<script setup>`に以下を追加する(既存の`import { computed, ComputedRef, onMounted, ref, Ref } from "vue";`に合わせて追加、重複させない)。

```typescript
import LoadingSpinner from "../../components/common/LoadingSpinner.vue";
```

`selectedMonths`宣言の直後あたりに追加する。

```typescript
const isLoading: Ref<boolean> = ref(true);
```

`onMounted`を以下のように変更する。

```typescript
onMounted(async () => {
  await fetchHistory();
  await fetchTagStats();
  isLoading.value = false;
});
```

- [ ] **Step 2: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、`/weight`にアクセスした瞬間にスピナーが表示され、データ取得後に本来のコンテンツに切り替わることを確認する。

---

## Task 15: `weightManagement.vue`への日付選択機能追加

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`

- [ ] **Step 1: 日付input・見出し動的化・当日以外の記録読み込みを実装する**

`resources/js/views/weight/weightManagement.vue`の`<template>`、期間切替ボタンの直後に日付選択欄を追加する。

```html
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">日付</label>
        <input type="date" v-model="selectedDate" class="border p-1" />
      </div>
```

「今日の体重を記録」セクションを以下のように変更する。

```html
      <div class="mt-6">
        <h3 class="font-semibold mb-2">{{ formTitle }}</h3>
        <WeightRecordForm
          :key="selectedDate"
          :recordedAt="selectedDate"
          :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
          :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
          :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
          @saved="onSaved"
        />
      </div>
```

`<script setup>`のimportに`watch`を追加する(既存の`import { computed, ComputedRef, onMounted, ref, Ref } from "vue";`を以下に変更)。

```typescript
import { computed, ComputedRef, onMounted, ref, Ref, watch } from "vue";
```

`today`定義の直後に以下を追加する。

```typescript
const selectedDate: Ref<string> = ref(today);
const selectedDateRecord: Ref<WeightRecord | null> = ref(null);

const formTitle: ComputedRef<string> = computed(() => {
  return selectedDate.value === today ? "今日の体重を記録" : `${selectedDate.value}の体重を記録`;
});

const fetchSelectedDateRecord = async (): Promise<void> => {
  await axios
    .get("/api/weight", { params: { from: selectedDate.value, to: selectedDate.value } })
    .then((res) => {
      selectedDateRecord.value = res.data.records.length > 0 ? res.data.records[0] : null;
    })
    .catch(() => {
      selectedDateRecord.value = null;
    });
};

watch(selectedDate, async () => {
  await fetchSelectedDateRecord();
});
```

`onSaved`関数を、保存後に選択中の日付データも再取得するよう変更する。

```typescript
const onSaved = async (): Promise<void> => {
  await fetchHistory();
  await fetchTagStats();
  await fetchSelectedDateRecord();
};
```

`onMounted`に初回の`fetchSelectedDateRecord`呼び出しを追加する。

```typescript
onMounted(async () => {
  await fetchHistory();
  await fetchTagStats();
  await fetchSelectedDateRecord();
  isLoading.value = false;
});
```

- [ ] **Step 2: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- `/weight`で日付を過去に変更すると、その日に記録があればフォームに反映される
- 記録がない日を選ぶと空のフォームになる
- 日付を変更して保存すると、その日付の`record_states`に保存され、グラフにも反映される

---

## Task 16: タグ追加・削除のcomposables

**Files:**
- Create: `resources/js/composables/weight/usePostWeightTag.ts`
- Create: `resources/js/composables/weight/useDeleteWeightTag.ts`

- [ ] **Step 1: `usePostWeightTag.ts`を作成する**

```typescript
import { ref, Ref } from "vue";
import axios from "axios";
import { WeightTag } from "../../types/weight";

export default function usePostWeightTag() {
  const isSaving: Ref<boolean> = ref(false);

  const postWeightTag = async (content: string): Promise<WeightTag | null> => {
    isSaving.value = true;
    return await axios
      .post("/api/weight/tags", { content })
      .then((res) => res.data.tag as WeightTag)
      .catch(() => null)
      .finally(() => {
        isSaving.value = false;
      });
  };

  return { isSaving, postWeightTag };
}
```

- [ ] **Step 2: `useDeleteWeightTag.ts`を作成する**

```typescript
import { ref, Ref } from "vue";
import axios from "axios";

export default function useDeleteWeightTag() {
  const isDeleting: Ref<boolean> = ref(false);

  const deleteWeightTag = async (tagId: number): Promise<void> => {
    isDeleting.value = true;
    await axios.delete(`/api/weight/tags/${tagId}`).catch(() => {});
    isDeleting.value = false;
  };

  return { isDeleting, deleteWeightTag };
}
```

- [ ] **Step 3: 型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep -iE "usePostWeightTag|useDeleteWeightTag" || echo "NO_TAG_COMPOSABLE_TYPE_ERROR"`

Expected: `NO_TAG_COMPOSABLE_TYPE_ERROR`

---

## Task 17: タグ編集コンポーネント

**Files:**
- Create: `resources/js/components/weight/WeightTagEditor.vue`

- [ ] **Step 1: コンポーネントを作成する**

```vue
<template>
  <div class="border p-3 rounded mb-4">
    <h3 class="font-semibold mb-2">タグを編集</h3>
    <div class="flex flex-wrap gap-2 mb-2">
      <span
        v-for="tag in tags"
        :key="tag.id"
        class="flex items-center gap-1 px-2 py-1 bg-gray-200 rounded text-sm"
      >
        {{ tag.content }}
        <button type="button" class="text-red-500" @click="remove(tag.id)">×</button>
      </span>
    </div>
    <div v-if="tags.length < 5" class="flex gap-2">
      <input
        type="text"
        class="border p-1 flex-1"
        v-model="newTagContent"
        placeholder="新しいタグ名"
      />
      <button
        type="button"
        class="bg-blue-500 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded"
        @click="add"
      >
        追加する
      </button>
    </div>
    <p v-else class="text-sm text-gray-500">タグは5個までです。</p>
    <p v-if="errorMessage" class="text-sm text-red-500 mt-1">{{ errorMessage }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, Ref } from "vue";
import { WeightTag } from "../../types/weight";
import usePostWeightTag from "../../composables/weight/usePostWeightTag";
import useDeleteWeightTag from "../../composables/weight/useDeleteWeightTag";

const props = defineProps<{
  tags: WeightTag[];
}>();

const emits = defineEmits<{
  (e: "changed"): void;
}>();

const newTagContent: Ref<string> = ref("");
const errorMessage: Ref<string> = ref("");

const { postWeightTag } = usePostWeightTag();
const { deleteWeightTag } = useDeleteWeightTag();

const add = async (): Promise<void> => {
  if (newTagContent.value === "") {
    return;
  }
  errorMessage.value = "";
  const result = await postWeightTag(newTagContent.value);
  if (result === null) {
    errorMessage.value = "タグは5個までです。";
    return;
  }
  newTagContent.value = "";
  emits("changed");
};

const remove = async (tagId: number): Promise<void> => {
  await deleteWeightTag(tagId);
  emits("changed");
};
</script>
```

Note: `props.tags`は親コンポーネントから渡されるため、テンプレート内で`tags`として直接参照できる(`<script setup>`の分割代入不要、Vueが自動でtemplateスコープに公開する)。

- [ ] **Step 2: 型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep -i "WeightTagEditor" || echo "NO_EDITOR_TYPE_ERROR"`

Expected: `NO_EDITOR_TYPE_ERROR`

---

## Task 18: `weightManagement.vue`へのタグ編集セクション組み込み

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`

- [ ] **Step 1: `WeightTagEditor`を組み込み、タグ変更時にタグ一覧と`WeightRecordForm`を更新する**

`resources/js/views/weight/weightManagement.vue`の`<template>`、`WeightTagStats`の直後に追加する。

```html
      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />
```

`WeightRecordForm`の`:key`を、日付とタグ変更版数の組み合わせに変更する(タグ編集後にコンポーネントを再マウントしてタグ一覧を再読込させるため)。

```html
        <WeightRecordForm
          :key="`${selectedDate}-${tagsVersion}`"
          :recordedAt="selectedDate"
          :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
          :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
          :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
          @saved="onSaved"
        />
```

`<script setup>`に以下を追加する。

```typescript
import WeightTagEditor from "../../components/weight/WeightTagEditor.vue";
import useGetWeightTags from "../../composables/weight/useGetWeightTags";
```

`selectedDateRecord`宣言の直後に追加する。

```typescript
const { weightTags, getWeightTags } = useGetWeightTags();
const tagsVersion: Ref<number> = ref(0);

const onTagsChanged = async (): Promise<void> => {
  await getWeightTags();
  tagsVersion.value++;
};
```

`onMounted`に`getWeightTags()`呼び出しを追加する。

```typescript
onMounted(async () => {
  await fetchHistory();
  await fetchTagStats();
  await fetchSelectedDateRecord();
  await getWeightTags();
  isLoading.value = false;
});
```

- [ ] **Step 2: ビルドが通ることを確認する**

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- `/weight`に「タグを編集」セクションが表示され、既存4タグが表示される
- 新規タグを1個追加できる(5個目)
- 5個の状態で追加フォームが非表示になり「タグは5個までです」が表示される
- タグを1個削除すると、一覧・体重記録フォームのチェックボックス両方に反映される

---

## Task 19: 最終ビルド・型チェック・バックエンド全体確認

**Files:**
- なし(確認のみ)

- [ ] **Step 1: フロントエンドの型チェックとビルドを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 2: バックエンドの全テストを実行する**

Run: `docker exec trainingmemoapp-app-1 php artisan test`

Expected: 全件PASS

---

## 最終コミット

すべてのタスク完了後、以下を実行する。

```bash
git add -A
git commit -m "fix: 体重管理機能の改善(UI/UX復元・ローディング表示・日付選択・タグ編集・新規登録デフォルトデータ)"
```

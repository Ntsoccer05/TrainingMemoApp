# 体重管理ページ レイアウト・目標体重機能 改善 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 体重管理ページ(`/weight`)で、体重記録フォームを最上部に配置し、目標体重に「現状との差分」表示と「任意の期限日」を追加し、グラフの目標体重ラインが常に見えるようY軸範囲を修正する。

**Architecture:** バックエンドは`users`テーブルに`target_weight_date`(nullable date)を追加し、既存の`WeightService::updateTargetWeight`・`WeightController`のtarget_weight関連エンドポイントを拡張する。フロントエンドは`WeightTargetSetting.vue`に期限日入力と差分表示を追加し、`weightManagement.vue`のテンプレート内の要素順序を並べ替え、`WeightChart.vue`のY軸を目標体重込みで計算するよう修正する。

**Tech Stack:** Laravel 9(PHP)、Vue 3 + TypeScript(Composition API)、dayjs

---

## Task 1: `users`テーブルへの`target_weight_date`カラム追加とUserモデル更新

**Files:**
- Create: `database/migrations/2026_07_28_000001_add_target_weight_date_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/WeightMigrationsTest.php`

- [ ] **Step 1: マイグレーションの失敗するテストを書く**

`tests/Feature/WeightMigrationsTest.php`に以下のテストメソッドを追加する(既存のクラス内、最後のメソッドの後に追加)。

```php
    public function test_users_table_has_target_weight_date_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'target_weight_date'));
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightMigrationsTest.php`

Expected: FAIL(`target_weight_date`カラムが存在しない)

- [ ] **Step 3: マイグレーションを作成する**

`database/migrations/2026_07_28_000001_add_target_weight_date_to_users_table.php`を新規作成する。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('target_weight_date')->nullable()->after('target_weight');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('target_weight_date');
        });
    }
};
```

- [ ] **Step 4: `User`モデルの`$fillable`と`$casts`を更新する**

`app/Models/User.php`の以下の部分(現在27-33行目・50-52行目)を変更する。

変更前:
```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'target_weight'
    ];
```

変更後:
```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'target_weight',
        'target_weight_date'
    ];
```

変更前:
```php
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
```

変更後:
```php
    protected $casts = [
        'email_verified_at' => 'datetime',
        'target_weight_date' => 'date',
    ];
```

- [ ] **Step 5: マイグレーションを実行し、テストがパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan migrate`

Expected: `2026_07_28_000001_add_target_weight_date_to_users_table`が実行される

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightMigrationsTest.php`

Expected: PASS(6件成功)

---

## Task 2: `WeightService::updateTargetWeight`の`target_weight_date`対応

**Files:**
- Modify: `app/Services/Weight/WeightService.php:106-120`
- Test: `tests/Feature/Services/Weight/WeightServiceTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Services/Weight/WeightServiceTest.php`の`test_update_target_weight_sets_users_target_weight`メソッドの直後に、以下の2つのテストメソッドを追加する。

```php
    public function test_update_target_weight_sets_users_target_weight_date(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->updateTargetWeight($user->id, 60.0, '2026-12-31');

        $this->assertEquals('2026-12-31', $result->target_weight_date->format('Y-m-d'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight_date' => '2026-12-31']);
    }

    public function test_update_target_weight_allows_null_target_weight_date(): void
    {
        $user = User::factory()->create();
        $service = new WeightService();

        $result = $service->updateTargetWeight($user->id, 60.0, null);

        $this->assertNull($result->target_weight_date);
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: FAIL(`updateTargetWeight()`は現在2引数までしか受け付けない)

- [ ] **Step 3: `WeightService::updateTargetWeight`を変更する**

`app/Services/Weight/WeightService.php`の以下の部分(現在106-120行目)を変更する。

変更前:
```php
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

変更後:
```php
    /**
     * ユーザーの目標体重と期限日を更新する。
     *
     * @param int $userId
     * @param float $targetWeight
     * @param string|null $targetWeightDate
     * @return User
     */
    public function updateTargetWeight(int $userId, float $targetWeight, ?string $targetWeightDate = null): User
    {
        $user = User::findOrFail($userId);
        $user->target_weight = $targetWeight;
        $user->target_weight_date = $targetWeightDate;
        $user->save();

        return $user;
    }
```

- [ ] **Step 4: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/Services/Weight/WeightServiceTest.php`

Expected: PASS(21件成功、既存の`test_update_target_weight_sets_users_target_weight`も引き続き成功する。この既存テストは`updateTargetWeight($user->id, 60.0)`と2引数で呼んでいるが、第3引数`$targetWeightDate`はデフォルト値`null`があるため互換性が保たれる)

---

## Task 3: `UpdateTargetWeightRequest`・`WeightController`の`target_weight_date`対応

**Files:**
- Modify: `app/Http/Requests/Weight/UpdateTargetWeightRequest.php`
- Modify: `app/Http/Controllers/WeightController.php:30-43,88-96`
- Test: `tests/Feature/WeightControllerTest.php`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/WeightControllerTest.php`の`test_update_target_weight`メソッドの直後に、以下の3つのテストメソッドを追加する。

```php
    public function test_update_target_weight_saves_target_weight_date(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/weight/targetWeight', [
            'target_weight' => 58.0,
            'target_weight_date' => '2026-12-31',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('target_weight_date', '2026-12-31');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'target_weight_date' => '2026-12-31']);
    }

    public function test_update_target_weight_allows_omitting_target_weight_date(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/weight/targetWeight', ['target_weight' => 58.0]);

        $response->assertStatus(200)
            ->assertJsonPath('target_weight_date', null);
    }

    public function test_index_returns_target_weight_date(): void
    {
        $user = $this->actingAsUser();
        $user->target_weight = 60.0;
        $user->target_weight_date = '2026-12-31';
        $user->save();

        $response = $this->getJson('/api/weight?from=2026-07-01&to=2026-07-31');

        $response->assertStatus(200)->assertJsonPath('target_weight_date', '2026-12-31');
    }
```

- [ ] **Step 2: テストを実行して失敗することを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: FAIL(レスポンスに`target_weight_date`キーが存在しない)

- [ ] **Step 3: `UpdateTargetWeightRequest`にバリデーションルールを追加する**

`app/Http/Requests/Weight/UpdateTargetWeightRequest.php`の`rules()`メソッド(現在14-19行目)を変更する。

変更前:
```php
    public function rules()
    {
        return [
            'target_weight' => 'required|numeric|min:0|max:999.9',
        ];
    }
```

変更後:
```php
    public function rules()
    {
        return [
            'target_weight' => 'required|numeric|min:0|max:999.9',
            'target_weight_date' => 'nullable|date_format:Y-m-d',
        ];
    }
```

- [ ] **Step 4: `WeightController`の`updateTargetWeight`・`index`を変更する**

`app/Http/Controllers/WeightController.php`の`index`メソッド(現在30-43行目)を変更する。

変更前:
```php
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
```

変更後:
```php
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
            'target_weight_date' => auth()->user()->target_weight_date?->format('Y-m-d'),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
```

`updateTargetWeight`メソッド(現在88-96行目)を変更する。

変更前:
```php
    public function updateTargetWeight(UpdateTargetWeightRequest $request, WeightService $weightService)
    {
        $user = $weightService->updateTargetWeight(auth()->id(), $request->input('target_weight'));

        return response()->json([
            'status_code' => 200,
            'target_weight' => $user->target_weight,
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
```

変更後:
```php
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
```

- [ ] **Step 5: テストを実行してパスすることを確認する**

Run: `docker exec trainingmemoapp-app-1 php artisan test tests/Feature/WeightControllerTest.php`

Expected: PASS(14件成功)

- [ ] **Step 6: バックエンド全体の回帰確認**

Run: `docker exec trainingmemoapp-app-1 php artisan test`

Expected: 全件PASS(既存の76件+今回追加した5件で81件前後、実行前の件数から今回追加したテスト分だけ増えていること)

---

## Task 4: フロントエンド型定義・composableの`target_weight_date`対応

**Files:**
- Modify: `resources/js/types/weight.d.ts`
- Modify: `resources/js/composables/weight/useGetWeightHistory.ts`

- [ ] **Step 1: `WeightHistoryResponse`型に`target_weight_date`を追加する**

`resources/js/types/weight.d.ts`の`WeightHistoryResponse`型定義(現在14-18行目)を変更する。

変更前:
```typescript
export declare type WeightHistoryResponse = {
    status_code: number,
    records: WeightRecord[],
    target_weight: number | null,
};
```

変更後:
```typescript
export declare type WeightHistoryResponse = {
    status_code: number,
    records: WeightRecord[],
    target_weight: number | null,
    target_weight_date: string | null,
};
```

- [ ] **Step 2: `useGetWeightHistory`に`targetWeightDate`を追加する**

`resources/js/composables/weight/useGetWeightHistory.ts`の全体を以下に変更する。

```typescript
import { ref, Ref } from "vue";
import axios from "axios";
import { WeightRecord } from "../../types/weight";

export default function useGetWeightHistory() {
  const weightRecords: Ref<WeightRecord[]> = ref([]);
  const targetWeight: Ref<number | null> = ref(null);
  const targetWeightDate: Ref<string | null> = ref(null);

  const getWeightHistory = async (from?: string, to?: string): Promise<void> => {
    await axios
      .get("/api/weight", {
        params: from && to ? { from, to } : {},
      })
      .then((res) => {
        weightRecords.value = res.data.records;
        targetWeight.value = res.data.target_weight;
        targetWeightDate.value = res.data.target_weight_date;
      })
      .catch(() => {
        weightRecords.value = [];
        targetWeight.value = null;
        targetWeightDate.value = null;
      });
  };

  return { weightRecords, targetWeight, targetWeightDate, getWeightHistory };
}
```

- [ ] **Step 3: 型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし(`useGetWeightHistory`の戻り値に`targetWeightDate`が追加されたことで、これを使う`weightManagement.vue`側は次のTaskで対応するため、この時点では`weightManagement.vue`が分割代入で`targetWeightDate`を受け取っていなくても型エラーにはならない)

---

## Task 5: `WeightTargetSetting.vue`に期限日入力・差分表示を追加

**Files:**
- Modify: `resources/js/components/weight/WeightTargetSetting.vue`

- [ ] **Step 1: コンポーネント全体を変更する**

`resources/js/components/weight/WeightTargetSetting.vue`の現在の内容:

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

これを以下に全置換する。

```vue
<template>
  <div class="border p-3 rounded mb-4">
    <label class="block text-sm font-medium mb-1">目標体重(kg)</label>
    <div class="flex gap-2 items-center flex-wrap">
      <input
        type="text"
        class="border p-1 w-24"
        v-model="targetWeightInput"
        placeholder="例: 60"
      />
      <label class="text-sm text-gray-600">期限(任意)</label>
      <input type="date" class="border p-1" v-model="targetWeightDateInput" />
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
    <p v-if="diffText" class="text-sm text-gray-600 mt-2">{{ diffText }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ComputedRef, ref, Ref, watch } from "vue";
import axios from "axios";
import dayjs from "dayjs";

const props = defineProps<{
  targetWeight: number | null;
  targetWeightDate: string | null;
  latestBodyWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "updated", value: { targetWeight: number; targetWeightDate: string | null }): void;
}>();

const targetWeightInput: Ref<string> = ref(props.targetWeight?.toString() ?? "");
const targetWeightDateInput: Ref<string> = ref(props.targetWeightDate ?? "");

watch(
  () => props.targetWeight,
  (value) => {
    targetWeightInput.value = value?.toString() ?? "";
  }
);

watch(
  () => props.targetWeightDate,
  (value) => {
    targetWeightDateInput.value = value ?? "";
  }
);

const isAchieved: ComputedRef<boolean> = computed(() => {
  if (props.targetWeight === null || props.latestBodyWeight === null) {
    return false;
  }
  return Math.abs(props.latestBodyWeight - props.targetWeight) <= 0.5;
});

const diffText: ComputedRef<string> = computed(() => {
  if (props.targetWeight === null || props.latestBodyWeight === null) {
    return "";
  }

  const diff = Math.round((props.targetWeight - props.latestBodyWeight) * 10) / 10;
  const diffLabel = diff > 0 ? `+${diff}` : `${diff}`;
  let text = `現在: ${props.latestBodyWeight}kg／目標まで: ${diffLabel}kg`;

  if (props.targetWeightDate) {
    const daysLeft = dayjs(props.targetWeightDate)
      .startOf("day")
      .diff(dayjs().startOf("day"), "day");
    if (daysLeft >= 0) {
      text += `(残り${daysLeft}日)`;
    }
  }

  return text;
});

const save = async (): Promise<void> => {
  if (targetWeightInput.value === "") {
    return;
  }
  const value = parseFloat(targetWeightInput.value);
  const dateValue = targetWeightDateInput.value === "" ? null : targetWeightDateInput.value;
  await axios
    .post("/api/weight/targetWeight", { target_weight: value, target_weight_date: dateValue })
    .then(() => {
      emits("updated", { targetWeight: value, targetWeightDate: dateValue });
    })
    .catch(() => {});
};
</script>
```

- [ ] **Step 2: 型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep -i "WeightTargetSetting" || echo "NO_TARGET_SETTING_TYPE_ERROR"`

Expected: `WeightTargetSetting`を使う`weightManagement.vue`側がまだ`targetWeightDate` propと新しい`updated`イベントの形に対応していないため、この時点ではエラーが出る想定。次のTaskで`weightManagement.vue`を修正した後に解消する。ここでは`WeightTargetSetting.vue`自体の記述に構文エラーがないことのみ、`npm run build`で確認する。

Run: `cd src && npm run build`

Expected: ビルド自体はTypeScriptの型エラーで失敗する可能性がある(props不一致のため)。次のTaskで解消されることを前提に進める。

---

## Task 6: `weightManagement.vue`のレイアウト変更と状態管理更新

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`

- [ ] **Step 1: テンプレートの要素順序を変更する**

`resources/js/views/weight/weightManagement.vue`の現在の`<template>`全体:

```vue
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

      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">日付</label>
        <input type="date" v-model="selectedDate" class="border p-1" />
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

      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />

      <div class="mt-6">
        <h3 class="font-semibold mb-2">{{ formTitle }}</h3>
        <WeightRecordForm
          :key="`${selectedDate}-${tagsVersion}`"
          :recordedAt="selectedDate"
          :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
          :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
          :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
          @saved="onSaved"
        />
      </div>

      <WeightRecordModal v-model="showModal" :record="selectedRecord" />
    </template>
  </div>
</template>
```

これを以下に全置換する(日付選択と記録フォームを最上部に移動し、`WeightTargetSetting`に`targetWeightDate`propを追加)。

```vue
<template>
  <div class="max-w-3xl mx-auto mt-8 px-2">
    <h2 class="text-xl font-bold mb-4">体重管理</h2>

    <LoadingSpinner v-if="isLoading" />
    <template v-else>
      <div class="mb-4">
        <label class="block text-sm font-medium mb-1">日付</label>
        <input type="date" v-model="selectedDate" class="border p-1" />
      </div>

      <div class="mb-6">
        <h3 class="font-semibold mb-2">{{ formTitle }}</h3>
        <WeightRecordForm
          :key="`${selectedDate}-${tagsVersion}`"
          :recordedAt="selectedDate"
          :initialBodyWeight="selectedDateRecord ? selectedDateRecord.bodyWeight : null"
          :initialMemo="selectedDateRecord ? selectedDateRecord.weight_memo : null"
          :initialTagIds="selectedDateRecord ? selectedDateRecord.weight_tags.map((t) => t.id) : []"
          @saved="onSaved"
        />
      </div>

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
        :targetWeightDate="targetWeightDate"
        :latestBodyWeight="latestBodyWeight"
        @updated="onTargetWeightUpdated"
      />

      <WeightChart
        :records="weightRecords"
        :targetWeight="targetWeight"
        @pointClick="openRecordModal"
      />

      <WeightTagStats :stats="tagStats" />

      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />

      <WeightRecordModal v-model="showModal" :record="selectedRecord" />
    </template>
  </div>
</template>
```

- [ ] **Step 2: `<script setup>`を変更する**

現在の`<script setup>`全体:

```typescript
import { computed, ComputedRef, onMounted, ref, Ref, watch } from "vue";
import dayjs from "dayjs";
import useGetWeightHistory from "../../composables/weight/useGetWeightHistory";
import axios from "axios";
import LoadingSpinner from "../../components/common/LoadingSpinner.vue";
import WeightChart from "../../components/weight/WeightChart.vue";
import WeightTagStats from "../../components/weight/WeightTagStats.vue";
import WeightRecordForm from "../../components/weight/WeightRecordForm.vue";
import WeightRecordModal from "../../components/weight/WeightRecordModal.vue";
import WeightTargetSetting from "../../components/weight/WeightTargetSetting.vue";
import WeightTagEditor from "../../components/weight/WeightTagEditor.vue";
import { WeightRecord, TagStatistic } from "../../types/weight";
import { setSeo } from "../../utils/setSeo";
import useGetWeightTags from "../../composables/weight/useGetWeightTags";

setSeo("weight");

const today = dayjs().format("YYYY-MM-DD");

const selectedDate: Ref<string> = ref(today);
const selectedDateRecord: Ref<WeightRecord | null> = ref(null);

const { weightTags, getWeightTags } = useGetWeightTags();
const tagsVersion: Ref<number> = ref(0);

const onTagsChanged = async (): Promise<void> => {
  await getWeightTags();
  tagsVersion.value++;
};

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

const periodOptions = [
  { label: "1ヶ月", months: 1 },
  { label: "3ヶ月", months: 3 },
  { label: "6ヶ月", months: 6 },
];
const selectedMonths: Ref<number> = ref(1);
const isLoading: Ref<boolean> = ref(true);

const { weightRecords, targetWeight, getWeightHistory } = useGetWeightHistory();
const tagStats: Ref<TagStatistic[]> = ref([]);

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

const onTargetWeightUpdated = (value: number): void => {
  targetWeight.value = value;
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
  await fetchSelectedDateRecord();
};

onMounted(async () => {
  try {
    await fetchHistory();
    await fetchTagStats();
    await fetchSelectedDateRecord();
    await getWeightTags();
  } finally {
    isLoading.value = false;
  }
});
```

`useGetWeightHistory()`の分割代入(`const { weightRecords, targetWeight, getWeightHistory } = useGetWeightHistory();`)を以下に変更する。

```typescript
const { weightRecords, targetWeight, targetWeightDate, getWeightHistory } = useGetWeightHistory();
```

`onTargetWeightUpdated`関数を以下に変更する。

変更前:
```typescript
const onTargetWeightUpdated = (value: number): void => {
  targetWeight.value = value;
};
```

変更後:
```typescript
const onTargetWeightUpdated = (value: { targetWeight: number; targetWeightDate: string | null }): void => {
  targetWeight.value = value.targetWeight;
  targetWeightDate.value = value.targetWeightDate;
};
```

- [ ] **Step 3: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 4: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- `/weight`にアクセスした際、ページ最上部に日付選択と体重記録フォームが表示される
- 期間切替ボタン・目標体重設定・グラフはその下に続けて表示される
- 目標体重を設定すると、設定欄の下に「現在: ○kg／目標まで: ±○kg」の差分テキストが表示される
- 目標体重の期限日を設定すると、差分テキストに「(残り○日)」が併記される
- 期限日を今日より前の日付に設定すると、「(残り○日)」の表記が出ない(差分テキスト自体は表示される)

---

## Task 7: `WeightChart.vue`のY軸範囲修正

**Files:**
- Modify: `resources/js/components/weight/WeightChart.vue`

- [ ] **Step 1: Y軸のmin/maxを目標体重込みで計算するcomputedを追加する**

`resources/js/components/weight/WeightChart.vue`の現在の内容:

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

`<script setup>`部分を以下に全置換する。

```typescript
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

const yAxisRange: ComputedRef<{ min?: number; max?: number }> = computed(() => {
  const values: number[] = props.records
    .map((r) => r.bodyWeight)
    .filter((v): v is number => v !== null);

  if (props.targetWeight !== null) {
    values.push(props.targetWeight);
  }

  if (values.length === 0) {
    return {};
  }

  const min = Math.min(...values);
  const max = Math.max(...values);

  return {
    min: Math.floor((min - 1) * 10) / 10,
    max: Math.ceil((max + 1) * 10) / 10,
  };
});

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
      ...yAxisRange.value,
      labels: {
        formatter: (val: number) => `${val}kg`,
      },
    },
    stroke: { curve: "smooth", width: 2 },
    markers: { size: 4 },
    annotations,
  };
});
```

(`<template>`部分は変更しない)

- [ ] **Step 2: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- 体重記録データ(例: 65.5kg付近)がある状態で、目標体重を実データから離れた値(例: 60kg)に設定する
- グラフのY軸範囲が自動的に拡張され、目標体重のオレンジ色の注釈線とラベル「目標体重 60kg」が表示される

---

## Task 8: 全体確認

**Files:**
- なし(確認のみ)

- [ ] **Step 1: バックエンド全体のテストを実行する**

Run: `docker exec trainingmemoapp-app-1 php artisan test`

Expected: 全件PASS

- [ ] **Step 2: フロントエンドの型チェックとビルドを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

---

## 最終コミット

すべてのタスク完了後、以下を実行する。

```bash
git add -A
git commit -m "feat: 体重管理ページのレイアウト変更と目標体重の期限・差分表示機能を追加"
```

# 体重管理ページ コンパクトレイアウト改善 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 体重管理ページ(`/weight`)で、期間選択・メモ開閉状態をlocalStorageに保存し、体重記録フォームをコンパクト化(入力欄幅縮小・タグのチップ化・メモ折りたたみ)し、デスクトップ幅では2カラムレイアウト、グラフ高さはモバイル幅で圧縮するレスポンシブ対応を行う。

**Architecture:** すべてフロントエンドのみの変更。`weightManagement.vue`のテンプレート構造とlocalStorage連携、`WeightRecordForm.vue`のUI圧縮、`WeightTargetSetting.vue`のラベル配置調整、`WeightChart.vue`のレスポンシブ高さ対応の4ファイルを個別タスクとして進める。

**Tech Stack:** Vue 3 + TypeScript(Composition API)、Tailwind CSS、`window.localStorage`、`vue3-apexcharts`

---

## Task 1: 期間切替ボタンの移動とlocalStorage永続化

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`

- [ ] **Step 1: テンプレートで期間切替ボタンをWeightChartの直前に移動する**

`resources/js/views/weight/weightManagement.vue`の`<template v-else>`内、現在の以下の並び:

```html
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
```

を以下に変更する(期間切替ボタンを`WeightTargetSetting`の後、`WeightChart`の直前に移動)。

```html
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

      <WeightTargetSetting
        :targetWeight="targetWeight"
        :targetWeightDate="targetWeightDate"
        :latestBodyWeight="latestBodyWeight"
        @updated="onTargetWeightUpdated"
      />

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
```

- [ ] **Step 2: `selectedMonths`の初期値をlocalStorageから復元する**

`<script setup>`内の以下の行:

```typescript
const selectedMonths: Ref<number> = ref(1);
```

を以下に変更する(この行のすぐ上に定数と関数を追加する)。

```typescript
const PERIOD_STORAGE_KEY = "weightManagement.selectedMonths";

const getInitialSelectedMonths = (): number => {
  const stored = localStorage.getItem(PERIOD_STORAGE_KEY);
  const parsed = stored ? Number(stored) : NaN;
  return [1, 3, 6].includes(parsed) ? parsed : 1;
};

const selectedMonths: Ref<number> = ref(getInitialSelectedMonths());
```

- [ ] **Step 3: `changePeriod`で選択をlocalStorageに保存する**

以下の関数:

```typescript
const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  await fetchHistory();
};
```

を以下に変更する。

```typescript
const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  localStorage.setItem(PERIOD_STORAGE_KEY, String(months));
  await fetchHistory();
};
```

- [ ] **Step 4: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 5: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- `/weight`にアクセスし、期間切替ボタンがグラフの直前(目標体重設定の下)に表示されている
- 「3ヶ月」を選択してからページをリロードし、「3ヶ月」が選択された状態のまま復元される

---

## Task 2: `WeightRecordForm.vue`のコンパクト化(体重入力欄・タグチップ化・メモ開閉)

**Files:**
- Modify: `resources/js/components/weight/WeightRecordForm.vue`

- [ ] **Step 1: コンポーネント全体を変更する**

`resources/js/components/weight/WeightRecordForm.vue`の現在の内容:

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

これを以下に全置換する。

```vue
<template>
  <div class="border p-3 rounded">
    <div class="mb-2 flex items-center gap-2">
      <label class="text-sm font-medium whitespace-nowrap">体重(kg)</label>
      <input
        type="text"
        class="border p-1 w-32"
        placeholder="例: 65.5"
        v-model="bodyWeightInput"
      />
    </div>
    <div class="mb-2">
      <label class="block text-sm font-medium mb-1">タグ</label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="tag in weightTags"
          :key="tag.id"
          type="button"
          class="px-2 py-1 rounded text-sm border"
          :class="
            selectedTagIds.includes(tag.id)
              ? 'bg-blue-500 text-white border-blue-500'
              : 'bg-gray-100 text-gray-700 border-gray-300'
          "
          @click="toggleTag(tag.id)"
        >
          {{ tag.content }}
        </button>
      </div>
    </div>
    <div class="mb-2">
      <button
        type="button"
        class="text-sm font-medium text-blue-600 flex items-center gap-1"
        @click="toggleMemo"
      >
        メモ{{ memoInput ? "(入力あり)" : "" }} {{ isMemoExpanded ? "▲" : "▼" }}
      </button>
      <textarea
        v-if="isMemoExpanded"
        class="border w-full p-1 mt-1"
        rows="3"
        v-model="memoInput"
      ></textarea>
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

onMounted(async () => {
  await getWeightTags();
});
</script>
```

- [ ] **Step 2: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- 「体重(kg)」ラベルとinputが横並びで表示され、inputの幅が縮小されている
- タグがチェックボックスではなくクリック可能なチップ(ボタン)として表示され、クリックで選択(青背景)/解除(グレー背景)がトグルできる
- モバイル幅(devtoolsで390×844程度にリサイズ)でページを開くと、メモ欄はデフォルトで折りたたまれている(「メモ ▼」表示、textarea非表示)
- 「メモ ▼」をクリックするとtextareaが展開され、「メモ ▲」に変わる。この状態でページをリロードしても展開状態が維持される
- メモにテキストを入力してから折りたたむと、ラベルが「メモ(入力あり) ▼」になる
- タグを選択し、体重・メモとあわせて保存すると、正しく`POST /api/weight`に反映される(ネットワークタブで`tag_ids`が選択したタグを含むことを確認)

---

## Task 3: `WeightTargetSetting.vue`の目標体重ラベルインライン化

**Files:**
- Modify: `resources/js/components/weight/WeightTargetSetting.vue`

- [ ] **Step 1: テンプレートのラベル配置を変更する**

`resources/js/components/weight/WeightTargetSetting.vue`の`<template>`部分、現在の内容:

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
```

これを以下に変更する(「目標体重(kg)」ラベルを独立したブロックから、`flex`行の中に移動する)。

```vue
<template>
  <div class="border p-3 rounded mb-4">
    <div class="flex gap-2 items-center flex-wrap">
      <label class="text-sm font-medium whitespace-nowrap">目標体重(kg)</label>
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
```

`<script setup>`部分は変更しない。

- [ ] **Step 2: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、「目標体重(kg)」ラベルがinput・期限・ボタンと同じ行に横並びで表示されることを確認する。

---

## Task 4: `weightManagement.vue`の2カラムレスポンシブレイアウト

**Files:**
- Modify: `resources/js/views/weight/weightManagement.vue`

Task 1完了後の`<template v-else>`内の並び順(日付→WeightRecordForm→WeightTargetSetting→期間切替ボタン→WeightChart→WeightTagStats→WeightTagEditor→WeightRecordModal)を前提に、2カラムグリッドで包む。

- [ ] **Step 1: テンプレートを2カラムグリッドに変更する**

`<template v-else>`内の現在の内容(Task 1完了後の状態):

```html
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

      <WeightTargetSetting
        :targetWeight="targetWeight"
        :targetWeightDate="targetWeightDate"
        :latestBodyWeight="latestBodyWeight"
        @updated="onTargetWeightUpdated"
      />

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

      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />

      <WeightRecordModal v-model="showModal" :record="selectedRecord" />
    </template>
```

これを以下に変更する(日付・記録フォーム・目標体重設定を左カラム、期間切替ボタン・グラフを右カラムに配置する`grid`でラップする。タグ集計・タグ編集・モーダルはグリッドの外側、フルwidthのまま)。

```html
    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
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

          <WeightTargetSetting
            :targetWeight="targetWeight"
            :targetWeightDate="targetWeightDate"
            :latestBodyWeight="latestBodyWeight"
            @updated="onTargetWeightUpdated"
          />
        </div>

        <div>
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
        </div>
      </div>

      <WeightTagStats :stats="tagStats" />

      <WeightTagEditor :tags="weightTags" @changed="onTagsChanged" />

      <WeightRecordModal v-model="showModal" :record="selectedRecord" />
    </template>
```

`<script setup>`部分は変更しない。

- [ ] **Step 2: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- デスクトップ幅(1280px程度)で`/weight`を開くと、左に日付・体重記録フォーム・目標体重設定、右に期間切替ボタン・グラフが横並びの2カラムで表示される
- devtoolsでモバイル幅(390×844程度)にリサイズすると、1カラムの縦積みレイアウトに戻る
- タグ別集計・タグ編集セクションは2カラムの下に、幅いっぱいで表示される

---

## Task 5: `WeightChart.vue`のグラフ高さレスポンシブ化

**Files:**
- Modify: `resources/js/components/weight/WeightChart.vue`

- [ ] **Step 1: グラフ高さをウィンドウ幅に応じて動的に変更する**

`resources/js/components/weight/WeightChart.vue`の`<template>`部分、現在の内容:

```html
<template>
  <VueApexCharts type="line" height="280" :options="chartOptions" :series="series" />
</template>
```

を以下に変更する。

```html
<template>
  <VueApexCharts type="line" :height="chartHeight" :options="chartOptions" :series="series" />
</template>
```

`<script setup>`の先頭、現在の内容:

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
```

を以下に変更する(import文に`onMounted`, `onUnmounted`, `ref`, `Ref`を追加し、`emits`定義の直後に高さ管理のコードを挿入する)。

```typescript
import { computed, ComputedRef, onMounted, onUnmounted, ref, Ref } from "vue";
import VueApexCharts from "vue3-apexcharts";
import { WeightRecord } from "../../types/weight";

const props = defineProps<{
  records: WeightRecord[];
  targetWeight: number | null;
}>();

const emits = defineEmits<{
  (e: "pointClick", record: WeightRecord): void;
}>();

const MOBILE_BREAKPOINT = 768;
const MOBILE_CHART_HEIGHT = 220;
const DESKTOP_CHART_HEIGHT = 280;

const chartHeight: Ref<number> = ref(
  window.innerWidth < MOBILE_BREAKPOINT ? MOBILE_CHART_HEIGHT : DESKTOP_CHART_HEIGHT
);

const updateChartHeight = (): void => {
  chartHeight.value =
    window.innerWidth < MOBILE_BREAKPOINT ? MOBILE_CHART_HEIGHT : DESKTOP_CHART_HEIGHT;
};

onMounted(() => {
  window.addEventListener("resize", updateChartHeight);
});

onUnmounted(() => {
  window.removeEventListener("resize", updateChartHeight);
});

const series: ComputedRef<{ name: string; data: (number | null)[] }[]> = computed(() => [
```

`series`以降(`yAxisRange`、`chartOptions`)は変更しない。

- [ ] **Step 2: ビルドと型チェックを確認する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 3: ブラウザで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- デスクトップ幅(768px以上)でグラフの高さが280px程度であることを確認する
- devtoolsでモバイル幅(390×844程度)にリサイズすると、グラフの高さが220px程度に縮小される
- ウィンドウ幅を768pxの境界をまたいでリサイズすると、リロードなしにグラフの高さが切り替わる

---

## Task 6: 全体確認

**Files:**
- なし(確認のみ)

- [ ] **Step 1: フロントエンドの型チェックとビルドを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`

Expected: エラー出力なし

Run: `cd src && npm run build`

Expected: ビルドエラーなく完了する

- [ ] **Step 2: 実ブラウザで一連の操作を通しで確認する**

`chrome-screen-check`スキルを使い、以下を確認する。
- デスクトップ幅: 2カラムレイアウトで日付・体重記録・目標体重(左)とグラフ(右)が並び、期間切替がグラフ直前にある
- モバイル幅(390×844): 1カラムに戻り、メモがデフォルト折りたたみ、グラフの高さが220px程度
- 期間選択・メモ開閉状態がリロード後も維持される
- タグのチップ選択・保存が正しく機能する

---

## 最終コミット

すべてのタスク完了後、以下を実行する。

```bash
git add -A
git commit -m "feat: 体重管理ページをコンパクトレイアウト化(期間・メモ状態のlocalStorage保存、タグチップ化、2カラムレイアウト、グラフ高さレスポンシブ対応)"
```

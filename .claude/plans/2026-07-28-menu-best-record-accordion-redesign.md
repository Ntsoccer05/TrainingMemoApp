# メニュー別最高記録画面 アコーディオンUI刷新 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 「メニュー別最高記録」画面(`/ranking`)を、カテゴリ単位のアコーディオン+種目カードグリッドのUIに刷新し、開閉状態をlocalStorageに永続化する。

**Architecture:** カテゴリ抽出・グルーピング・開閉状態計算のロジックを `resources/js/utils/recordRanking.ts` の純粋関数として切り出し(vitestで単体テスト可能にする)、`useGetRecordRanking.ts` と `userRecordRankingTable.vue` からそれらを呼び出す形にする。`userRecordRankingTable.vue` はテーブルからアコーディオン+カードグリッドの構造に全面的に作り替える。`userRecordRanking.vue` は周辺UI(戻るボタン・凡例・幅)のみ調整する。

**Tech Stack:** Vue 3 (`<script setup lang="ts">`), TypeScript, TailwindCSS, Vitest

参照仕様書: `.claude/specs/2026-07-28-menu-best-record-accordion-redesign-design.md`

---

### Task 1: `CategorySummary` 型の追加

**Files:**
- Modify: `src/resources/js/types/recordRanking.d.ts`

- [ ] **Step 1: 型定義を追加する**

`src/resources/js/types/recordRanking.d.ts` の1行目(`export declare type dispRecordContents ={`)の直前に以下を追加する。

```typescript
export declare type CategorySummary = {
    id: number;
    content: string;
};

```

変更後のファイル冒頭は以下のようになる。

```typescript
export declare type CategorySummary = {
    id: number;
    content: string;
};

export declare type dispRecordContents ={
    category?: Category,
    emptyData?: number,
    menu?: Menu,
    menuBestVolume?: RecordContents,
    bestWeight?: number,
    menuBestRightWeight?: number,
    menuBestRightVolume?: RecordContents,
    menuBestLeftWeight?: number,
    menuBestLeftVolume?: RecordContents,
}[]
```

(以降の `Category` / `Menu` / `RecordContents` の定義は変更しない)

---

### Task 2: カテゴリ抽出・グルーピング・開閉状態計算の純粋関数を作成する(TDD)

**Files:**
- Create: `src/resources/js/utils/recordRanking.ts`
- Test: `src/resources/js/utils/recordRanking.test.ts`

- [ ] **Step 1: 失敗するテストを書く**

`src/resources/js/utils/recordRanking.test.ts` を作成する。

```typescript
import { describe, it, expect } from "vitest";
import { dispRecordContents } from "../types/recordRanking";
import {
  extractCategorySummaries,
  groupContentsByCategory,
  parseStoredClosedCategoryIds,
  toggleCategoryInClosedIds,
  toggleAllClosedCategoryIds,
} from "./recordRanking";

const chestCategory = {
  id: 1,
  content: "胸",
  created_at: "2026-01-01",
  updated_at: "2026-01-01",
  user_id: 1,
};

const backCategory = {
  id: 2,
  content: "背中",
  created_at: "2026-01-01",
  updated_at: "2026-01-01",
  user_id: 1,
};

const benchPressMenu = {
  id: 10,
  content: "ベンチプレス",
  category: chestCategory,
  category_id: 1,
  oneSide: 0,
};

const inclinePressMenu = {
  id: 11,
  content: "インクラインプレス",
  category: chestCategory,
  category_id: 1,
  oneSide: 0,
};

const dumbbellRowMenu = {
  id: 20,
  content: "ダンベルロウ",
  category: backCategory,
  category_id: 2,
  oneSide: 1,
};

const contents: dispRecordContents = [
  { category: chestCategory, menu: benchPressMenu, emptyData: 0, bestWeight: 80 },
  { category: backCategory, menu: dumbbellRowMenu, emptyData: 0 },
  { category: chestCategory, menu: inclinePressMenu, emptyData: 1 },
];

describe("extractCategorySummaries", () => {
  it("最初に出現した順でカテゴリを重複なく抽出する", () => {
    expect(extractCategorySummaries(contents)).toEqual([
      { id: 1, content: "胸" },
      { id: 2, content: "背中" },
    ]);
  });

  it("同一カテゴリが隣接していなくても重複しない", () => {
    const nonAdjacent: dispRecordContents = [
      { category: chestCategory, menu: benchPressMenu, emptyData: 0 },
      { category: backCategory, menu: dumbbellRowMenu, emptyData: 0 },
      { category: chestCategory, menu: inclinePressMenu, emptyData: 1 },
    ];
    expect(extractCategorySummaries(nonAdjacent)).toEqual([
      { id: 1, content: "胸" },
      { id: 2, content: "背中" },
    ]);
  });
});

describe("groupContentsByCategory", () => {
  it("カテゴリIDごとに種目をグルーピングする", () => {
    const grouped = groupContentsByCategory(contents);
    expect(grouped.get(1)).toHaveLength(2);
    expect(grouped.get(2)).toHaveLength(1);
    expect(grouped.get(1)?.map((c) => c.menu.content)).toEqual([
      "ベンチプレス",
      "インクラインプレス",
    ]);
  });

  it("存在しないカテゴリIDにはundefinedを返す", () => {
    const grouped = groupContentsByCategory(contents);
    expect(grouped.get(999)).toBeUndefined();
  });
});

describe("parseStoredClosedCategoryIds", () => {
  it("nullの場合は空配列を返す", () => {
    expect(parseStoredClosedCategoryIds(null)).toEqual([]);
  });

  it("JSON配列文字列をパースして返す", () => {
    expect(parseStoredClosedCategoryIds("[1,2,3]")).toEqual([1, 2, 3]);
  });

  it("配列でないJSONの場合は空配列を返す", () => {
    expect(parseStoredClosedCategoryIds('{"a":1}')).toEqual([]);
  });
});

describe("toggleCategoryInClosedIds", () => {
  it("含まれていないIDを追加する", () => {
    expect(toggleCategoryInClosedIds([1, 2], 3)).toEqual([1, 2, 3]);
  });

  it("含まれているIDを除去する", () => {
    expect(toggleCategoryInClosedIds([1, 2, 3], 2)).toEqual([1, 3]);
  });
});

describe("toggleAllClosedCategoryIds", () => {
  it("閉じているカテゴリが1つもない場合は全カテゴリIDを返す(すべて閉じる)", () => {
    expect(toggleAllClosedCategoryIds([], [1, 2, 3])).toEqual([1, 2, 3]);
  });

  it("閉じているカテゴリが1つでもある場合は空配列を返す(すべて開く)", () => {
    expect(toggleAllClosedCategoryIds([2], [1, 2, 3])).toEqual([]);
  });
});
```

- [ ] **Step 2: テストが失敗することを確認する**

Run: `cd src && npx vitest run resources/js/utils/recordRanking.test.ts`
Expected: FAIL(`./recordRanking` が存在せずモジュール解決エラー)

- [ ] **Step 3: 最小限の実装を書く**

`src/resources/js/utils/recordRanking.ts` を作成する。

```typescript
import { CategorySummary, dispRecordContents } from "../types/recordRanking";

export const extractCategorySummaries = (
  contents: dispRecordContents
): CategorySummary[] => {
  const seenCategoryIds = new Set<number>();
  const summaries: CategorySummary[] = [];
  for (const content of contents) {
    const categoryId = content.category.id;
    if (!seenCategoryIds.has(categoryId)) {
      seenCategoryIds.add(categoryId);
      summaries.push({ id: categoryId, content: content.category.content });
    }
  }
  return summaries;
};

export const groupContentsByCategory = (
  contents: dispRecordContents
): Map<number, dispRecordContents> => {
  const map = new Map<number, dispRecordContents>();
  for (const content of contents) {
    const categoryId = content.category.id;
    const list = map.get(categoryId) ?? [];
    list.push(content);
    map.set(categoryId, list);
  }
  return map;
};

export const parseStoredClosedCategoryIds = (stored: string | null): number[] => {
  if (!stored) {
    return [];
  }
  const parsed = JSON.parse(stored);
  return Array.isArray(parsed) ? parsed : [];
};

export const toggleCategoryInClosedIds = (
  closedIds: number[],
  categoryId: number
): number[] => {
  if (closedIds.includes(categoryId)) {
    return closedIds.filter((id) => id !== categoryId);
  }
  return [...closedIds, categoryId];
};

export const toggleAllClosedCategoryIds = (
  closedIds: number[],
  allCategoryIds: number[]
): number[] => {
  return closedIds.length === 0 ? [...allCategoryIds] : [];
};
```

- [ ] **Step 4: テストが通ることを確認する**

Run: `cd src && npx vitest run resources/js/utils/recordRanking.test.ts`
Expected: PASS(11 tests)

---

### Task 3: `useGetRecordRanking.ts` を新しいユーティリティ関数を使う形に更新する

**Files:**
- Modify: `src/resources/js/composables/ranking/useGetRecordRanking.ts`

- [ ] **Step 1: ファイル全体を置き換える**

`src/resources/js/composables/ranking/useGetRecordRanking.ts` の内容を以下に置き換える。

```typescript
import { ref } from "vue";
import axios from "axios";
import useNotLoginedRedirect from "../certification/useNotLoginedRedirect";
import { CategorySummary, dispRecordContents } from "../../types/recordRanking";
import { extractCategorySummaries } from "../../utils/recordRanking";

export default function useGetRecords() {
    const rankingContents = ref<dispRecordContents>([]);
    const compGetData = ref<boolean>(false);
    const categoryContents = ref<CategorySummary[]>([]);

    const getRecords = async (user_id: number) => {
        await axios
            .get("/api/recordRanking/user", {
                // get時にパラメータを渡す際はparamsで指定が必要
                params: {
                    // keyとvalueが同じためuser_id:user_idの「:user_id」を省略できる
                    user_id,
                },
            })
            .then((res) => {
                rankingContents.value = res.data.dispContents;
                categoryContents.value = extractCategorySummaries(rankingContents.value);
                compGetData.value = true;
            })
            .catch((err) => {
                useNotLoginedRedirect(err);
            });
    };

    return { rankingContents, compGetData, categoryContents, getRecords };
}
```

この時点では `userRecordRankingTable.vue` の props 型がまだ `Array<string>` のままのため、`vue-tsc` を実行すると `category_contents` の型不一致エラーが出る。これはTask 4で解消されるため、ここでは型チェックを実行しない(Task 4 Step 2でまとめて確認する)。

---

### Task 4: `userRecordRankingTable.vue` をアコーディオン+カードグリッドに作り替える

**Files:**
- Modify: `src/resources/js/components/ranking/userRecordRankingTable.vue`

- [ ] **Step 1: ファイル全体を置き換える**

`src/resources/js/components/ranking/userRecordRankingTable.vue` の内容を以下に置き換える。

```vue
<script setup lang="ts">
import { ComputedRef, Ref, computed, ref } from "vue";
import { CategorySummary, dispRecordContents } from "../../types/recordRanking";
import {
  groupContentsByCategory,
  parseStoredClosedCategoryIds,
  toggleAllClosedCategoryIds,
  toggleCategoryInClosedIds,
} from "../../utils/recordRanking";

const props = defineProps<{
  ranking_contents: dispRecordContents;
  category_contents: CategorySummary[];
}>();

const dispContents: ComputedRef<dispRecordContents> = computed(() => props.ranking_contents);
const categoryContents: ComputedRef<CategorySummary[]> = computed(
  () => props.category_contents
);

const contentsByCategory: ComputedRef<Map<number, dispRecordContents>> = computed(() =>
  groupContentsByCategory(dispContents.value)
);

const CLOSED_CATEGORIES_STORAGE_KEY = "recordRanking.closedCategoryIds";

const closedCategoryIds: Ref<number[]> = ref(
  parseStoredClosedCategoryIds(localStorage.getItem(CLOSED_CATEGORIES_STORAGE_KEY))
);

const persistClosedCategoryIds = (): void => {
  localStorage.setItem(
    CLOSED_CATEGORIES_STORAGE_KEY,
    JSON.stringify(closedCategoryIds.value)
  );
};

const isCategoryOpen = (categoryId: number): boolean =>
  !closedCategoryIds.value.includes(categoryId);

const toggleCategory = (categoryId: number): void => {
  closedCategoryIds.value = toggleCategoryInClosedIds(closedCategoryIds.value, categoryId);
  persistClosedCategoryIds();
};

const allOpen: ComputedRef<boolean> = computed(() => closedCategoryIds.value.length === 0);

const toggleAll = (): void => {
  closedCategoryIds.value = toggleAllClosedCategoryIds(
    closedCategoryIds.value,
    categoryContents.value.map((category) => category.id)
  );
  persistClosedCategoryIds();
};
</script>

<template>
  <div class="max-w-3xl mx-auto w-11/12">
    <div class="flex justify-end mb-2">
      <button type="button" class="text-sm text-teal-700 underline" @click="toggleAll">
        {{ allOpen ? "すべて閉じる" : "すべて開く" }}
      </button>
    </div>

    <div v-for="category in categoryContents" :key="category.id" class="mb-3">
      <button
        type="button"
        class="w-full flex items-center justify-between rounded-md bg-teal-600 px-3 py-2 text-white font-semibold"
        @click="toggleCategory(category.id)"
      >
        <span>{{ category.content }}</span>
        <svg
          :class="{ 'rotate-180': isCategoryOpen(category.id) }"
          class="w-4 h-4 transition-transform"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div
        v-if="isCategoryOpen(category.id)"
        class="grid grid-cols-2 gap-2 p-3 border border-t-0 rounded-b-md"
      >
        <template v-for="item in contentsByCategory.get(category.id)" :key="item.menu.id">
          <div
            v-if="item.emptyData === 1"
            class="col-span-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3"
          >
            <div class="text-sm font-semibold text-gray-400">{{ item.menu.content }}</div>
            <div class="text-xs text-gray-400 mt-1">記録なし</div>
          </div>
          <div v-else class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-sm font-semibold mb-1">{{ item.menu.content }}</div>
            <template v-if="item.menu.oneSide === 1">
              <div class="text-xs text-gray-700">
                右 {{ item.menuBestRightWeight }}kg / 左 {{ item.menuBestLeftWeight }}kg
              </div>
              <div class="text-[11px] text-gray-500 mt-1">
                ボリューム 右{{ item.menuBestRightVolume.right_volume }}({{
                  item.menuBestRightVolume.right_weight
                }}kg×{{ item.menuBestRightVolume.right_rep }}回)
              </div>
              <div class="text-[11px] text-gray-500">
                左{{ item.menuBestLeftVolume.left_volume }}({{
                  item.menuBestLeftVolume.left_weight
                }}kg×{{ item.menuBestLeftVolume.left_rep }}回)
              </div>
            </template>
            <template v-else>
              <div class="text-xs text-gray-700">重量 {{ item.bestWeight }}kg</div>
              <div class="text-[11px] text-gray-500 mt-1">
                ボリューム {{ item.menuBestVolume.volume }}({{ item.menuBestVolume.weight }}kg×{{
                  item.menuBestVolume.rep
                }}回)
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>
    <div class="h-12"></div>
  </div>
</template>
```

- [ ] **Step 2: 型チェックを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json 2>&1 | grep -i "userRecordRankingTable\|useGetRecordRanking" || echo "NO_TYPE_ERROR"`
Expected: `NO_TYPE_ERROR`

---

### Task 5: `userRecordRanking.vue` の周辺UIを調整する

**Files:**
- Modify: `src/resources/js/views/ranking/userRecordRanking.vue:60-79`

- [ ] **Step 1: 戻るボタンの配色・凡例削除・幅変更を行う**

`src/resources/js/views/ranking/userRecordRanking.vue` の以下の箇所(60〜78行目)

```html
    <div class="mx-auto md:w-6/12 w-11/12 mb-5 font-bold md:text-left">
      <button
        class="mx-auto mt-10 font-bold md:text-center bg-red-500 text-white w-28 h-8 rounded-md ml-5"
        @click="toBeforeScreen"
      >
        前画面へ戻る
      </button>
    </div>
    <p class="mx-auto mt-5 md:w-6/12 w-11/12 mb-5 font-bold md:text-center">
      メニュー別の最高重量、最高ボリュームを表示しています。
    </p>
    <div class="text-right mx-auto w-11/12 md:w-6/12">
      <i class="fa-solid fa-minus text-red-500 text-xl"></i>
      <span class="text-lg">：部位</span>
    </div>
    <div class="text-right mx-auto w-11/12 md:w-6/12">
      <i class="fa-solid fa-minus text-blue-500 text-xl"></i>
      <span class="text-lg">：種目</span>
    </div>
```

を以下に置き換える。

```html
    <div class="max-w-3xl mx-auto w-11/12 mb-5 font-bold md:text-left">
      <button
        class="mx-auto mt-10 md:text-center border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-md px-4 py-1.5 text-sm font-medium ml-5"
        @click="toBeforeScreen"
      >
        前画面へ戻る
      </button>
    </div>
    <p class="mx-auto mt-5 max-w-3xl w-11/12 mb-5 font-bold md:text-center">
      メニュー別の最高重量、最高ボリュームを表示しています。
    </p>
```

- [ ] **Step 2: 型チェックを実行する**

Run: `cd src && npx vue-tsc --noEmit -p tsconfig.json`
Expected: エラーなし(0件)

---

### Task 6: ビルドと実ブラウザでの動作確認

**Files:**
- (変更なし。検証のみ)

- [ ] **Step 1: プロダクションビルドを実行する**

Run: `cd src && npm run build`
Expected: ビルド成功(エラーなし)

- [ ] **Step 2: 全テストを実行する**

Run: `cd src && npx vitest run`
Expected: 既存の `seoUrl.test.ts` を含め全テストPASS

- [ ] **Step 3: 実ブラウザで確認する**

`chrome-screen-check` スキルを使い、開発サーバー(`npm run dev` + Docker)を起動した状態でシードのテストアカウントでログインし、`/ranking` 画面をデスクトップ幅・モバイル幅(390×844)の両方でスクリーンショット確認する。確認観点:

- カテゴリ見出しがteal背景のアコーディオンになっており、クリックで開閉できる
- 開閉状態がページリロード後も保持される(2〜3カテゴリを閉じてリロードし、閉じた状態のまま保持されることを確認する)
- 「すべて開く/すべて閉じる」ボタンで全カテゴリの開閉が一括切り替えできる
- 片側メニュー(右/左の重量・ボリューム)、両側メニュー、記録なしメニューそれぞれのカード表示が正しい
- モバイル幅でカードが2列グリッドのまま崩れずに折り返される
- デスクトップ幅で `max-w-3xl` の中央寄せ幅で表示され、旧来の色分け凡例が表示されていない
- 「前画面へ戻る」ボタンがニュートラルな配色(白背景・グレー枠)になっている

コンソールエラー・ネットワークエラーが出ていないことも合わせて確認する。

---

## 最終コミット

全タスク完了後、以下でまとめてコミットする。

```bash
git add src/resources/js/types/recordRanking.d.ts
git add src/resources/js/utils/recordRanking.ts
git add src/resources/js/utils/recordRanking.test.ts
git add src/resources/js/composables/ranking/useGetRecordRanking.ts
git add src/resources/js/components/ranking/userRecordRankingTable.vue
git add src/resources/js/views/ranking/userRecordRanking.vue
git commit -m "feat: メニュー別最高記録画面をアコーディオン+カードグリッドUIに刷新"
```

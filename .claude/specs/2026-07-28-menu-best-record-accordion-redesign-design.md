# メニュー別最高記録画面 UI・UX刷新設計

## 背景・目的

「メニュー別最高記録」画面(`/ranking`)は、カテゴリごとに赤/青のベタ塗りヘッダーを持つテーブルが縦に並ぶ作りで、以下の課題がある。

1. 見た目が古く、体重管理ページなど直近改善した画面と統一感がない
2. カテゴリ・種目数が多いと単純に縦スクロールが伸びるだけで、目的の種目を探しにくい
3. 片側メニュー(左右あり)の重量・ボリュームがCSS Gridの5列に詰め込まれており、モバイルで読みにくい
4. デスクトップでも `md:w-6/12` の中央寄せ細帯レイアウトのため、画面幅を活かせていない

これらをまとめて解決するため、カテゴリ単位のアコーディオン+種目カードグリッドへ刷新する。

## スコープ

含む:
- `userRecordRanking.vue`:「前画面へ戻る」ボタンの配色変更、色分け凡例の削除、ページ幅の拡張
- `userRecordRankingTable.vue`:カテゴリのアコーディオン化、開閉状態のlocalStorage永続化、種目カードグリッドへの作り替え
- `useGetRecordRanking.ts` / `types/recordRanking.d.ts`:`categoryContents` にカテゴリIDを持たせる型変更

含まない(スコープ外):
- ログイン必須モーダル(`dispAlertModal`)の挙動 — 既存のまま
- APIレスポンス構造・バックエンド(`RecordRankingController`)の変更
- カテゴリ・種目の並び替え機能(現状のAPI返却順を踏襲)

## フロントエンド変更

### 1. `types/recordRanking.d.ts`:カテゴリ要約型の追加

`categoryContents` を `string[]` から、開閉状態のキーに使えるID付きの型に変更する。

```typescript
export declare type CategorySummary = {
    id: number;
    content: string;
};
```

### 2. `useGetRecordRanking.ts`:カテゴリ抽出ロジックの簡略化

現状は「直前の要素とカテゴリIDが違ったら追加」という隣接比較ロジックのため、同一カテゴリのデータがAPI返却順で隣接していない場合に重複が発生しうる。`Set` を使ったID単位の重複排除に置き換える。

```typescript
import { CategorySummary, dispRecordContents } from "../../types/recordRanking";

const categoryContents = ref<CategorySummary[]>([]);

const getRecords = async (user_id: number) => {
    await axios
        .get("/api/recordRanking/user", { params: { user_id } })
        .then((res) => {
            rankingContents.value = res.data.dispContents;
            const seenCategoryIds = new Set<number>();
            categoryContents.value = [];
            for (const content of rankingContents.value) {
                if (!seenCategoryIds.has(content.category.id)) {
                    seenCategoryIds.add(content.category.id);
                    categoryContents.value.push({
                        id: content.category.id,
                        content: content.category.content,
                    });
                }
            }
            compGetData.value = true;
        })
        .catch((err) => {
            useNotLoginedRedirect(err);
        });
};
```

### 3. `userRecordRankingTable.vue`:アコーディオン+カードグリッドへの作り替え

**カテゴリ別グルーピング**(現状の二重ループをMapによる1パスに置き換え):

```typescript
const contentsByCategory: ComputedRef<Map<number, dispRecordContents>> = computed(() => {
  const map = new Map<number, dispRecordContents>();
  for (const content of dispContents.value) {
    const list = map.get(content.category.id) ?? [];
    list.push(content);
    map.set(content.category.id, list);
  }
  return map;
});
```

**開閉状態の永続化**:「閉じているカテゴリID」をlocalStorageに保存する(「開いているID」ではなく)。これにより、初回訪問時(未保存)は自動的に全カテゴリ展開になり、後から追加された新カテゴリも保存済みユーザーにとって自動的に展開状態になる。

```typescript
const CLOSED_CATEGORIES_STORAGE_KEY = "recordRanking.closedCategoryIds";

const getStoredClosedCategoryIds = (): number[] => {
  const stored = localStorage.getItem(CLOSED_CATEGORIES_STORAGE_KEY);
  if (!stored) return [];
  const parsed = JSON.parse(stored);
  return Array.isArray(parsed) ? parsed : [];
};

const closedCategoryIds: Ref<Set<number>> = ref(new Set(getStoredClosedCategoryIds()));

const persistClosedCategoryIds = (): void => {
  localStorage.setItem(
    CLOSED_CATEGORIES_STORAGE_KEY,
    JSON.stringify([...closedCategoryIds.value])
  );
};

const isCategoryOpen = (categoryId: number): boolean => !closedCategoryIds.value.has(categoryId);

const toggleCategory = (categoryId: number): void => {
  const next = new Set(closedCategoryIds.value);
  if (next.has(categoryId)) {
    next.delete(categoryId);
  } else {
    next.add(categoryId);
  }
  closedCategoryIds.value = next;
  persistClosedCategoryIds();
};

const allOpen: ComputedRef<boolean> = computed(() => closedCategoryIds.value.size === 0);

const toggleAll = (): void => {
  closedCategoryIds.value = allOpen.value
    ? new Set(categoryContents.value.map((c) => c.id))
    : new Set();
  persistClosedCategoryIds();
};
```

**テンプレート構造**:

```html
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
      <!-- SVGシェブロンアイコン、開閉状態でrotateクラスを切り替え -->
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

    <div v-if="isCategoryOpen(category.id)" class="grid grid-cols-2 gap-2 p-3 border border-t-0 rounded-b-md">
      <template v-for="item in contentsByCategory.get(category.id)" :key="item.menu.id">
        <div v-if="item.emptyData === 1" class="col-span-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3">
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
              ボリューム 右{{ item.menuBestRightVolume.right_volume }}({{ item.menuBestRightVolume.right_weight }}kg×{{ item.menuBestRightVolume.right_rep }}回)
            </div>
            <div class="text-[11px] text-gray-500">
              左{{ item.menuBestLeftVolume.left_volume }}({{ item.menuBestLeftVolume.left_weight }}kg×{{ item.menuBestLeftVolume.left_rep }}回)
            </div>
          </template>
          <template v-else>
            <div class="text-xs text-gray-700">重量 {{ item.bestWeight }}kg</div>
            <div class="text-[11px] text-gray-500 mt-1">
              ボリューム {{ item.menuBestVolume.volume }}({{ item.menuBestVolume.weight }}kg×{{ item.menuBestVolume.rep }}回)
            </div>
          </template>
        </div>
      </template>
    </div>
  </div>
</div>
```

「すべて開く/すべて閉じる」ボタンと開閉状態はこのコンポーネント内に閉じ、親(`userRecordRanking.vue`)への状態受け渡しは不要にする。

### 4. `userRecordRanking.vue`:周辺UIの調整

- 色分け凡例(`部位=赤線`/`種目=青線`、67〜78行目)を削除する。カテゴリ見出し=アコーディオン、種目名=カード見出しという構造そのものが説明を兼ねるため。
- 「前画面へ戻る」ボタンを赤背景からニュートラルな配色に変更する。

```html
<button
  class="mx-auto mt-10 md:text-center border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-md px-4 py-1.5 text-sm font-medium ml-5"
  @click="toBeforeScreen"
>
  前画面へ戻る
</button>
```

- 外側コンテナの幅を `md:w-6/12 w-11/12` から `max-w-3xl mx-auto w-11/12` に変更し、デスクトップでの表示幅を広げる(体重管理ページの `max-w-3xl` と統一)。

## データ・表記

- 「ボリューム」表記は現状のまま使用する(略さない)。
- 重量・ボリュームの数値フォーマットは既存の表示ロジックをそのまま踏襲する(新規の丸め・単位変換は行わない)。

## エラーハンドリング

- localStorageへのアクセス失敗(プライベートブラウジング等)は既存の体重管理ページの方針を踏襲し、特別に考慮しない(try-catchを追加しない)。
- `contentsByCategory.get(category.id)` が `undefined` になるケースは発生しない(`categoryContents` は `dispContents` から導出されるため、存在しないカテゴリIDがテンプレート側に現れることはない)。

## テスト方針

- フロントエンドのみの変更(表示ロジック・localStorage)のため、`vue-tsc` 型チェックと `npm run build`、実ブラウザでの動作確認(デスクトップ幅・モバイル幅390×844の両方)で担保する。
- 確認観点:
  - カテゴリの開閉がクリックで切り替わり、リロード後も状態が保持される
  - 初回訪問(localStorage未保存)では全カテゴリが展開されている
  - 「すべて開く/すべて閉じる」ボタンが全カテゴリの開閉状態を一括で切り替え、localStorageにも反映される
  - 片側メニュー・両側メニュー・記録なしそれぞれのカード表示が正しい
  - モバイル幅でカードが2列グリッドのまま崩れずに折り返される
  - デスクトップ幅で `max-w-3xl` により中央寄せの適切な幅で表示される

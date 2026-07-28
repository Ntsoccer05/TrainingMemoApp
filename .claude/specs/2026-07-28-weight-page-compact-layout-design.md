# 体重管理ページ コンパクトレイアウト改善設計

## 背景・目的

前回実装した体重管理ページの改善(`.claude/specs/2026-07-28-weight-target-and-layout-improvements-design.md`)を実ブラウザ(デスクトップ・モバイル390×844px)で確認したところ、以下の改善要望が出た。

1. 期間切替ボタン(1ヶ月/3ヶ月/6ヶ月)がグラフから離れた位置(目標体重設定の上)にあり分かりにくい。また選択状態が保持されない
2. 体重記録の「体重(kg)」入力欄が不必要に横幅いっぱいになっている
3. 体重記録・目標体重・グラフはこのページの主要3要素であり、できる限りスクロールなしで画面内に収めたい(デスクトップ・モバイル両方で)

実測の結果(モバイル390×844px)、主要3要素を完全にスクロールなしで収めるのはグラフの判読性を著しく損なわない限り困難であることが判明した。そのため「完全にスクロールなし」は保証しないが、以下の施策を組み合わせて可能な限りスクロール量を減らす方針とする。

## スコープ

含む:
- 期間切替ボタンを`WeightChart`の直前に移動し、選択状態をlocalStorageに保存して次回訪問時も復元する
- `WeightRecordForm.vue`の「体重(kg)」入力欄の幅を縮小し、ラベルをinput左側にインライン配置する
- `WeightTargetSetting.vue`の「目標体重(kg)」ラベルもinput左側にインライン配置する
- `WeightRecordForm.vue`の「メモ」欄を開閉可能にする。デフォルトの開閉状態はモバイル幅(768px未満)では閉じる、デスクトップ幅では開く。ユーザーが手動で開閉した場合はその状態をlocalStorageに保存し、次回訪問時も復元する
- `WeightRecordForm.vue`のタグ選択をチェックボックス+ラベルから、クリックでトグルするチップ形式のボタンに変更する(`WeightTagEditor.vue`と視覚的に統一)
- `weightManagement.vue`のレイアウトを、デスクトップ幅(768px以上)では2カラム(左:日付+体重記録フォーム+目標体重設定、右:期間切替ボタン+グラフ)に、モバイル幅では現状通り1カラム縦積みに変更する
- `WeightChart.vue`のグラフ高さを、モバイル幅では圧縮(280px→220px)、デスクトップ幅では現状の280pxを維持するようレスポンシブにする

含まない(スコープ外):
- モバイル幅での「完全なスクロールなし」の保証(実測の結果、判読性を大きく損なわずには達成困難と判断)
- `WeightTagEditor.vue`(タグ編集セクション)自体のチップUI変更(既にチップ形式のため対象外)

## フロントエンド変更

### 1. 期間切替ボタンの移動とlocalStorage永続化

`weightManagement.vue`のテンプレートで、期間切替ボタンのブロックを`WeightChart`の直前に移動する。

`selectedMonths`の初期値をlocalStorageから復元し、変更時に保存する。

```typescript
const PERIOD_STORAGE_KEY = "weightManagement.selectedMonths";

const getInitialSelectedMonths = (): number => {
  const stored = localStorage.getItem(PERIOD_STORAGE_KEY);
  const parsed = stored ? Number(stored) : NaN;
  return [1, 3, 6].includes(parsed) ? parsed : 1;
};

const selectedMonths: Ref<number> = ref(getInitialSelectedMonths());
```

`changePeriod`関数を以下に変更する。

```typescript
const changePeriod = async (months: number): Promise<void> => {
  selectedMonths.value = months;
  localStorage.setItem(PERIOD_STORAGE_KEY, String(months));
  await fetchHistory();
};
```

`fetchHistory`は既に`selectedMonths.value`を参照しているため、`onMounted`時の初回`fetchHistory()`呼び出しがそのまま復元済みの期間で実行される(追加の変更不要)。

### 2. `WeightRecordForm.vue`: 体重(kg)入力欄の幅縮小・ラベルインライン化

「体重(kg)」のラベルとinputを縦積みのブロックから、横並びのflex行に変更する。inputの幅は`w-32`程度に固定する。

### 3. `WeightRecordForm.vue`: タグのチップ形式化

チェックボックス+ラベルの行を、クリックでトグルするボタン(チップ)形式に変更する。選択中は青背景、未選択はグレー背景で表現する(`WeightTagEditor.vue`のチップ表示と統一感を持たせる)。

```typescript
const toggleTag = (tagId: number): void => {
  const index = selectedTagIds.value.indexOf(tagId);
  if (index === -1) {
    selectedTagIds.value.push(tagId);
  } else {
    selectedTagIds.value.splice(index, 1);
  }
};
```

### 4. `WeightRecordForm.vue`: メモ欄の開閉可能化とlocalStorage永続化

メモのラベル部分をクリック可能なトグルボタンにし、開閉状態に応じてtextareaの表示/非表示を切り替える。開いているかどうかに関わらず`memoInput`の値自体は保持される(非表示中も入力データは消えない)。

```typescript
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
```

メモに既存の入力がある状態で折りたたまれている場合、ラベル部分に「(入力あり)」を併記し、閉じたままでも入力の有無が分かるようにする。

### 5. `WeightTargetSetting.vue`: 目標体重(kg)ラベルのインライン化

「目標体重(kg)」ラベルを、現在input・期限・ボタンが並んでいるflex行の中に組み込み、独立した縦積みブロックをなくす。

### 6. `weightManagement.vue`: 2カラムレスポンシブレイアウト

デスクトップ幅(Tailwindの`md`ブレークポイント、768px)以上では2カラムグリッドにする。

```html
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
  <div>
    <!-- 日付選択 + WeightRecordForm(体重記録) + WeightTargetSetting -->
  </div>
  <div>
    <!-- 期間切替ボタン + WeightChart -->
  </div>
</div>
<!-- WeightTagStats・WeightTagEditor・WeightRecordModalは2カラムの外、下部にフルwidthで配置 -->
```

モバイル幅(768px未満)では`grid-cols-1`によりこれまで通り1カラムの縦積みになる。

### 7. `WeightChart.vue`: グラフ高さのレスポンシブ化

ウィンドウ幅に応じてグラフの高さを動的に変更する。768px未満では220px、以上では現状の280pxを維持する。ウィンドウのリサイズにも追従する。

```typescript
const chartHeight: Ref<number> = ref(window.innerWidth < 768 ? 220 : 280);

const updateChartHeight = (): void => {
  chartHeight.value = window.innerWidth < 768 ? 220 : 280;
};

onMounted(() => {
  window.addEventListener("resize", updateChartHeight);
});

onUnmounted(() => {
  window.removeEventListener("resize", updateChartHeight);
});
```

`<VueApexCharts>`の`height`propを固定値`"280"`から`:height="chartHeight"`に変更する。

## エラーハンドリング

- localStorageへのアクセスが失敗する環境(プライベートブラウジング等の一部ブラウザ)を特別に考慮しない。`localStorage.getItem`/`setItem`が例外を投げるケースはこのアプリの対象ブラウザでは想定しないため、try-catchは追加しない
- チップ形式のタグ選択で、既に5個上限に達している状態でもタグの選択/解除自体は制限しない(選択できるタグ数と保有できるタグ数の上限(5個)は別の関心事のため)

## テスト方針

- 本改善はフロントエンドのみの変更(localStorage・レイアウト・表示ロジック)のため、既存同様`vue-tsc`型チェックと`npm run build`、実ブラウザでの動作確認(デスクトップ幅・モバイル幅390×844の両方)で担保する
- 確認観点: 期間選択がリロード後も保持される、メモの開閉状態がリロード後も保持される、チップタグの選択/解除が体重記録の保存に正しく反映される、デスクトップ幅で2カラムレイアウトになる、モバイル幅で1カラムに戻りグラフの高さが220pxになる

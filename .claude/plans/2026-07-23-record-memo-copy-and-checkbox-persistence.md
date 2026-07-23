# セット単位コピー機能・補完チェックボックス永続化 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** トレーニング記録画面(`RecordTable.vue`)にセット単位で前回の重量・回数・メモを今回欄へコピーするアイコンボタンを追加し、`recordContents.vue`の「重量・回数を補完する」チェックボックスの状態を部位・種目単位で`sessionStorage`に永続化する。

**Architecture:** 既存の`menuContentSessionStorage.ts`(部位・種目単位のセッションキャッシュを提供するcomposable風関数)に新しいgetter/setterペアを追加し、`recordContents.vue`から利用する。`RecordTable.vue`には新しい関数`copySetFromBefore(index)`を追加し、「前回の記録」列に条件付きで表示するアイコンボタンから呼び出す。

**Tech Stack:** Vue 3 (`<script setup>`, TypeScript), sessionStorage, Font Awesome (`fa-solid`)

**設計書:** `docs/plans/2026-07-23-record-memo-copy-and-checkbox-persistence-design.md`

**検証方針:** このプロジェクトのフロントエンドには自動テストフレームワークが無いため、各タスクの検証は (1) `vue-tsc`による型チェック、(2) 実装完了後にchrome-devtools-mcpを使った実ブラウザでの動作確認、の2段階で行う。

---

## Task 1: `menuContentSessionStorage.ts` にcomplementContents用のgetter/setterを追加

**Files:**
- Modify: `src/resources/js/utils/menuContentSessionStorage.ts`

- [ ] **Step 1: キー定義とgetter/setterを追加する**

`src/resources/js/utils/menuContentSessionStorage.ts`の以下の部分:

```typescript
    const menuContentKey = `menuContent_${categoryId}_${menuId}`;
    const fillBeforeRecordKey = `secondRecord_${categoryId}_${menuId}_${recordStateId}`;
    const historyRecordsKey = `historyRecords_${categoryId}_${menuId}_${recordStateId}`;
```

を以下に置き換える(3行目に新しいキー定義を追加):

```typescript
    const menuContentKey = `menuContent_${categoryId}_${menuId}`;
    const fillBeforeRecordKey = `secondRecord_${categoryId}_${menuId}_${recordStateId}`;
    const historyRecordsKey = `historyRecords_${categoryId}_${menuId}_${recordStateId}`;
    // 日付(recordStateId)を問わず、部位+種目単位で保持する
    const complementContentsKey = `complementContents_${categoryId}_${menuId}`;
```

次に、以下の部分:

```typescript
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

    return {
        setMenuContentSession,
        getMenuContentSession,
        removeMenuContentSession,
        getFillBeforeRecordSession,
        setFillBeforeRecordSession,
        removeFillBeforeRecordSession,
        getHistoryRecordSession,
        setHistoryRecordSession,
        removeHistoryRecordSession,
    };
}
```

を以下に置き換える:

```typescript
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
        getFillBeforeRecordSession,
        setFillBeforeRecordSession,
        removeFillBeforeRecordSession,
        getHistoryRecordSession,
        setHistoryRecordSession,
        removeHistoryRecordSession,
        getComplementContentsSession,
        setComplementContentsSession,
    };
}
```

- [ ] **Step 2: 型チェックを実行する**

Run:
```bash
docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html
```
(Windowsのシェルからパス変換で失敗する場合は `MSYS_NO_PATHCONV=1` を先頭に付けて実行する: `MSYS_NO_PATHCONV=1 docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html`)

Expected: `resources/js/utils/setSeo.ts(1,21): error TS2307: Cannot find module '@/config/seo'` という既知の無関係なエラーのみが出力される。`menuContentSessionStorage.ts`に関する新規エラーが無いこと。

---

## Task 2: `recordContents.vue` でcomplementContentsの状態を復元・永続化する

**Files:**
- Modify: `src/resources/js/components/record/recordContents.vue`

- [ ] **Step 1: `watch`をvueのimportに追加する**

`src/resources/js/components/record/recordContents.vue`の以下の行:

```typescript
import { ref, onMounted, computed, ComputedRef } from "vue";
```

を以下に置き換える:

```typescript
import { ref, onMounted, computed, watch, ComputedRef } from "vue";
```

- [ ] **Step 2: `menuContentSessionStorage`の呼び出しから新しい関数を取り出す**

以下の部分:

```typescript
const {
  setMenuContentSession,
  getMenuContentSession,
  removeMenuContentSession,
  getFillBeforeRecordSession,
  setFillBeforeRecordSession,
  removeFillBeforeRecordSession,
  getHistoryRecordSession,
  setHistoryRecordSession,
  removeHistoryRecordSession,
} = menuContentSessionStorage(category_id, menu_id, record_state_id);
const fillBeforeRecordSession = getFillBeforeRecordSession();
```

を以下に置き換える:

```typescript
const {
  setMenuContentSession,
  getMenuContentSession,
  removeMenuContentSession,
  getFillBeforeRecordSession,
  setFillBeforeRecordSession,
  removeFillBeforeRecordSession,
  getHistoryRecordSession,
  setHistoryRecordSession,
  removeHistoryRecordSession,
  getComplementContentsSession,
  setComplementContentsSession,
} = menuContentSessionStorage(category_id, menu_id, record_state_id);
const fillBeforeRecordSession = getFillBeforeRecordSession();
```

- [ ] **Step 3: `complementContents`の初期値をsessionStorageから復元し、変更をwatchして保存する**

以下の行:

```typescript
// 自動補完するか
const complementContents = ref<boolean>(false);
```

を以下に置き換える:

```typescript
// 自動補完するか(部位+種目単位でsessionStorageに保存された値を初期値として復元する)
const complementContents = ref<boolean>(getComplementContentsSession());

// チェックボックスの状態が変わるたびに部位+種目単位で保存する
watch(complementContents, (value) => {
  setComplementContentsSession(value);
});
```

- [ ] **Step 4: 型チェックを実行する**

Run:
```bash
MSYS_NO_PATHCONV=1 docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html
```
Expected: `setSeo.ts`の既知エラーのみ。`recordContents.vue`に関する新規エラーが無いこと。

---

## Task 3: `RecordTable.vue` にセット単位コピー機能を追加する

**Files:**
- Modify: `src/resources/js/components/record/RecordTable.vue`

- [ ] **Step 1: `nextTick`をvueのimportに追加する**

`src/resources/js/components/record/RecordTable.vue`の以下の行:

```typescript
import { ref, onMounted, computed, watch, ComputedRef } from "vue";
```

を以下に置き換える:

```typescript
import { ref, onMounted, computed, watch, ComputedRef, nextTick } from "vue";
```

- [ ] **Step 2: `copySetFromBefore`関数を追加する**

`inputBeforeMemo`関数の定義の直後(以下のブロックの直後):

```typescript
const inputBeforeMemo = (index) => {
  if (!beforeMemo.value) return contents.value[index].memo;
  const newValue = contents.value[index].memo;
  // :valueバインディングはこの関数を全行分、再レンダリングのたびに呼び出す
  // (どこか1行に入力するだけで他の9行分も再実行される)ため、
  // 実際に値が変わっていない行ではDOM書き込み・強制レイアウト(adjustHeight)を行わないようにする
  if (beforeMemo.value[index].value === (newValue ?? "")) {
    return newValue;
  }
  beforeMemo.value[index].value = newValue;
  adjustHeight(beforeMemo.value[index], thisMemo.value[index]);
  return newValue;
};
```

に、以下を追加する:

```typescript
const inputBeforeMemo = (index) => {
  if (!beforeMemo.value) return contents.value[index].memo;
  const newValue = contents.value[index].memo;
  // :valueバインディングはこの関数を全行分、再レンダリングのたびに呼び出す
  // (どこか1行に入力するだけで他の9行分も再実行される)ため、
  // 実際に値が変わっていない行ではDOM書き込み・強制レイアウト(adjustHeight)を行わないようにする
  if (beforeMemo.value[index].value === (newValue ?? "")) {
    return newValue;
  }
  beforeMemo.value[index].value = newValue;
  adjustHeight(beforeMemo.value[index], thisMemo.value[index]);
  return newValue;
};

// 前回のそのセットの重量・回数・メモを今回欄へ常に上書きコピーする
const copySetFromBefore = (index: number) => {
  const before = contents.value[index];
  if (hasOneHand.value) {
    rightWeight.value[index] =
      before.right_weight !== null && before.right_weight !== undefined
        ? before.right_weight.toString()
        : "";
    rightRep.value[index] =
      before.right_rep !== null && before.right_rep !== undefined
        ? before.right_rep.toString()
        : "";
    leftWeight.value[index] =
      before.left_weight !== null && before.left_weight !== undefined
        ? before.left_weight.toString()
        : "";
    leftRep.value[index] =
      before.left_rep !== null && before.left_rep !== undefined
        ? before.left_rep.toString()
        : "";
  } else {
    weight.value[index] =
      before.weight !== null && before.weight !== undefined
        ? before.weight.toString()
        : "";
    rep.value[index] =
      before.rep !== null && before.rep !== undefined ? before.rep.toString() : "";
  }
  memo.value[index] = before.memo !== null && before.memo !== undefined ? before.memo : "";

  // v-modelの反映(DOM更新)を待ってから、今回のメモ欄の高さを前回欄に合わせて調整する
  nextTick(() => {
    if (thisMemo.value && thisMemo.value[index] && beforeMemo.value && beforeMemo.value[index]) {
      adjustHeight(thisMemo.value[index], beforeMemo.value[index]);
    }
  });

  postRecordContent(index);
};
```

- [ ] **Step 3: 「前回の記録」列のセット見出しにコピーボタンを追加する**

テンプレート内の以下のブロック(「前回の記録」列、190行目付近):

```html
        <td>
          <div class="bg-gray-200 border indent-1">{{ index + 1 }}セット目</div>
          <!-- 前回のセットがある場合 -->
```

を以下に置き換える:

```html
        <td>
          <div class="bg-gray-200 border indent-1 flex items-center justify-between">
            <span>{{ index + 1 }}セット目</span>
            <button
              v-if="contents[index].set"
              type="button"
              class="mr-1 text-blue-700 hover:text-blue-900"
              title="今回にコピー"
              @click="copySetFromBefore(index)"
            >
              <i class="fa-solid fa-copy"></i>
            </button>
          </div>
          <!-- 前回のセットがある場合 -->
```

- [ ] **Step 4: 型チェックを実行する**

Run:
```bash
MSYS_NO_PATHCONV=1 docker exec trainingmemo-app-1 npx vue-tsc --noEmit -p /var/www/html
```
Expected: `setSeo.ts`の既知エラーのみ。`RecordTable.vue`に関する新規エラーが無いこと。

---

## Task 4: chrome-devtools-mcpによるブラウザ動作確認

**Files:** なし(検証のみ)

- [ ] **Step 1: 前提条件を確認する**

Run:
```bash
docker-compose ps
```
Expected: `db`, `mailhog`, `trainingmemo-app-1`, `trainingmemo-web-1`, `trainingmemo-phpmyadmin-1` がすべて起動していること。Viteの開発サーバーがポート5173で応答していること。

- [ ] **Step 2: セット単位コピーボタンの動作を確認する**

`test@gmail.com` / `password` でログインし、既存の前回記録があるメニュー(例: ホーム画面から過去の記録がある部位・種目)のトレーニング記録画面(`/record/...`)へ遷移する。

- 前回データがあるセットの「前回の記録」列に、`fa-copy`アイコンボタンが表示されていることを確認する。
- 前回データが無いセット(例: 前回の合計セット数を超えるセット)には、ボタンが表示されないことを確認する。
- 「今回」欄に何か入力した状態でコピーボタンを押し、前回の重量・回数・メモが「今回」欄に上書きされることを確認する。
- ネットワークタブで、コピー後に`POST /api/recordContent/create`が発行され、`weight`/`rep`/`memo`(oneSideメニューの場合は`right_weight`/`right_rep`/`left_weight`/`left_rep`)が前回の値と一致していることを確認する。
- oneSide(左右別)のメニューでも同様に、右重量・右回数・左重量・左回数・メモが正しくコピーされることを確認する。

- [ ] **Step 3: 「重量・回数を補完する」チェックボックスの永続化を確認する**

同じトレーニング記録画面で「重量・回数を補完する」チェックボックスをONにする。

- ページをリロードし、同じメニューであればチェック状態がONのまま維持されていることを確認する。
- 「メニュー選択へ戻る」→別の日付の同じ部位・種目の記録画面に入り直し、チェック状態がONのまま維持されていることを確認する。
- 別の種目(menu_idが異なる)の記録画面に遷移した場合は、チェック状態がその種目ごとに独立して保持されている(初回は未設定ならOFF)ことを確認する。

- [ ] **Step 4: コンソール・ネットワークエラーが無いことを確認する**

`list_console_messages`・`list_network_requests`で、JSエラーや4xx/5xxが発生していないことを確認する(既知の無関係なアクセシビリティ警告は無視してよい)。

---

## 最終コミット

全タスク完了・ブラウザ確認後、今回の新機能追加分のみをコミットする(直前セッションの未コミットの性能改善分はこのコミットに含めない):

```bash
wsl.exe -d Ubuntu-22.04 -- bash -c "cd /tmp/trainingMemo && git add -- src/resources/js/utils/menuContentSessionStorage.ts src/resources/js/components/record/recordContents.vue src/resources/js/components/record/RecordTable.vue docs/plans/2026-07-23-record-memo-copy-and-checkbox-persistence-design.md && git status"
```

ステージされたファイルが上記4件のみであることを確認してからコミットする:

```bash
wsl.exe -d Ubuntu-22.04 -- bash -c "cd /tmp/trainingMemo && git commit -m \"\$(cat <<'EOF'
feat: セット単位の前回記録コピー機能と補完チェックボックスの永続化を追加

RecordTable.vueの各セットの「前回の記録」列に、重量・回数・メモを
今回欄へ一括コピーするアイコンボタンを追加。また「重量・回数を補完する」
チェックボックスの状態を部位・種目単位でsessionStorageに保存し、
リロードや画面遷移をまたいで維持されるようにした。

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)\""
# セット単位コピー機能・補完チェックボックス永続化 設計書

- 作成日: 2026-07-23
- ステータス: 設計承認済み・未実装

## 背景

トレーニング記録画面(`recordContents.vue` → `RecordTable.vue`)で、以下2点の要望があった。

1. 前回の記録(重量・回数・メモ)を今回のセットへ複製したい。既存の「前回の記録を埋める」ボタンは全セット分を一括でコピーするのみで、セット単位での個別コピーはできない。
2. 「重量・回数を補完する」チェックボックス(`complementContents`)の状態が、リロードや画面遷移のたびに`false`にリセットされてしまう。同じ部位・種目であれば維持してほしい。

## 合意した設計

### 1. セット単位コピーアイコンボタン

- **配置**: `RecordTable.vue`の各セット行、「前回の記録」列(現在は無効化された表示専用の入力欄が並ぶ列)に、Font Awesomeの`fa-copy`アイコンボタンを追加する。
- **表示条件**: そのセットに前回データが存在する場合のみボタンを表示する(`contents.value[index].set`が真の場合。既存の`v-if`相当の条件と同じ判定を流用)。
- **コピー内容・挙動**: ボタン押下で、そのセットの「今回」欄に前回データを常に上書きする(既に入力済みでも確認なく上書き)。
  - 通常メニュー(`hasOneHand === false`): `weight[index]`, `rep[index]`, `memo[index]`
  - 左右別メニュー(`hasOneHand === true`): `rightWeight[index]`, `rightRep[index]`, `leftWeight[index]`, `leftRep[index]`, `memo[index]`
  - コピー元は`contents.value[index]`(前回データ、既存の`second_record`由来)の各フィールド。
- **保存**: コピー後は既存の`postRecordContent(index)`を呼び出し、サーバーへ即時保存する(手入力後の`@blur`保存と同じ扱い)。

### 2. 「重量・回数を補完する」チェックボックスの永続化

- **保存先**: `sessionStorage`。既存の`menuContentSessionStorage.ts`と同じ命名規則で、キーは`complementContents_${categoryId}_${menuId}`とする。
- **スコープ**: 部位(`category_id`)+種目(`menu_id`)単位。日付(`record_state_id`)は問わない。同じ種目であれば別の日に記録する際もチェック状態が引き継がれる。
- **復元・保存タイミング**: `recordContents.vue`のマウント時に`sessionStorage`から復元し、`complementContents`の値が変わるたびに保存する。
- **既存コードとの統合**: `menuContentSessionStorage.ts`(`userSessionStorage(categoryId, menuId, recordStateId)`)に`getComplementContentsSession`/`setComplementContentsSession`を追加し、他のセッションキャッシュ関数と同じ場所で管理する。

## 非対象・留意点

- 「前回の記録を埋める」ボタン(全セット一括コピー)の挙動は変更しない。
- チェックボックスの永続化はブラウザタブを閉じると消える(`sessionStorage`の性質上、既存の他のキャッシュと同じ)。

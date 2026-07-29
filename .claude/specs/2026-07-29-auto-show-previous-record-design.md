# 前回記録の常時表示化 + API統合 設計

## 背景・目的

種目の記録画面(`recordContents.vue`)には「前回の記録を埋める」ボタンがあり、これを押すと前回記録を取得して画面右側に表示する仕組みになっている。ボタンを廃止し、画面表示時点で前回の記録を常に表示する形に変更する。

サーバーコスト試算の結果、前回記録取得クエリは既存の複合インデックス(`idx_record_menus_search`)によりすでに軽量であり、常時取得に変えても絶対的なDB負荷は小さいと判断した。ただし「今回の記録取得(`GET /api/recordContent`)」と「前回の記録取得(`GET /api/recordMenu`、ボタン押下時のみ)」の2エンドポイント構成をそのまま常時実行にすると、HTTPラウンドトリップ・Laravelブートストラップ・Sanctum認証が2重に発生する。これを避けるため、両者を1本のAPIに統合する。

## スコープ

- 対象: 種目記録画面(`recordContents.vue` / `RecordTable.vue`)における前回記録の取得・表示
- 対象外: 体重管理画面、履歴確認モーダル(`confirmHistory`/`getMenuHistory`)、ホーム画面カレンダー — いずれも今回の変更と無関係のため触らない

## バックエンド設計

### エンドポイントの分岐条件の並び替え(`RecordContentController::index`)

現状、`GET /api/recordContent` は `recorded_at` パラメータの有無で「メニュー選択画面の日付マーキング用」分岐と「記録画面用」分岐を切り替えている。記録画面用の分岐でも前回検索のために `recorded_at` を使いたいため、分岐条件を `record_state_id` の有無を先に見る形に並び替える。

`record_state_id` は記録画面のみで送られ、メニュー選択画面(日付マーキング用)では送られないため、順序を入れ替えても既存の2分岐(ホーム画面カレンダー／日付マーキング)への影響はない。

```php
if (!$category_id && !$recorded_at) { /* 既存: ホーム画面カレンダー */ }
if ($record_state_id) { /* 記録画面: 今回分＋前回分をまとめて返す(新規、recorded_atを使用) */ }
if (isset($recorded_at)) { /* 既存: メニュー選択画面の日付マーキング */ }
```

### `RecordContentService::getCurrentAndPreviousRecord` の新設

`getMenuHistory` と同じレイヤー構成(Service内で完結、Controllerはレスポンス整形のみ)で追加する。

```php
public function getCurrentAndPreviousRecord(
    int $userId,
    int $categoryId,
    int $menuId,
    int $recordStateId,
    ?string $recordedAt
): array
```

- **今回分**: `record_state_id` で `RecordMenu` を1件検索 → 見つかれば `recordContents` を `set` 昇順で取得
- **前回分**: `recordedAt` が指定されている場合のみ検索する
  - 今回分の `RecordMenu` が**既に存在する**場合: `user_id + category_id + menu_id` で絞り込み、`recorded_at <= recordedAt` の降順1件目をスキップして2件目を取得(1件目は今回分自身のため)
  - 今回分の `RecordMenu` が**まだ存在しない**場合: `recorded_at < recordedAt` の降順1件目を取得
  - 見つかった場合、`recordState` をEager Loadし、`recordContents` を `set` 昇順で取得
- 戻り値: `['tgtRecords' => Collection|null, 'previousRecordState' => RecordState|null, 'previousRecords' => Collection|null]`

既存の `RecordMenuController::index`(`GET /api/recordMenu`)は、クライアントから送られる `thisTotalSet` の有無で同様の分岐をしていたが、今回は同一リクエスト内で今回分の存在有無がすでにわかっているため、クライアントからの追加パラメータなしで同じ判定ができる。

### レスポンス形式

```json
{
  "status_code": 200,
  "message": "記録日と前回の記録データを取得",
  "tgtRecords": [...] | null,
  "previousRecordState": { "id": ..., "bodyWeight": ... } | null,
  "previousRecords": [...] | null
}
```

### 削除

- `RecordMenuController::index`(`GET /api/recordMenu`)と対応するルート定義 — フロントエンドでの利用箇所は `useGetSecondRecordContent.ts` のみであることを確認済み(削除対象に含まれる)。`RecordMenuController::create` は無関係のため維持する

## フロントエンド設計

### データ取得の一本化

- `useGetTgtRecordContent.ts` と `useGetSecondRecordContent.ts` を廃止し、新しい composable `useGetRecordContent.ts` に統合する
  - `getRecordContent(user_id, category_id, menu_id, record_state_id, recorded_at)` が `GET /api/recordContent` を1回呼び出し、`tgtRecords / hasTgtRecord / previousRecordState / previousRecords / hasPreviousRecord` を返す
- 呼び出し元を `recordContents.vue`(親)に一本化する
  - `RecordTable.vue` は自前の `onMounted` フェッチを削除し、`tgtRecords` / `hasTgtRecord` を新規propsとして親から受け取る(`second_record` / `hasSecondRecord` は既存のまま維持)

### UI変更(`recordContents.vue`)

- 削除: 「前回の記録を埋める」ボタン、`fillBeforeRecord()`、`fillBeforeBtn` ref、「※前回の記録を埋めるためには今回の記録を埋めてください」の注意文言
- 削除: `BeforeBtnTxt` / `isDispTxt` の状態と、`RecordTable.vue` 側の対応する `canClick` emit・`canClickFillBefore`(前々回機能の未使用コードごと削除)
- 簡略化: `BeforeWeightTxt`(前回の体重) / `BeforeTotalSetTxt`(前回の合計セット数) / `BeforeHeaderTxt`(前回の記録) は値が変化しなくなるため `ref` から固定 `const` 文字列に変更
- `msgNoBeforeData` はマウント時に取得した `hasPreviousRecord` を見て即座にセットする(クリック時にセットする現行ロジックを廃止)

### セッションキャッシュ(`menuContentSessionStorage.ts`)

- `fillBeforeRecordKey` 系(`getFillBeforeRecordSession` / `setFillBeforeRecordSession` / `removeFillBeforeRecordSession`)を、統合レスポンス全体(`tgtRecords` + `previousRecordState` + `previousRecords`)をキャッシュする `recordDataKey` 系(`getRecordDataSession` / `setRecordDataSession` / `removeRecordDataSession`)に置き換える
- `recordContents.vue` のマウント時、セッションキャッシュがあればAPIを呼ばずに復元し、無ければAPIを呼んで結果をキャッシュに保存する(同一セッション内で同じ種目・同じ日に再訪問した際は再フェッチしない、既存の挙動を踏襲)

## テスト方針

`RecordContentServiceTest.php` に既存の `test_get_menu_history_*` と同じスタイル(Serviceを直接インスタンス化し、`DB::enableQueryLog()` でN+1を検証)で以下を追加する。

- 今回分のレコードが存在する場合、前回分は「今回を除いた直近1件」を返す
- 今回分がまだ存在しない場合、前回分は「基準日より前の直近1件」を返す
- 前回分が存在しない場合は `previousRecordState` / `previousRecords` が `null` になる
- `previousRecordState`(`recordState`)・`previousRecords`(`recordContents`)のアクセスでN+1が発生しない

コントローラーの分岐並び替えについては、既存の `tests/Feature/RecordContentControllerTest.php`(ホーム画面カレンダー分岐・日付マーキング分岐を検証済み)がそのまま回帰テストとして機能する。分岐条件の並び替え後もこれらのテストが引き続きパスすることを確認する。記録画面分岐(`record_state_id` あり)についても、統合レスポンス(`tgtRecords`/`previousRecordState`/`previousRecords`)を検証する新規テストを同ファイルに追加する。

## 影響範囲まとめ

| ファイル | 変更内容 |
|---|---|
| `app/Http/Controllers/RecordContentController.php` | 分岐条件の並び替え、記録画面分岐をService呼び出しに置き換え |
| `app/Http/Controllers/RecordMenuController.php` | `index` メソッド削除(他利用箇所がなければ) |
| `app/Services/RecordContent/RecordContentService.php` | `getCurrentAndPreviousRecord` 新設 |
| `routes/api.php` | `GET /api/recordMenu` ルート削除(他利用箇所がなければ) |
| `resources/js/composables/record/useGetRecordContent.ts` | 新規(統合composable) |
| `resources/js/composables/record/useGetTgtRecordContent.ts` | 削除 |
| `resources/js/composables/record/useGetSecondRecordContent.ts` | 削除 |
| `resources/js/components/record/recordContents.vue` | ボタン・関連状態削除、統合composable呼び出しに変更 |
| `resources/js/components/record/RecordTable.vue` | 自前フェッチ削除、props経由に変更 |
| `resources/js/utils/menuContentSessionStorage.ts` | `fillBeforeRecord*` → `recordData*` に置き換え |
| `tests/Feature/Services/RecordContent/RecordContentServiceTest.php` | 新メソッドのテスト追加 |
| `tests/Feature/RecordContentControllerTest.php` | 記録画面分岐(`record_state_id` あり)の統合レスポンス検証テストを追加。既存の2分岐のテストは回帰確認として維持 |

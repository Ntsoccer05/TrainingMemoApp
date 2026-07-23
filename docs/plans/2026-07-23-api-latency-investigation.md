# APIレイテンシ改善 実行計画・実施記録

- 実施日: 2026-07-23
- 対象: バックエンドAPI全体のレイテンシ調査・改善
- 使用スキル: `performance-investigation`

## 背景・目的

「APIにてレイテンシが遅いものが多々ある。全て解消したい。」という依頼を受け、
体感や推測ではなくクエリログ・実行計画・レスポンスサイズなど客観的な数値に基づいて原因を特定し、修正する。

## 調査手順

1. 対象コードパスの特定: `routes/api.php` から呼ばれる全コントローラー(`RecordController`, `RecordMenuController`, `RecordContentController`, `RecordRankingController`, `MenuController`)を洗い出し。
2. 本番相当データの投入: 既存シーダーはレコード数が少なく(record_states 34件など)N+1やレスポンス肥大化が顕在化しないため、`test@gmail.com`(user_id=1)に対して以下を投入。
   - record_states: 700件(約700日分)
   - record_menus: 2,130件
   - record_contents: 8,506件
   - 投入スクリプトはスクラッチパッドで作成し、`docker cp` + `php artisan tinker --execute="include '...';"` で実行(既存メニュー・カテゴリを流用)。
3. `php artisan tinker` + `DB::enableQueryLog()` + `microtime(true)` で各コントローラーのメソッドを直接呼び出し、クエリ数と所要時間を実測。
4. 疑わしい箇所は `EXPLAIN` や処理を分解したスクリプトでさらに深掘り。

## 計測結果(Before)

| エンドポイント | 所要時間 | クエリ数 | 備考 |
|---|---|---|---|
| `GET /recordContent`(ホーム画面、全記録取得) | 7,180.74ms | 2,862 | 記録日数分クエリが線形増加 |
| `GET /record` | 12.22ms | 3 | 問題なし |
| `GET /recordRanking/user`(MAX記録) | 3,797.78ms | 6 | クエリ数は少ないがPHP側処理とレスポンスサイズが原因 |
| `GET /menus` | 94.01ms | 4 | 問題なし |
| `GET /recordMenu` | 9.22ms | 3 | 問題なし |

## 原因特定と修正内容

### 1. `RecordContentController::index`(N+1問題)

- **ファイル**: `src/app/Http/Controllers/RecordContentController.php`
- **原因**: ホーム画面表示用の記録日ループ内で、`record_state`ごとに `RecordMenu::where('record_state_id', ...)->exists()` と `->get()->load(['menu','category'])` を毎回発行しており、クエリ数が記録日数にほぼ比例して増加していた(700件 → 2,862クエリ)。
- **修正**: ループに入る前に `with(['recordMenus.menu', 'recordMenus.category'])` で一括Eager Loadし、ループ内のクエリ発行をゼロにした。`hasRecordMenu` の判定も再クエリ(`exists()`)ではなく、既に読み込み済みのコレクションの `isNotEmpty()` に変更。

### 2. `RecordRankingController::index`(冗長なソート + レスポンス肥大化)

- **ファイル**: `src/app/Http/Controllers/RecordRankingController.php`
- **原因A(冗長な計算)**: メニューごとのベスト記録(最大重量・最大ボリューム)を求めるために、同一コレクションに対して `sortByDesc()` を列ごとに2〜4回呼んでおり、O(n log n)のソートを不要に繰り返していた。
- **原因B(レスポンス肥大化、こちらが主要因)**: レスポンスに含める `menu` オブジェクトに、Eager Loadした `recordContents`(メニューごと数百件)がリレーションごと丸ごとシリアライズされて乗っており、19メニュー分でレスポンスサイズが約3MBに達していた。`json_encode` だけで2,140msを要していた。
- **検証**: フロントエンド `src/resources/js/components/ranking/userRecordRankingTable.vue` が参照しているのは `menu.content` と `menu.oneSide` のみであることを確認し、`menu.recordContents` はどこからも参照されていないことを確認した。
- **修正**:
  - `sortByDesc()` の複数回呼び出しを、1回のループでO(n)の最大値探索に置き換え(結果は同一)。
  - レスポンスに含める `menu` に対して `makeHidden('recordContents')` を呼び、不要なリレーションデータをJSONシリアライズ対象から除外。

## 計測結果(After)

| エンドポイント | 所要時間 | クエリ数 | 備考 |
|---|---|---|---|
| `GET /recordContent`(ホーム画面) | 350〜850ms | 4 | クエリ数 2,862 → 4 |
| `GET /recordRanking/user` | 570〜950ms | 6 | レスポンスサイズ 3,004,934 bytes → 15,393 bytes |

## テスト・検証

- `php artisan test` 実行前に、sentinelレコード(`sentinel@example.com`)を作成し、テストDBが開発用DBから正しく分離されていることを確認(過去に開発DBのデータが消えた事故があったため必須の手順)。
- 分離OKを確認した上で全テストを実行 → 既存テスト2件(`Tests\Unit\ExampleTest`, `Tests\Feature\ExampleTest`)ともPASS、リグレッションなし。
- 確認用のsentinelユーザーは削除済み。
- パフォーマンス計測用に投入した検証データ(record_states 700件など)は、今後の計測にも再利用できるようユーザー判断により開発DBに残置。

## 今後の検討事項(第1弾時点、以下は第2弾で対応済み)

- ~~`RecordRankingController` の残り所要時間(500〜900ms)は、DB側で全 `record_contents` を読み込みPHPで最大値計算する設計に起因する。さらに詰める場合はSQL側の集計(ウィンドウ関数によるargmax等)への変更が考えられる。~~ → 第2弾で対応。
- `RecordController::index` は `whereNotNull('updated_at')->get()` で全件取得しており(パターンC: 無制限の全件取得)、現状のデータ量では十分高速だが、ユーザーあたりの記録件数が将来大きく増える場合は `latest()` のみで完結するロジックへの見直しが有効。(未対応)

---

## 第2弾: 本番規模(100ユーザー×10万件/ユーザー)での検証とSQL集計化

「本番環境では100ユーザー、record_contentsに1ユーザー当たり10万件のデータがある」という前提を受け、この規模で挙動を再検証した。

### 多テナント環境の再現

第1弾の検証は `test@gmail.com`(user_id=1)単独で約8,500件のデータのみだったため、`record_contents` テーブル全体に占める対象ユーザーの割合が約99.5%と極端に高く、MySQLのオプティマイザが「フルテーブルスキャンの方が速い」と判断する状態だった(=本番の「100ユーザー中の1人」という選択率1%とは性質が異なる)。これを再現するため、代表サンプルとして以下を追加投入した。

- 新規ユーザー9人(`loadtest1〜9@example.com`)を作成し、各ユーザーに4カテゴリ・20メニュー・500 record_states・2,000 record_menus・約100,000 record_contentsを投入。
- 既存 `user_id=1` にも追加投入し、record_contentsを約100,000件まで積み増し。
- 結果: `record_contents` テーブル全体で約1,000,178件、対象ユーザー(user_id=1)の占有率は約10%。100ユーザー×10万件(合計1,000万件)の場合、対象ユーザーの占有率はさらに低く(約1%)なり、インデックスによる絞り込みはより有利になる方向のため、今回の検証結果は本番規模でも同様以上に成立すると判断した。

### 判明した問題と対応

1. **`RecordContentController::index` は多テナント化後も正常**: クエリ数は4のまま変わらず、所要時間はuser_id=1のrecord_states件数増加(700→1,192件)に比例して増加(350ms→650ms程度)。N+1の再発なし。

2. **`RecordRankingController::index` のウィンドウ関数(ROW_NUMBER)はMySQLの実行計画が不安定**:
   - 第1弾で実装したウィンドウ関数(`ROW_NUMBER() OVER (PARTITION BY menu_id ORDER BY volume DESC)`)による1クエリでのargmax取得は、単一ユーザーの検証(8,500件規模)では高速だったが、多テナント化後(対象ユーザー行が全体の10%程度)は `EXPLAIN` で `Using filesort` が発生し、2,000〜3,400ms程度まで悪化した。MySQLの現バージョン(8.0.32)ではウィンドウ関数のPARTITION/ORDER句にインデックスの順序をうまく利用できないことが原因。
   - **対応**: ウィンドウ関数をやめ、「`GROUP BY`でメニューごとの最大値を求める」→「その最大値を持つ行を`JOIN`で引く」という古典的な方法に書き換えた。
   - さらに、`(user_id, menu_id, 対象カラム)` の複合インデックスをカラムごとに用意することで、GROUP BY集計が「loose index scan」(インデックスのみで完結する集計)になり、対象ユーザーの行数に比例したコストで済むようにした(テーブル全体のスキャンを回避)。
   - **`STRAIGHT_JOIN`が必須だった**: 上記のJOINクエリは、ヒントなしだとMySQLのオプティマイザが結合順序を誤り(対象ユーザーの全行を先に読んでからJOIN条件を絞り込む非効率なプラン)を選ぶことがあった(`right_volume`/`left_volume`列で実際に発生し、290〜420msかかっていた)。`STRAIGHT_JOIN`でクエリに書いた通りの結合順序(件数の少ない集計結果側を先に評価)を強制することで、30ms程度まで改善した。
   - weight系(weight/right_weight/left_weight)の3列同時MAX集計も、1クエリにまとめると複合インデックスを1つしか活かせず一時テーブルを伴っていたため、列ごとに専用インデックス+単純なGROUP BYクエリへ分割した(150〜260ms → 25ms程度)。

3. **正しさの検証**: 修正前(全件ロード+PHPでの最大値探索)と修正後(SQL集計)の出力を、実際に両方の実装を動かして全メニュー分突き合わせるスクリプトを作成し、一致(`mismatches=0`)を確認した。

4. **マイグレーション追加**: `src/database/migrations/2026_07_23_000001_add_ranking_indexes_to_record_contents_table.php` で、`record_contents` テーブルに以下の複合インデックスを追加した。
   - `idx_rc_user_menu_weight` (user_id, menu_id, weight)
   - `idx_rc_user_menu_rweight` (user_id, menu_id, right_weight)
   - `idx_rc_user_menu_lweight` (user_id, menu_id, left_weight)
   - `idx_rc_user_menu_volume` (user_id, menu_id, volume)
   - `idx_rc_user_menu_rvolume` (user_id, menu_id, right_volume)
   - `idx_rc_user_menu_lvolume` (user_id, menu_id, left_volume)

### 計測結果(多テナント・約100万件規模、user_id=1は約10万件)

| エンドポイント | インデックス追加前 | インデックス+STRAIGHT_JOIN後 |
|---|---|---|
| `GET /recordContent`(ホーム画面) | 630〜2,000ms(データ量増加に比例、N+1なし) | 630〜650ms |
| `GET /recordRanking/user` | 2,159〜3,431ms(ウィンドウ関数、filesort発生) | 185〜200ms |

### テスト・検証(第2弾)

- 追加投入時に型エラー(`generateAndInsertForUser()`の引数型ヒントが`array`だったが`Collection`を渡していた)が発生し、部分的に作成されたダミーユーザー(id=9)とその関連データを一度削除してから再実行した。
- インデックス作成は最初に検証目的で`DB::statement`で直接作成し、効果を確認した後、正式にマイグレーションファイル化して`php artisan migrate`で再作成した(手動作成時に外部キー制約用インデックスを誤って削除しそうになったため、一度復元してからマイグレーションを適用)。
- `php artisan test` 実行前にsentinelレコードでテストDB分離を再確認 → 分離OK、既存テスト2件ともPASS。
- 検証用に追加投入した多テナントデータ(新規ユーザー9人、合計約100万件)はユーザー判断により開発DBに残置。

### 今後の検討事項(第2弾時点で未対応)

- `RecordContentController::index` はホーム画面表示のたびにユーザーの全トレーニング履歴(record_states全件)を取得しており、無期限に線形増加する設計(パターンC)。本検証でも user_id=1 の record_states が約1,192件まで増えたことで所要時間が増加している。ユーザーが長期間使い続けるほど遅くなるため、ページネーションまたは直近N件/期間フィルタへの変更が望ましいが、レスポンス形式がフロントエンドと密結合しているため、フロントエンドとの合意の上での対応が必要。
- `RecordController::index` の全件取得(パターンC)は引き続き未対応。

# 体重管理・記録画面 改善設計

## 背景・目的

前回実装した体重管理機能(`.claude/specs/2026-07-27-weight-record-analysis-design.md`、`.claude/plans/2026-07-27-weight-management-screen.md`)を実際にブラウザで使ってみたユーザーからのフィードバックに基づき、以下5点を改善する。

1. トレーニング記録画面(`recordContents.vue`)に埋め込んだ体重記録フォームが、既存のUI/UXを変えてしまっている
2. APIでのデータ取得が表示に関わる画面にローディング表示がない
3. 体重管理ページで日付を選んで過去の体重を登録・編集できない(今日に固定)
4. 新規ユーザー登録時、カテゴリー(部位)が何も登録されておらず、ユーザーは種目の親となる部位から手動で作る必要がある
5. 体重タグが固定5個(生理タグは不要)で、ユーザーが編集できない

## スコープ

含む:
- トレーニング記録画面から`WeightRecordForm`の埋め込みを削除し、Task19以前の表示のみに戻す
- 共通ローディングスピナーコンポーネントの新規作成と、API取得で表示が変わる画面(`recordContents.vue`・`userRecordRanking.vue`・`Calendar.vue`・`weightManagement.vue`)への適用
- 体重管理画面への日付選択(`<input type="date">`、デフォルト今日)追加。選択日に既存記録があれば読み込んで編集可能にする
- 新規ユーザー登録時(通常登録・Google登録の両方)に「胸・背中・足・腕・腹筋」の5カテゴリーを自動作成(メニューは作成しない)
- 体重タグをユーザー個別データ化(`weight_tags`に`user_id`追加)。新規登録時にデフォルト4タグ(食べすぎ・飲みすぎ・体調不良・運動)を自動作成。体重管理画面内の「タグを編集」セクションでユーザーがタグを追加・削除できる(合計5個まで)

含まない(スコープ外):
- タグの並び替え・色分け・アイコン設定
- 独自カレンダーUI(ネイティブ`<input type="date">`のみ使用)
- ローディング演出の作り込み(Tailwindの`animate-spin`による単純な回転アイコンのみ)

## データモデル変更

- `weight_tags`テーブルに`user_id`(外部キー、`users`テーブル参照、`cascadeOnDelete`)を追加する
  - 体重管理機能自体がまだ本番未リリースのため、既存のグローバルタグ(生理を含む5個、`WeightTagSeeder`由来)は開発環境のデータとして扱い、本番向けの移行処理は不要。ローカル開発DBは`php artisan migrate:fresh --seed`相当で作り直せば良い
- `WeightTagSeeder`は「生理」を除いた4タグ(食べすぎ・飲みすぎ・体調不良・運動)を、シーディング対象ユーザー(既存の`UserSeeder`が作るテストユーザー)に対して`user_id`付きで作成するよう更新する
- `categories`・`menus`テーブルへのスキーマ変更は不要(既存構造のまま、登録時にレコードをINSERTするだけ)

## バックエンドAPI変更

### タグCRUD(新規)

- `POST /api/weight/tags` — `WeightController::storeTag()` 新規タグを追加する。`WeightService::addTag(int $userId, string $content): WeightTag`
  - ユーザーの既存タグ数が5件に達している場合は422を返す(`StoreWeightTagRequest`でのカスタムバリデーションではなく、Service層でカウントしてControllerが422を返す形。理由: 「そのユーザーの既存件数」というデータ依存のチェックのため)
- `DELETE /api/weight/tags/{id}` — `WeightController::destroyTag()` タグを削除する。`WeightService::deleteTag(int $userId, int $tagId): void`
  - 他ユーザーのタグを削除できないよう、`WeightTag::where('user_id', $userId)->findOrFail($tagId)`で所有権を確認する
  - 中間テーブル(`record_state_weight_tag`)の関連レコードは`cascadeOnDelete`で自動削除される

### 既存APIの変更

- `GET /api/weight/tags` は、認証ユーザー自身のタグのみ返すよう`WeightService::getAllTags()`を`getAllTags(int $userId)`に変更し、`where('user_id', $userId)`を追加する
- 日付を指定した単日の体重取得は新規APIを作らず、既存の`GET /api/weight?from=X&to=X`をそのまま使う(`from`と`to`に同じ日付を渡す)

### ユーザー登録時のデフォルトデータ

- `RegisterController::register()`と`registerProviderUser()`の両方から呼べる共通処理として、`App\Services\Auth\RegisterService`(新規)に`setupDefaultData(int $userId): void`を作る
  - カテゴリー5件(胸・背中・足・腕・腹筋)を作成
  - 体重タグ4件(食べすぎ・飲みすぎ・体調不良・運動)を作成
  - メニューは作成しない

## フロントエンド変更

### 1. トレーニング記録画面(`recordContents.vue`)

Task19で追加した以下を削除し、元の状態に戻す。
- `<WeightRecordForm>`のテンプレート部分
- `import WeightRecordForm from "../weight/WeightRecordForm.vue";`
- `recordedAtParam`computedと`onWeightSaved`関数
- 体重記録は`/weight`画面からのみ行う

### 2. 共通ローディングスピナー

- `resources/js/components/common/LoadingSpinner.vue`を新規作成。Tailwindの`animate-spin`を使った回転円アイコンのみ
- 適用箇所(いずれも既存の「データ取得中です」等のテキスト表示、または表示なしの箇所を置き換え):
  - `recordContents.vue`(既存のテキストを置換)
  - `userRecordRanking.vue`(既存のテキストを置換)
  - `Calendar.vue`(既存のテキストを置換)
  - `weightManagement.vue`(新規追加、初回データ取得中に表示)

### 3. 体重管理画面(`weightManagement.vue`)の日付選択

- 期間切替ボタンの上に`<input type="date" v-model="selectedDate">`を追加(デフォルトは今日)
- 日付が変わるたび、`GET /api/weight?from=selectedDate&to=selectedDate`でその日の記録を取得する
  - 記録があれば`WeightRecordForm`の`initialBodyWeight`・`initialMemo`・`initialTagIds`に反映して編集可能にする
  - 記録がなければ空フォームになる
- 見出し「今日の体重を記録」は選択日に応じて動的に変える(例: 「2026-07-20の体重を記録」)

### 4. タグ編集セクション(`weightManagement.vue`内、新規)

- タグ一覧(チップ+削除ボタン)と、新規タグ名の入力欄+追加ボタンを表示
- 5個に達している場合は追加フォームを無効化し、「タグは5個までです」を表示
- 追加・削除後は`WeightRecordForm`が参照するタグ一覧(`useGetWeightTags`)を再取得する

## エラーハンドリング

- タグ追加時、5個上限に達していれば422を返し、フロントは「タグは5個までです」を表示して追加フォームを無効化する
- タグ削除は他ユーザーの所有物を削除できないよう、Service層で`user_id`スコープを必ず確認する
- 日付選択に未来日の制限は設けない(既存の`date_format:Y-m-d`バリデーションのみ)

## テスト方針

- バックエンド: 新規の`WeightService::addTag/deleteTag/getAllTags(userId)`、`RegisterService::setupDefaultData`、および`WeightController`の新規エンドポイントについてTDDでテストを書く
- フロントエンド: 既存同様、自動テスト基盤がないため`vue-tsc`型チェックと`npm run build`、および実ブラウザでの動作確認で担保する

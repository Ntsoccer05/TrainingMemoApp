# ホーム画面API・最新レコードAPIの「無制限全件取得」解消 設計書

- 作成日: 2026-07-23
- ステータス: 設計承認済み・未実装
- 関連: [2026-07-23-api-latency-investigation.md](./2026-07-23-api-latency-investigation.md)(パフォーマンス調査 第1弾・第2弾)で「今後の検討事項」として残された2件への対応設計。

## 背景

パフォーマンス調査(第2弾、user_id=1に約1,192日分・10万件超のデータで検証)で、以下2つのエンドポイントが「無制限の全件取得」設計(パターンC)であり、ユーザーが使い続けるほど線形に遅くなることが判明した。

1. `RecordContentController::index`(`GET /api/recordContent`、`category_id`も`recorded_at`も無い場合＝ホーム画面のカレンダー用)が、ユーザーの`record_states`を全件取得している。
2. `RecordController::index`(`GET /api/record`)が、`whereNotNull('updated_at')->get()`でユーザーの全`record_states`を取得している。

本設計は、この2つを解消するための調査結果と合意内容をまとめたもの。

## 対象2の調査結果:実は単純な無駄

`RecordController::index`のレスポンスのうち、フロントエンドが実際に参照しているのは`latestRecord`のみ(`grep`で確認、`isSetUpdated`・`updatedDateTime`・`createdDateTime`・`latestUpdated`・`latestCreated`はどこからも参照されていない未使用フィールド)。

現状の全件取得は「作成日時が最新のレコード」と「更新日時が最新のレコード」を比較し、より新しい方を`latestRecord`として返すためだけに使われている。これは`latest()->first()`を2回呼ぶだけで実現でき、**API契約(レスポンス形式)を変えずに解消できる**。設計判断を要さない単純な修正。

## 対象1の調査結果:カレンダーの「全履歴を先読み」という前提

`Calendar.vue`は、マウント時に一度だけ`useGetRecords()`経由で全履歴を取得し、以降は月を移動しても再フェッチせず、既に持っている全データだけで以下を行っている。

- カレンダー上の「筋トレ日」バー表示・ポップオーバー(部位・メニュー名表示)
- `selectedDay()`内での「その日は記録済みか」の重複判定(records.value を毎回全走査)

v-calendar(`^3.0.0-alpha.8`)は月が切り替わるたびに`update:to-page`イベントで`{month, year}`を発行することをソースコード(`node_modules/v-calendar/src/components/Calendar/Calendar.vue`)で確認済み。これを使えば月単位の追加取得が実装できる。

また調査の過程で、`RecordToday.vue`が`Calendar.vue`とは別に同じ`recordContent` APIを独自に呼んでおり、取得結果(`records`)を一切使わず`isLoaded`フラグを立てるためだけに使っていること(=ホーム画面表示のたびに同じAPIが2回発行されている)も判明した。ユーザー確認の上、これも本設計のスコープに含める。

## 合意した設計

### 1. `RecordController::index` の修正

- **API契約**: 変更なし。レスポンスは`{"status_code": 200, "latestRecord": ...}`のみとし、未使用フィールドは削除する。
- **Service層**(`.claude/rules/backend-architecture.md`のルールに従い新設):
  ```php
  // app/Services/Record/RecordService.php
  class RecordService
  {
      public function getLatestRecordState(int $userId): ?RecordState
      {
          $latestCreated = RecordState::where('user_id', $userId)->latest('created_at')->first();
          $latestUpdated = RecordState::where('user_id', $userId)->whereNotNull('updated_at')->latest('updated_at')->first();
          // updated_at が created_at より新しければ $latestUpdated、そうでなければ $latestCreated を返す
          // (レコードが1件も無ければ null)
      }
  }
  ```
- Controllerは`RecordService`を呼ぶだけの薄い層にする。

### 2. `RecordContentController::index` の修正(ホーム画面ブランチのみ)

- **追加パラメータ**(任意、`from`/`to`、`YYYY-MM-DD`形式):
  - 両方指定 → その範囲で絞り込み
  - 両方省略 → デフォルトで「当月を含む直近3ヶ月分の月初(今日の2ヶ月前の月の1日)〜今日」を適用
  - 片方のみ指定 → バリデーションエラー
- **FormRequest**: `GetRecordContentsRequest`を新設。`from`/`to`は`date_format:Y-m-d`、`to >= from`、片方のみの指定を禁止するルールを持つ。
- **Service層**:
  ```php
  // app/Services/RecordContent/RecordContentService.php
  class RecordContentService
  {
      public function getRecordsInRange(int $userId, Carbon $from, Carbon $to): Collection
      {
          // 既存のwith(['recordMenus.menu','recordMenus.category'])のロジックを移設し、
          // whereBetween('recorded_at', [$from, $to]) を追加
      }
  }
  ```
- **レスポンス形式**: 変更なし。取得範囲が狭まるだけで、既存の`records`配列の構造は維持する。

### 3. フロントエンド変更

**`useGetRecords.ts`(コンポーザブル)**: `getRecords(user_id, recorded_at, from?, to?)`のように`from`/`to`を追加パラメータとして受け取り、axiosの`params`に渡すようにする。呼ぶたびに`records.value`を置き換える既存の挙動は変えない(`SelectMenu.vue`など他の呼び出し元への影響を避けるため)。

**`Calendar.vue`**:
- 蓄積専用のstate `allRecords` を新設し、`records`(コンポーザブルの戻り値)が変化するたびに`record_id`で重複排除しながら`allRecords`へマージする。
- 取得済み月を`Set<string>`(`"YYYY-MM"`形式)で管理する。
- マウント時: `from`=直近3ヶ月の月初、`to`=今日 で取得。
- `<v-calendar>`に`@update:to-page="onPageChange"`を追加。月が切り替わるたびに`{year, month}`を受け取り、キーが未取得ならその月の`from`(月初)/`to`(月末)で追加取得 → `allRecords`にマージ。
- バー表示・ポップオーバー・`selectedDay()`の重複判定は、すべて`records`ではなく`allRecords`を参照するよう変更。

**`RecordToday.vue`**:
- `useGetRecords`の呼び出しを削除。
- `isLoaded`を使わず、親(`home.vue`)から受け取っている`compGetData` propをそのまま表示条件に使う。

### 4. インデックス追加

`record_states`テーブルには現状`user_id`単体のインデックスしかなく、`recorded_at`の範囲検索のために`(user_id, recorded_at)`の複合インデックスをマイグレーションで追加する。

### 5. テスト方針

- `RecordService::getLatestRecordState()`: 更新なしなら作成日時最新、更新ありなら更新日時と作成日時を比較して新しい方、レコードなしなら`null`を返すことをユニットテストで検証。
- `RecordContentService::getRecordsInRange()`: 範囲外のレコードが除外されること、`from`/`to`省略時に直近3ヶ月がデフォルト適用されることを検証。
- `GetRecordContentsRequest`: `from`のみ/`to`のみ指定時にバリデーションエラーになること、`to < from`が弾かれることを検証。
- 実装後、`performance-investigation`スキルの手法(tinker + クエリログ)で、100件規模のuser_id=1に対し「取得件数がユーザーの利用期間に関わらず直近3ヶ月分に収まる」ことを再計測して確認。
- `chrome-screen-check`で、カレンダーを数ヶ月戻す→バー/ポップオーバーが正しく表示される→ホーム画面表示時のAPI呼び出しが1回に減っている、をブラウザで確認。

## 非対象・留意点

- `SelectMenu.vue`など、`recorded_at`を指定した個別日付取得の他ブランチには影響しない(今回の`from`/`to`はホーム画面ブランチのみに追加)。
- カレンダーの「未来日」表示には影響しない(`to`のデフォルトは今日だが、記録は`recorded_at`が今日以前のもののみなので実害なし。将来日の記録が必要になった場合は別途検討)。

# 体重管理ページ レイアウト・目標体重機能 改善設計

## 背景・目的

前回実装した体重管理機能改善(`.claude/specs/2026-07-27-weight-feature-improvements-design.md`)を実際にブラウザで確認したところ、以下の問題・改善点が見つかった。

1. 目標体重を設定しても、グラフの注釈線がY軸の表示範囲外になり見えないことがある(実データ範囲のみでY軸が自動計算されるため)。加えて、設定後に保存できたかどうかの視覚的フィードバックが一切ない
2. 日付選択欄が「目標体重設定」のすぐ上にあり、実際には下部の体重記録フォームのための日付選択であることが分かりにくい
3. 体重管理ページなのに、最も頻度の高い操作である「体重を記録する」がページ下部にあり、毎回スクロールが必要
4. 目標体重に期限(いつまでに達成したいか)の概念がなく、モチベーションが続きにくい

## スコープ

含む:
- 「〇〇の体重を記録」フォーム(日付選択も含む)をページ最上部に移動する
- 目標体重設定の横に「現在○kg／目標まで±○kg」の差分表示を追加する
- 目標体重に任意の期限日(`target_weight_date`)を追加できるようにする。期限を設定した場合のみ「残り◯日」を差分表示と併記する。期限を過ぎている場合は残り日数を表示しない(特別な表示は行わない)
- グラフのY軸表示範囲に目標体重を含めるよう修正し、注釈線が必ず見えるようにする

含まない(スコープ外):
- 目標体重の期限に対するリマインダー通知・アラート機能
- 目標体重達成時の演出強化(既存の「目標達成！」バッジ表示は維持するのみ)
- ダッシュボード化(トレーニング記録・体重・ランキングの統合画面)は別途の設計検討として扱う

## データモデル変更

- `users`テーブルに`target_weight_date`(nullable date)を追加する
  - 既存の`target_weight`と同様、`users`テーブルに保持する(体重管理機能自体が本番未リリースのため、マイグレーション追加のみで移行処理は不要)

## バックエンドAPI変更

### 既存APIの変更

- `POST /api/weight/targetWeight`(`WeightController::updateTargetWeight`)
  - リクエストに`target_weight_date`(nullable, `date_format:Y-m-d`)を追加
  - `WeightService::updateTargetWeight(int $userId, float $targetWeight, ?string $targetWeightDate): User`にシグネチャ変更し、`target_weight_date`も同時に更新する
  - レスポンスに`target_weight_date`を追加する
- `GET /api/weight`(`WeightController::index`)
  - レスポンスの`target_weight`と並べて`target_weight_date`を追加する

## フロントエンド変更

### 1. `weightManagement.vue`のレイアウト変更

- テンプレートの並び順を以下に変更する:
  1. 見出し「体重管理」
  2. 「〇〇の体重を記録」フォーム(日付選択 + `WeightRecordForm`)
  3. 期間切替ボタン(1ヶ月/3ヶ月/6ヶ月)
  4. `WeightTargetSetting`(目標体重設定)
  5. `WeightChart`(グラフ)
  6. `WeightTagStats`(タグ別集計)
  7. `WeightTagEditor`(タグ編集)
  8. `WeightRecordModal`(モーダル)
- ロジック(`selectedDate`、`fetchSelectedDateRecord`等)は変更せず、テンプレート内の配置のみ変更する

### 2. `WeightTargetSetting.vue`の変更

- Propsに`targetWeightDate: string | null`を追加する
- 期限日の`<input type="date">`を目標体重入力の横に追加する(空欄可)
- 差分表示を追加する:
  - `targetWeight`と`latestBodyWeight`が両方存在する場合、「現在: {{ latestBodyWeight }}kg／目標まで: {{ diff > 0 ? '+' : '' }}{{ diff }}kg」を表示する(`diff = targetWeight - latestBodyWeight`)
  - `targetWeightDate`が設定されており、かつ今日以降の場合のみ「残り{{ daysLeft }}日」を併記する。期限を過ぎている場合は残り日数を表示しない
- `save()`時に`target_weight_date`も送信し、`emits("updated", { targetWeight, targetWeightDate })`のようにオブジェクトで返す(既存は数値のみのemit)

### 3. `weightManagement.vue`側の状態管理

- `targetWeightDate: Ref<string | null>`を追加し、`useGetWeightHistory`(またはAPIレスポンス)から取得する
- `onTargetWeightUpdated`を新しいemitの形(`{ targetWeight, targetWeightDate }`)に合わせて更新する

### 4. `WeightChart.vue`のY軸修正

- `chartOptions`の`yaxis`に`min`/`max`を明示的に計算して追加する
  - `min`: 体重データと目標体重の最小値から余白(例: 1kg)を引いた値
  - `max`: 体重データと目標体重の最大値に余白(例: 1kg)を足した値
  - 体重データが空の場合は目標体重のみを基準にする。両方存在しない場合は`min`/`max`を指定しない(ApexChartsの自動計算に任せる)

## エラーハンドリング

- 期限日のバリデーションは`date_format:Y-m-d`のみとし、過去日も許容する(体重記録の日付選択と同様の考え方)
- 期限日が未入力の場合は`null`として扱い、既存の目標体重のみの挙動と変わらない

## テスト方針

- バックエンド: `WeightService::updateTargetWeight`の`target_weight_date`保存、`WeightController`のレスポンスに`target_weight_date`が含まれることをTDDでテストする
- フロントエンド: 既存同様、`vue-tsc`型チェックと`npm run build`、実ブラウザでの動作確認で担保する(差分表示・残り日数表示・レイアウト変更が対象)

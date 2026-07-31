# コード品質レビュアープロンプトテンプレート

コード品質レビュアーサブエージェントを起動するときにこのテンプレートを使用します。

**目的：** 実装が適切に構築されていることを確認する（クリーン、テスト済み、保守可能）

**起動タイミング：** 仕様適合レビューがパスした後のみ。

**前提：軽量な静的解析は実行済み。** `npm run lint`（ESLint）/ `composer pint`（Pint、自動整形）は PostToolUse hook が変更ファイルに対してすでに自動実行し、フォーマット・未使用変数・到達不能コードなどは解消済み。したがって以下はレビュー対象から**除外**する（機械的に検出済み、または検出不能なら指摘しても直せない誤検知源になるため）：
- フォーマット・インデント・属性の並び順などのスタイル
- 未使用の変数・import、到達不能コード
- 命名規約のうち機械的にチェック可能なもの

**型チェック（typecheck / stan）は「初回レビューのみ」実行する。** `npm run typecheck`（vue-tsc）と `docker exec trainingmemoapp-app-1 composer stan`（Larastan）は hook には含めていない（Windows + Docker Desktop の bind mount 越しだと実行のたびに ~90〜170秒かかり、スコープを絞っても Laravel ブートストラップのI/Oコストが支配的なため速くならない。したがって「実行するかどうか」より「何回実行するか」がコストを左右する）。

- **REVIEW_ROUND: first**（そのタスクで最初に起動するコード品質レビュー）→ レビュアー自身が `npm run typecheck` と `composer stan` を実行し、型エラーを Critical/Important として報告する
- **REVIEW_ROUND: re-review**（同じタスクで、指摘を直した後の再レビュー）→ typecheck/stan は**実行しない**。初回レビュー時点でグリーンだった前提に立ち、今回の修正差分が新たに型エラーを持ち込んでいないかを目視で確認する程度に留める（再レビューのたびに全体スキャンし直すと 1 タスクで数百秒の重複コストになるため）

必ずディスパッチ時に `REVIEW_ROUND` を明記すること。

レビュアーの意識は、静的解析が原理的に判断できないもの——**アーキテクチャ判断、レイヤー違反（`.claude/rules/backend-architecture.md` 準拠）、YAGNI、テストが実際の挙動を検証しているか、セキュリティ、仕様との整合性**——に集中させる。

```
Task tool (general-purpose):
  Use template at requesting-code-review/code-reviewer.md

  DESCRIPTION: [task summary, from implementer's report]
  PLAN_OR_REQUIREMENTS: Task N from [plan-file]
  BASE_SHA: [commit before task]
  HEAD_SHA: [current commit]
  REVIEW_ROUND: [first | re-review]
```

**標準的なコード品質の懸念に加えて、レビュアーは以下を確認すべきです：**
- 各ファイルが明確に定義されたインターフェースを持つ1つの明確な責務を持っているか？
- ユニットが独立して理解・テストできるよう分解されているか？
- 実装が計画のファイル構造に従っているか？
- この実装が既に大きな新しいファイルを作成したか、または既存ファイルを大幅に増大させたか？（既存のファイルサイズは指摘しない — この変更が貢献したものに集中する）

**コードレビュアーが返すもの：** 強み、問題（Critical/Important/Minor）、評価

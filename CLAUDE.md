# CLAUDE.md

このファイルは、このリポジトリのコードを操作する際に Claude Code (claude.ai/code) へのガイダンスを提供します。

## プロジェクト概要

**Training Memo** は、以下の要素を持つトレーニング記録アプリケーションです:
- **バックエンド**: Laravel 9 と Filament 管理パネル(カテゴリ・メニュー・記録の管理用)
- **フロントエンド**: Vue 3 + TypeScript と Vite
- **データベース**: MySQL
- **認証**: Laravel Sanctum + Socialite(OAuth連携)

## クイックスタートコマンド

### インストール
```bash
cd src
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
cd ..
```

### 開発
```bash
# Dockerコンテナを起動(MySQL、Nginx、PHP、Mailhog、phpMyAdmin)
docker-compose up -d

# Vite dev server(ホットリロード)
cd src
npm run dev  # http://localhost:5173
```

Laravel バックエンドは Nginx コンテナ経由(`.env` の `WEB_PORT`)で、Vue フロントエンドの dev server は `http://localhost:5173` で動作します。

### ビルド
```bash
cd src
npm run build
```

### テスト
```bash
docker exec trainingmemo-app-1 php artisan test
docker exec trainingmemo-app-1 php artisan test tests/Feature
docker exec trainingmemo-app-1 php artisan test tests/Feature/SomeTest.php
```

## アーキテクチャ

### バックエンド (`src/app/`)

**サービスベース × レイヤードアーキテクチャ**(ドメインごとに分割し、各ドメイン内部は Controller → Service → Repository(任意) → Model のレイヤー構成)を採用する。

**詳細なレイヤー定義・ディレクトリ規約・命名規則・移行方針は [`.claude/rules/backend-architecture.md`](.claude/rules/backend-architecture.md) を参照すること。バックエンドの実装・変更に着手する前に必ず読むこと。**

現状のドメイン(コントローラー単位): Record, RecordContent, RecordMenu, Menu, RecordRanking, Auth, Inquiry

- **Http/Controllers/**: 各ドメインのAPIエンドポイント
- **Http/Requests/**: フォームリクエスト検証クラス(未整備箇所あり。新規は必須)
- **Models/**: Eloquent モデル(User, Record, RecordContent, RecordMenu, RecordState, Menu, Category, RankingRecord)
- **Filament/Resources/**: 管理パネル CRUD インターフェース(Category, Menu, RecordMenu, RecordContent, RecordState, User)
- **Services/** `.claude/rules/backend-architecture.md` のルールに従って今後追加していく(現時点では未作成)

### フロントエンド (`src/resources/js/`)

- **app.ts**: Vue アプリのエントリーポイント、`router/`・`store.ts` を読み込む
- **views/**: ページレベルコンポーネント
- **components/**: 再利用可能なコンポーネント
- **composables/**: Composition API のロジック共有
- **store.ts**: Vuex ストア
- **router/**: Vue Router 設定
- **types/**: TypeScript型定義

主な依存関係: Vue 3, Vue Router, Vuex, v-calendar, vuedraggable, tw-elements(Bootstrap系), Alpine.js

### データベース

- **接続**: MySQL(コンテナ名 `db`)
- **マイグレーション**: `src/database/migrations/`
- **シーダー**: `src/database/seeders/`(`UserSeeder` にテスト用アカウントあり)

## 主要な設定ファイル

- **src/.env**: DB・メール(Mailhog)・Sanctum設定
- **docker-compose.yml**: app(PHP) / web(Nginx) / db(MySQL) / phpmyadmin / mail(Mailhog)
- **.env**(ルート): `WEB_PORT` / `DB_PORT` / `PMA_PORT` などホスト側ポート設定

## 開発上の注意

- **Sanctum ステートフルドメイン**: `localhost:5173`(Vite dev server)からの認証付きリクエストを想定
- **メールテスト**: Mailhog は `http://localhost:8025`
- **管理パネル**: Filament 管理パネルは `/admin`
- **テスト**: `tests/Feature`・`tests/Unit` にサンプルのみ。新規機能は `test-driven-development` スキルに従いテストを先に書く
- **Lint/型チェック**: package.json に lint/typecheck スクリプトは未整備(将来追加を検討)

## 開発ワークフロー

このプロジェクトは `.claude/skills/` に配置されたスキル群を使って機能開発を進めます。Claude Code が状況に応じて自動的に読み込みます。

### フェーズ0: プロジェクト初期化(初回のみ)

```bash
/setup-project
```

対話的に `docs/` 内の永続ドキュメント(product-requirements, functional-design, architecture など)を作成します。

### 標準の機能開発フロー

| ステップ | スキル | 出力先 |
|---------|--------|--------|
| 1. 設計・要件整理 | `brainstorming` | 設計メモ |
| 2. 実装計画作成 | `writing-plans` | 実装計画 |
| 3. 計画を実行 | `subagent-driven-development` または `executing-plans` | コード・テスト・コミット |
| 4. 完了処理 | ご自身で判断 | `git merge` / `git push` / PR 作成 |

### 補助スキル

| 状況 | スキル |
|------|--------|
| バグ・テスト失敗 | `systematic-debugging` |
| 実装前(TDD) | `test-driven-development` |
| 完了宣言の前 | `verification-before-completion` |
| コードレビュー依頼 | `requesting-code-review` |
| レビュー受け取り | `receiving-code-review` |
| 複数の独立した問題 | `dispatching-parallel-agents` |
| 機能ブランチの隔離 | `using-git-worktrees` |
| フロントエンド画面の動作確認(実ブラウザ) | `chrome-screen-check` |
| APIが遅い・レイテンシ調査 | `performance-investigation` |
| バックエンドの実装・変更 | `.claude/rules/backend-architecture.md` を先に読む |

### 永続ドキュメント更新の判定

実装完了後、以下の基準で `docs/` を更新してください:

- 新機能追加 → `docs/product-requirements.md`
- DB スキーマ変更 → `docs/functional-design.md`
- システム構成変更 → `docs/architecture.md`
- コーディング規約追加 → `docs/development-guidelines.md`
- バックエンドのレイヤー・ドメイン構成ルール変更 → `.claude/rules/backend-architecture.md`

## Claude Code の権限設定

`.claude/settings.json`(個人設定は `.claude/settings.local.json`、いずれも非コミット)を参照。主に以下を許可:

- `composer install` / `npm install`
- `php artisan make:model|make:controller|make:migration|make:request`
- `php artisan migrate` / `migrate:rollback` / `db:seed`
- `npm run build` / `npm run dev`
- `docker-compose exec|restart|down|up|ps|logs|build`
- `docker exec trainingmemo-app-1 *`

## デバッグ

- **Laravel**: `.env` で `APP_DEBUG=true` にしてスタックトレースを表示
- **データベース**: phpMyAdmin(`.env` の `PMA_PORT`)、または `docker exec -it db mysql -u root -p`
- **ログ**: `src/storage/logs/laravel.log`
- **メール**: Mailhog(`http://localhost:8025`)

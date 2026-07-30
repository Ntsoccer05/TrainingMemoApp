# Training Memo

トレーニング記録アプリケーション。Laravel 9(API + Filament管理パネル)と Vue 3 + TypeScript(フロントエンド)で構成されたポートフォリオプロダクトです。

**本番環境**: https://training-memo.com/

## 技術スタック

| 領域 | 技術 |
|---|---|
| バックエンド | Laravel 9, Filament(管理パネル), Laravel Sanctum + Socialite(認証・OAuth) |
| フロントエンド | Vue 3 (Composition API), TypeScript, Vite, Vuex, Vue Router |
| データベース | MySQL |
| ローカル開発環境 | Docker Compose(app / web(Nginx) / db / phpMyAdmin / Mailhog) |
| 本番インフラ | AWS(Lambda + API Gateway 等、サーバーレス構成), Terraform |
| CI/CD | GitHub Actions |

## ディレクトリ構成

```
.
├── src/                # Laravelアプリケーション本体(バックエンドAPI + Vueフロントエンド)
├── docker/             # ローカル開発用Dockerイメージの定義(PHP, Nginxなど)
├── docker-compose.yml  # ローカル開発環境の起動設定
├── infra/
│   ├── terraform/      # 本番AWSインフラのIaC定義
│   ├── lambda/          # Terraform管理外の個別Lambda関数
│   └── bootstrap/       # 既存リソースをTerraform管理下に取り込むための一時スクリプト
├── docs/               # 設計メモ・実装計画・調査記録などの永続ドキュメント
├── .github/workflows/  # CI/CD(バックエンド/フロントエンドデプロイ、Terraform plan/apply)
└── phpmyadmin/         # phpMyAdminの永続化データ
```

アプリケーションのコード自体の詳細(バックエンド/フロントエンドの構成、アーキテクチャ規約)は [`src/README.md`](src/README.md) を参照してください。

## クイックスタート

### 前提

- Docker / Docker Compose
- Node.js(`src/package.json` の要求バージョンに準拠)

### セットアップ

ルート直下に `.env`(Docker Composeのホスト側ポート・DB接続情報)を作成します。サンプルファイルは用意していないため、以下の項目を含めて新規作成してください。

```env
WEB_PORT=1001
DB_PORT=3308
DB_NAME=trainingmemo
DB_USER=training0512
DB_PASSWORD=training0512
DB_ROOT_PASSWORD=trainingroot
PMA_PORT=9998
```

続けてアプリケーション本体をセットアップします(`src/.env` の設定項目は [`src/README.md`](src/README.md) を参照)。

```bash
cd src
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
cd ..
```

### 起動

```bash
# バックエンド(Nginx / PHP / MySQL / phpMyAdmin / Mailhog)
docker-compose up -d

# フロントエンド(Vite dev server、ホスト側で直接起動すること)
cd src
npm run dev  # http://localhost:5173
```

Laravel APIは Nginx コンテナ経由(ルートの `.env` の `WEB_PORT`)で、Vue フロントエンドの開発サーバーは `http://localhost:5173` で動作します。

> Windows上のDocker Desktopでは、Viteをコンテナ内(`docker exec` 経由)で起動するとバインドマウント越しのファイルI/Oが遅くCPU/IO競合が発生するため、**必ずホスト側で `npm run dev` を実行してください**。

### テスト

```bash
docker exec trainingmemo-app-1 php artisan test
```

## 開発ワークフロー

このリポジトリは [Claude Code](https://claude.com/claude-code) と `.claude/skills/` 配下のスキル群を使った開発フローを前提にしています。詳細は [`CLAUDE.md`](CLAUDE.md) を参照してください。

## インフラ・デプロイ

本番環境はAWS上のサーバーレス構成で稼働しており、`infra/terraform/` でIaC管理されています。移行の背景・設計は [`docs/infrastructure/serverless-migration-design.md`](docs/infrastructure/serverless-migration-design.md) を参照してください。デプロイは GitHub Actions(`.github/workflows/`)経由で行われます。

# Training Memo — アプリケーション本体

Laravel 9(API + Filament管理パネル)と Vue 3 + TypeScript(SPA)からなるトレーニング記録アプリケーションのソースコードです。リポジトリ全体の構成・ローカル環境の起動方法は [ルートのREADME](../README.md) を参照してください。

## Portfolio

https://training-memo.com/

## 構成

### バックエンド

- **フレームワーク**: Laravel 9
- **管理パネル**: Filament(カテゴリ・メニュー・記録の管理用、`/admin`)
- **認証**: Laravel Sanctum(SPA向けCookie認証) + Socialite(Google OAuth連携)
- **主なディレクトリ**
  - `app/Http/Controllers/` — 各ドメイン(Record, RecordContent, RecordMenu, Menu, RecordRanking, Auth, Inquiry)のAPIエンドポイント
  - `app/Http/Requests/` — フォームリクエスト検証クラス
  - `app/Services/` — ユースケース単位の業務ロジック
  - `app/Models/` — Eloquentモデル
  - `app/Filament/Resources/` — 管理パネルCRUD

アーキテクチャ規約(サービスベース×レイヤードアーキテクチャ)の詳細は [`../.claude/rules/backend-architecture.md`](../.claude/rules/backend-architecture.md) を参照してください。

### フロントエンド (`resources/js/`)

- Vue 3(Composition API) + TypeScript + Vite
- `views/` ページレベルコンポーネント、`components/` 再利用可能コンポーネント、`composables/` ロジック共有、`store.ts` (Vuex)、`router/` (Vue Router)
- 依存: v-calendar, vuedraggable, tw-elements(Bootstrap系), Alpine.js

もともとJavaScriptで書かれていたフロントエンドをTypeScriptへ全面移行した経緯があります。

## セットアップ

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
```

`.env` はサンプルファイルを用意していないため、標準的なLaravelの環境変数に加えて以下を設定してください。

| 変数 | 用途 |
|---|---|
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Docker Compose の `db` コンテナへの接続情報(ルートの `.env` と揃える) |
| `SANCTUM_STATEFUL_DOMAINS` | Sanctumのステートフル認証を許可するドメイン(Vite dev serverのポートを含める。フォールバックでポートが変わった場合は都度追記) |
| `VITE_API_BASE_URL` | Vite dev serverとAPI(Nginx経由)のポートが異なる場合に、axiosの`baseURL`を上書きする値 |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | Google OAuthログイン用(Socialite) |

`UserSeeder` によりログイン確認用アカウント(`test@gmail.com` / `password`)が作成されます。

## 開発

```bash
# Vite dev server(ホットリロード)。Docker Desktop(Windows)特有のI/O遅延を避けるため、
# コンテナ内(docker exec)ではなく必ずホスト側で起動する
npm run dev  # http://localhost:5173
```

Laravel APIはNginxコンテナ(ルートの`.env`の`WEB_PORT`)経由で動作します。

## ビルド

```bash
npm run build
```

## テスト

```bash
docker exec trainingmemo-app-1 php artisan test
docker exec trainingmemo-app-1 php artisan test tests/Feature
docker exec trainingmemo-app-1 php artisan test tests/Feature/SomeTest.php
```

フロントエンドの単体テストは [Vitest](https://vitest.dev/) を使用しています(`environment: "node"`、DOM APIは使用不可)。

```bash
npm run test
```

## デバッグ

- **Laravel**: `.env` で `APP_DEBUG=true` にするとスタックトレースを表示
- **データベース**: phpMyAdmin(ルートの`.env`の`PMA_PORT`)、または `docker exec -it db mysql -u root -p`
- **ログ**: `storage/logs/laravel.log`
- **メール**: Mailhog(`http://localhost:8025`)

## OPcache preload

Windows上のDocker Desktopは `./src` バインドマウント越しのファイルI/Oが遅いため、`docker/php/php.ini` で `opcache.preload=/var/www/html/preload.php` を設定しています。preload対象は `preload-manifest.txt` に列挙した実測済みファイルのみです(vendor全体を対象にすると一部ファイルでpreloadがクラッシュするため)。

`composer install` / `composer update` で vendor の依存関係を変更した場合は、`preload.php` 冒頭のコメントに記載した手順で `preload-manifest.txt` を再生成し、`docker-compose restart app` でOPcacheを再読み込みしてください(再生成を忘れても致命的ではありませんが、速度改善の恩恵を受けられません)。

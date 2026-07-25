# SEO技術対策 設計書

## 背景・目的

「筋トレ」「ジム」「家トレ」等のトレーニング関連ワードでのGoogle検索流入を増やすため、技術的SEOの不足分を補う。既に `config/seo.ts` によるページ別 title/description/keywords/robots 設定、GA4計測、サイトマップ(S3ホスティング + `SitemapController` + CloudFront routing)は実装済み。

今回のスコープは技術的な穴埋めのみ（低リスク・既存実装への追加が中心）。コンテンツ拡充（LP本文強化、ブログ機能）は対象外とし、将来の別サイクルで扱う。

## 前提情報

- 本番ドメイン: `https://training-memo.com`（`.github/workflows/deploy-frontend.yml`・`deploy-backend.yml` で確認済み）
- OGP画像: 専用画像は用意せず、`src/index.html` の favicon で使われている既存アイコンURL `https://training-memo.s3.ap-northeast-1.amazonaws.com/icon.ico` を暫定使用。後日専用画像（1200x630程度のPNG/JPG）を用意した際は `config/seo.ts` の定数1箇所を差し替えるだけで済む構造にする。

## 対応項目

### 1. OGP / Twitter Card メタタグ

- `src/resources/js/config/seo.ts` の各ページエントリに以下を追加:
  - `ogTitle`（省略時は `title` を流用）
  - `ogDescription`（省略時は `description` を流用）
  - `ogImage`（共通で既存アイコンURLを使用。全ページ共通なので `COMMON_KEYWORDS` と同様に定数化してもよい）
- `src/resources/js/utils/setSeo.ts` の `useHead()` の `meta` 配列に `og:title` / `og:description` / `og:image` / `og:type`(`website`固定) / `og:url`(下記canonicalと同じ値) / `twitter:card`(`summary_large_image`) / `twitter:title` / `twitter:description` / `twitter:image` を追加。
- ログイン必須ページ(noindex対象)にもOGPは付与してよい(SNSシェア自体は許容)。

### 2. canonical URL

- `setSeo.ts` 内で `window.location.origin + window.location.pathname` ではなく、本番ドメイン定数（例: `config/seo.ts` に `SITE_URL = "https://training-memo.com"` を追加）+ Vue Router の現在ルートの `path` を組み合わせて構築する。
- クエリパラメータ・ハッシュは含めない。
- `useHead()` の `link` に `{ rel: "canonical", href: canonicalUrl }` を追加。
- `setSeo()` の呼び出し側（各 view）は現状 `setSeo("home")` のように page キーのみ渡しているため、canonical URL計算に必要なパス情報は `vue-router` の `useRoute()` を `setSeo.ts` 内で取得する形にする（呼び出し側の変更を最小化する）。

### 3. 構造化データ(JSON-LD) — トップページのみ

- `home.vue` にのみ `WebApplication` タイプの JSON-LD を追加する。
- `useHead()` の `script` オプションで `type: "application/ld+json"` として埋め込む。
- 内容: `name`(トレメモ) / `description` / `url`(SITE_URL) / `applicationCategory: "HealthApplication"` / `operatingSystem: "Web"` / `offers: { "@type": "Offer", "price": "0", "priceCurrency": "JPY" }`。
- 実装方法: 呼び出し側(`home.vue`)は現状通り `setSeo("home")` のみ呼べばよく、`setSeo.ts` 内で `page === "home"` の場合にのみ JSON-LD の `script` を `useHead()` に追加する分岐を持たせる（呼び出し側の変更は不要）。
- 他ページへの展開は今回のスコープ外。

### 4. robots.txt に Sitemap 記載

- `src/public/robots.txt` に以下を追記:
  ```
  Sitemap: https://training-memo.com/tr-sitemap
  ```
- 既存の `User-agent: *` / `Disallow:`(空=全許可) はそのまま維持。

### 5. ログイン必須ページの robots 修正

- `config/seo.ts` の `selectMenu` / `record` / `addMenu` / `userRecordRanking` の `robots` を `"index, follow"` → `"noindex, follow"` に変更。
- 理由: これらは `requiresAuth: true` で未ログイン時は正常に機能しないページであり、インデックス対象として不適切。ただしページ間の内部リンク評価は妨げないよう `follow` は維持する。
- `login` / `register` 等の既存 `noindex, nofollow` ページは変更しない（フォームのみで内部リンク価値がないため現状のままでよい）。

## 変更ファイル一覧

- `src/resources/js/config/seo.ts`（OGP項目・SITE_URL定数・robots値の追加/修正）
- `src/resources/js/utils/setSeo.ts`（OGP/canonical/JSON-LDのuseHead反映、useRoute利用。home.vue側の変更は不要）
- `src/public/robots.txt`（Sitemap記載）

## テスト方針

- 既存にVueコンポーネントの単体テスト基盤があるか確認し、なければ `setSeo.ts` の純粋関数部分（canonical URL組み立てロジック等）を切り出してユニットテスト可能にする。
- 手動確認: `npm run build` 後、各ページで `document.head` にOGP/canonical/JSON-LDが正しく出力されることをブラウザで確認する（`chrome-screen-check` スキル相当の手動確認）。
- robots.txt はビルド不要な静的ファイルなので目視確認のみ。

## スコープ外（将来対応）

- トップページ本文へのキーワード訴求文・機能紹介セクションの追加
- お役立ちコラム/ブログ機能の新設
- SSR/プリレンダリング化
- 全ページへのJSON-LD展開・パンくずリスト構造化データ

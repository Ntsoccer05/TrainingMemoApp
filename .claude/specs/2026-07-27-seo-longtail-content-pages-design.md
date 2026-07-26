# ロングテールキーワード向けコンテンツページ追加 設計書

## 背景・目的

`docs/seo/seo-strategy.md` 3.2節「中期(コンテンツで戦う)」の対応。Google Search Console実データ(2026-07-26確認)で、直近3ヶ月の検索表示クエリが「筋トレメモ」ブランド名2件のみ(クリック0)であることが判明しており、一般語での露出がゼロに近い。「筋トレ アプリ」等のビッグキーワードはネイティブアプリ勢(筋トレMEMO・バーンフィット・PUMP UP・LIBRARY等、いずれもインストール前提)が強く占有しているため、正面対決を避け、ロングテールかつトレメモ固有の差別化軸(ブラウザ完結・インストール不要)を狙う。

前回セッションでCSR(クライアントサイドレンダリング)によりGooglebotの初回クロール時点ではJS実行前の固定metaしか見えない問題が判明・一部対応(JSON-LDの`index.html`静的埋め込み)済み。新規コンテンツページはこの制約を踏まえ、**Vue Router/JSに依存しない完全な静的HTML**として作る。

今回のスコープはページ3本の新規追加とサイトマップ反映のみ。SSGツール導入・全面的なコンテンツ拡充(ブログ機能等)は対象外。

## 前提情報

- Viteの`publicDir`は既定値(`src/public/`)で、ここに置いたファイルは無加工で`dist/`直下にコピーされる(既存の`favicon.ico`等と同じ挙動。`vite.config.ts`に`publicDir`の上書き設定なしを確認済み)
- `dist/`は既存の`deploy-frontend.yml`が`aws s3 sync dist/ s3://trainingmemo-spa-frontend --delete --exclude "backend/*"`で本番反映するため、**この配置だけでCI変更なしに本番デプロイパイプラインに乗る**
- サイトマップの実体は`SitemapController`が`https://training-memo.s3.ap-northeast-1.amazonaws.com/sitemap.xml`(Terraform管理外の別バケット`training-memo`)から取得して`/tr-sitemap`としてプロキシしている、リポジトリ管理外の手動更新ファイル
- 本アプリはログインなしでは記録の保存ができない(`auth:sanctum`必須)。「登録不要」と書くのは誇大表現になるため、コンテンツでは「インストール不要」と「記録保存には簡単な無料登録が必要」を正直に書き分ける

## 対応項目

### 1. 共有テンプレート(素のHTML、Vue非依存)

`src/public/guide/_template.html` は作成せず、3ページに直接同一構造を複製する(3ページのみのため共通化コストの方が高い)。各ページ共通で持つ要素:

- シンプルなヘッダー: ロゴ「トレメモ」+ トップページ(`/`)へのリンクのみ
- 本文(後述の個別構成)
- フッター: 他2本のガイドページへの相互リンク + 「無料で始める」ボタン(`/register`へのリンク)
- 最低限のインラインCSS(既存サイトの配色・フォントを流用、`src/index.html`の`<head>`にあるFont読み込みと同じ`Nunito`/FontAwesomeを使うかは軽量性を優先し不要と判断。素朴な見た目で良い)

### 2. ページ1: `src/public/guide/free-browser-training-log.html`

- URL: `https://training-memo.com/guide/free-browser-training-log.html`
- 狙うキーワード: 「トレーニング 記録 ブラウザ 無料」
- title: `トレーニング記録、ブラウザだけで無料でできます | トレメモ`
- description: ブラウザ完結・無料という2軸を含む1文(120字程度)
- 本文の切り口: 個人開発者の一人語りで「なぜアプリではなくブラウザで作ったか」(端末の空き容量を気にしたくない、複数端末で同じ記録を見たい等、開発の動機に基づく具体的な理由)を書く。競合(ネイティブアプリ勢)との対比は「インストール不要」ページ側に譲り、こちらは「無料で使い続けられる」点を軸にする

### 3. ページ2: `src/public/guide/no-install-training-log.html`

- URL: `https://training-memo.com/guide/no-install-training-log.html`
- 狙うキーワード: 「アプリ インストール不要 筋トレ記録」
- title: `筋トレ記録アプリ、インストール不要のWeb版という選択肢 | トレメモ`
- description: インストール不要・筋トレ記録という2軸
- 本文の切り口: `docs/seo/seo-strategy.md` 2章の競合調査(筋トレMEMO・バーンフィット・PUMP UP・LIBRARY・マイルーティン/FitPoint)がいずれもネイティブアプリである点に触れつつ、「有名アプリを否定するのではなく、インストールしたくない・お試しで使いたい人向けの選択肢」という謙虚な立ち位置で差別化する(競合をこき下ろさない)

### 4. ページ3: `src/public/guide/web-training-memo.html`

- URL: `https://training-memo.com/guide/web-training-memo.html`
- 狙うキーワード: 「Web版 トレーニングメモ」
- title: `トレーニングメモ、Web版で完結。トレメモの使い方 | トレメモ`
- description: Web版・トレーニングメモという2軸
- 本文の切り口: 実際の使い方(カレンダーで記録・メニュー登録等、`home.vue`等の実機能に基づく)を一人語りで紹介する、実質的な機能紹介ページ

### 5. 各ページ共通のmeta実装

各HTMLの`<head>`に個別に以下を静的記述(JSまたぎなし):

- `<title>` / `<meta name="description">` / `<link rel="canonical" href="ページ固有URL">`
- `og:type`(`article`)/ `og:title` / `og:description` / `og:image`(既存の`https://training-memo.com/og-image.jpg`を流用)/ `og:url`
- `twitter:card`(`summary_large_image`)等、`src/index.html`と同じ構成に揃える
- `<meta name="robots" content="index, follow">`

### 6. サイトマップ更新

- `https://training-memo.s3.ap-northeast-1.amazonaws.com/sitemap.xml` に3件の`<url>`エントリを追加(`lastmod`は追加作業日の実日付)
- 既存6件の`lastmod`(`2025-04-27`固定)はスコープ外とし、変更しない(内容が変わっていないページの日付を不用意に書き換えると、かえって鮮度シグナルの信頼性を損なう可能性があるため)
- AWS CLI(`trainingmemo-mfa`プロファイル)で直接アップロードする

### 7. 内部リンクの追加

- 既存トップページ(`home.vue`)のフッター相当箇所、もしくは適切な場所に、ガイドページ群への控えめなリンクを1箇所追加する(サイトマップだけでなく通常のクロールでも発見されやすくするため)
- 具体的な設置箇所はhome.vueの既存レイアウトを確認した上で実装時に決定する

## 変更ファイル一覧

- `src/public/guide/free-browser-training-log.html`(新規)
- `src/public/guide/no-install-training-log.html`(新規)
- `src/public/guide/web-training-memo.html`(新規)
- `src/resources/js/views/home.vue`(ガイドページへの内部リンク追加、軽微)
- S3(`training-memo`バケット)上の`sitemap.xml`(リポジトリ管理外、直接更新)

## テスト方針

- `npm run build`後、`dist/guide/*.html`が3件生成されていることを確認
- 各HTMLファイルを`docker exec`環境またはローカルで直接開き、レイアウト崩れがないか目視確認
- デプロイ後、`curl`で各URLの`<title>`/`description`/`canonical`が個別に正しいことを確認(JS実行不要でGooglebot視点を再現)
- サイトマップ更新後、`curl https://training-memo.com/tr-sitemap`で3件追加されていることを確認
- Search Consoleの「URL検査」で3ページのインデックス登録をリクエストする(手動、実装完了後にユーザーと確認)

## スコープ外(将来対応)

- SSGツール導入・Vue Routerへの統合
- サイトマップの完全自動化(動的lastmod生成の仕組み化)
- 比較記事(競合アプリとの機能比較表)等、追加のロングテールページ
- 個人開発ブログ・Zenn/Qiita/noteでの外部発信(3.2節の別項目、被リンク獲得は別サイクル)

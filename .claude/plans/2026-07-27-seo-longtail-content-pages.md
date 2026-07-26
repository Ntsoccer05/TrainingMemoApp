# ロングテールSEOコンテンツページ追加 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ロングテールキーワードを狙った静的HTMLガイドページを3本追加し、サイトマップと内部リンクに反映する。

**Architecture:** `src/public/guide/` 配下に、Vue Router/JSに依存しない完全な静的HTMLを3本配置する。Viteの既定`publicDir`挙動により`npm run build`で`dist/guide/*.html`へ無加工コピーされ、既存の`deploy-frontend.yml`でそのままS3へデプロイされる(CI変更不要)。home.vueに内部リンクを1箇所追加し、S3上のsitemap.xmlに3件追記する。

**Tech Stack:** 素のHTML/CSS(Vue非依存)、Vite(publicDir経由の静的配信)、既存のVue3コンポーネント(home.vue)への軽微な追記。

参照設計書: `.claude/specs/2026-07-27-seo-longtail-content-pages-design.md`

---

### Task 1: ガイドページ1「ブラウザで無料に記録」を作成

**Files:**
- Create: `src/public/guide/free-browser-training-log.html`

- [ ] **Step 1: ファイルを作成する**

```html
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>トレーニング記録、ブラウザだけで無料でできます | トレメモ</title>
  <meta name="description" content="筋トレの記録をブラウザだけで、無料で続けられるトレメモ。アプリのインストールも容量の心配もいりません。個人開発者が実際に使うために作った理由を書きました。">
  <link rel="canonical" href="https://training-memo.com/guide/free-browser-training-log.html">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="トレメモ">
  <meta property="og:title" content="トレーニング記録、ブラウザだけで無料でできます | トレメモ">
  <meta property="og:description" content="筋トレの記録をブラウザだけで、無料で続けられるトレメモ。アプリのインストールも容量の心配もいりません。">
  <meta property="og:image" content="https://training-memo.com/og-image.jpg">
  <meta property="og:url" content="https://training-memo.com/guide/free-browser-training-log.html">
  <meta property="og:locale" content="ja_JP">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="トレーニング記録、ブラウザだけで無料でできます | トレメモ">
  <meta name="twitter:description" content="筋トレの記録をブラウザだけで、無料で続けられるトレメモ。">
  <meta name="twitter:image" content="https://training-memo.com/og-image.jpg">
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", sans-serif;
      color: #333;
      line-height: 1.8;
      background: #fafafa;
    }
    .site-header { background: #6b7280; padding: 16px 24px; }
    .site-header .logo { color: #fff; text-decoration: none; font-size: 1.25rem; font-weight: bold; }
    main { max-width: 680px; margin: 0 auto; padding: 32px 20px 60px; }
    h1 { font-size: 1.6rem; line-height: 1.5; margin-bottom: 1.2em; }
    p { margin: 1em 0; }
    .site-footer { text-align: center; padding: 32px 20px 48px; border-top: 1px solid #e5e5e5; margin-top: 40px; }
    .footer-links a { color: #666; font-size: 0.85rem; text-decoration: none; margin: 0 4px; }
    .footer-links a:hover { text-decoration: underline; }
    .cta-button {
      display: inline-block; margin: 20px 0; padding: 12px 32px;
      background: linear-gradient(90deg,#f97316,#ec4899); color: #fff;
      text-decoration: none; border-radius: 999px; font-weight: bold;
    }
    .copyright { color: #999; font-size: 0.75rem; margin-top: 20px; }
  </style>
</head>
<body>
  <header class="site-header">
    <a href="https://training-memo.com/" class="logo">トレメモ</a>
  </header>
  <main>
    <h1>トレーニング記録、ブラウザだけで無料でできます</h1>

    <p>筋トレを始めた頃、記録をどう残すか結構悩んだ。</p>

    <p>最初はノートに手書きしていたけど、三日坊主で終わって、次にジムに行った時にはどのノートに書いたかも忘れていた。</p>

    <p>じゃあアプリを入れようと思って、筋トレ記録アプリをいくつかダウンロードしてみたこともある。でも結局続かなかった。理由は単純で、スマホの空き容量が気になったのと、開くのが地味に面倒だったから。</p>

    <p>そこで、ブラウザだけで完結するトレメモを作った。URLを開けばそれで終わり。インストールも、アプリのアップデート待ちも、容量を気にすることもない。</p>

    <p>料金は無料。個人で作って個人で運用しているだけなので、広告でガンガン収益化する予定も今のところない。純粋に、自分が欲しかったものを作っただけです。</p>

    <p>スマホでもPCでも、同じURLを開けば同じ記録が見られる。ジムではスマホで記録して、家ではPCで振り返る、といった使い方もできる。</p>

    <p>気になったら、まずは覗いてみてください。</p>
  </main>
  <footer class="site-footer">
    <p class="footer-links">
      <a href="/guide/free-browser-training-log.html">ブラウザで無料に記録する</a> ・
      <a href="/guide/no-install-training-log.html">インストール不要という選択</a> ・
      <a href="/guide/web-training-memo.html">Web版の使い方</a>
    </p>
    <a href="https://training-memo.com/register" class="cta-button">無料で始める</a>
    <p class="copyright">&copy; トレメモ</p>
  </footer>
</body>
</html>
```

- [ ] **Step 2: 構文を確認する**

Run: `docker exec trainingmemoapp-app-1 node -e "require('fs').readFileSync('/var/www/html/public/guide/free-browser-training-log.html','utf8')" && echo OK`
Expected: `OK`(ファイルが存在し読み込めることの確認。HTMLとしての構文チェックはTask 5のビルド確認で行う)

---

### Task 2: ガイドページ2「インストール不要という選択」を作成

**Files:**
- Create: `src/public/guide/no-install-training-log.html`

- [ ] **Step 1: ファイルを作成する**

```html
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>筋トレ記録アプリ、インストール不要のWeb版という選択肢 | トレメモ</title>
  <meta name="description" content="筋トレMEMOやバーンフィットなど有名アプリはたくさんあるが、まず試したいだけならインストール不要のトレメモという選択肢もある。個人開発者が考えた立ち位置の話。">
  <link rel="canonical" href="https://training-memo.com/guide/no-install-training-log.html">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="トレメモ">
  <meta property="og:title" content="筋トレ記録アプリ、インストール不要のWeb版という選択肢 | トレメモ">
  <meta property="og:description" content="まず試したいだけならインストール不要のトレメモという選択肢もある。">
  <meta property="og:image" content="https://training-memo.com/og-image.jpg">
  <meta property="og:url" content="https://training-memo.com/guide/no-install-training-log.html">
  <meta property="og:locale" content="ja_JP">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="筋トレ記録アプリ、インストール不要のWeb版という選択肢 | トレメモ">
  <meta name="twitter:description" content="まず試したいだけならインストール不要のトレメモという選択肢もある。">
  <meta name="twitter:image" content="https://training-memo.com/og-image.jpg">
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", sans-serif;
      color: #333;
      line-height: 1.8;
      background: #fafafa;
    }
    .site-header { background: #6b7280; padding: 16px 24px; }
    .site-header .logo { color: #fff; text-decoration: none; font-size: 1.25rem; font-weight: bold; }
    main { max-width: 680px; margin: 0 auto; padding: 32px 20px 60px; }
    h1 { font-size: 1.6rem; line-height: 1.5; margin-bottom: 1.2em; }
    p { margin: 1em 0; }
    .site-footer { text-align: center; padding: 32px 20px 48px; border-top: 1px solid #e5e5e5; margin-top: 40px; }
    .footer-links a { color: #666; font-size: 0.85rem; text-decoration: none; margin: 0 4px; }
    .footer-links a:hover { text-decoration: underline; }
    .cta-button {
      display: inline-block; margin: 20px 0; padding: 12px 32px;
      background: linear-gradient(90deg,#f97316,#ec4899); color: #fff;
      text-decoration: none; border-radius: 999px; font-weight: bold;
    }
    .copyright { color: #999; font-size: 0.75rem; margin-top: 20px; }
  </style>
</head>
<body>
  <header class="site-header">
    <a href="https://training-memo.com/" class="logo">トレメモ</a>
  </header>
  <main>
    <h1>筋トレ記録アプリ、インストール不要のWeb版という選択肢</h1>

    <p>筋トレMEMOやバーンフィット、PUMP UPなど、筋トレ記録アプリはすでにたくさんある。どれも機能が充実していて、本気で使うならそちらの方が向いていることも多いと思う。</p>

    <p>ただ、個人的には「まず試してみたい」段階でアプリをインストールするのに少し抵抗があった。スマホの空き容量、通知の許可、アカウント登録……ちょっとしたハードルが積み重なっていく感じがする。</p>

    <p>トレメモは、そのハードルを無くすことだけを考えて作った、ブラウザで動くWebアプリです。</p>

    <p>リンクを開けばそのまま使える。試してみて合わなければ、アンインストール操作すらいらない。タブを閉じるだけでいい。</p>

    <p>本格的に筋トレを追い込みたい人には、有名アプリの方が機能面で満足度が高いはず。トレメモは、そこまで作り込まれた機能は正直ないです。それでも「まず記録する習慣をつけたい」「インストールせずに使いたい」という人には、選択肢の一つになるかなと思っています。</p>

    <p>ちなみに記録を保存するには、無料の会員登録だけは必要です(メールアドレス、またはGoogleアカウントでログインできます)。インストールは不要ですが、この一手間だけはご了承ください。</p>
  </main>
  <footer class="site-footer">
    <p class="footer-links">
      <a href="/guide/free-browser-training-log.html">ブラウザで無料に記録する</a> ・
      <a href="/guide/no-install-training-log.html">インストール不要という選択</a> ・
      <a href="/guide/web-training-memo.html">Web版の使い方</a>
    </p>
    <a href="https://training-memo.com/register" class="cta-button">無料で始める</a>
    <p class="copyright">&copy; トレメモ</p>
  </footer>
</body>
</html>
```

- [ ] **Step 2: 構文を確認する**

Run: `docker exec trainingmemoapp-app-1 node -e "require('fs').readFileSync('/var/www/html/public/guide/no-install-training-log.html','utf8')" && echo OK`
Expected: `OK`

---

### Task 3: ガイドページ3「Web版の使い方」を作成

**Files:**
- Create: `src/public/guide/web-training-memo.html`

- [ ] **Step 1: ファイルを作成する**

```html
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>トレーニングメモ、Web版で完結。トレメモの使い方 | トレメモ</title>
  <meta name="description" content="トレメモはカレンダー形式で日々のトレーニングを記録できるWebアプリ。よく使うメニューの登録から記録の続け方まで、実際の使い方を紹介します。">
  <link rel="canonical" href="https://training-memo.com/guide/web-training-memo.html">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="article">
  <meta property="og:site_name" content="トレメモ">
  <meta property="og:title" content="トレーニングメモ、Web版で完結。トレメモの使い方 | トレメモ">
  <meta property="og:description" content="カレンダー形式で日々のトレーニングを記録できるWebアプリ、トレメモの使い方を紹介します。">
  <meta property="og:image" content="https://training-memo.com/og-image.jpg">
  <meta property="og:url" content="https://training-memo.com/guide/web-training-memo.html">
  <meta property="og:locale" content="ja_JP">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="トレーニングメモ、Web版で完結。トレメモの使い方 | トレメモ">
  <meta name="twitter:description" content="カレンダー形式で日々のトレーニングを記録できるWebアプリ、トレメモの使い方を紹介します。">
  <meta name="twitter:image" content="https://training-memo.com/og-image.jpg">
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", sans-serif;
      color: #333;
      line-height: 1.8;
      background: #fafafa;
    }
    .site-header { background: #6b7280; padding: 16px 24px; }
    .site-header .logo { color: #fff; text-decoration: none; font-size: 1.25rem; font-weight: bold; }
    main { max-width: 680px; margin: 0 auto; padding: 32px 20px 60px; }
    h1 { font-size: 1.6rem; line-height: 1.5; margin-bottom: 1.2em; }
    p { margin: 1em 0; }
    .site-footer { text-align: center; padding: 32px 20px 48px; border-top: 1px solid #e5e5e5; margin-top: 40px; }
    .footer-links a { color: #666; font-size: 0.85rem; text-decoration: none; margin: 0 4px; }
    .footer-links a:hover { text-decoration: underline; }
    .cta-button {
      display: inline-block; margin: 20px 0; padding: 12px 32px;
      background: linear-gradient(90deg,#f97316,#ec4899); color: #fff;
      text-decoration: none; border-radius: 999px; font-weight: bold;
    }
    .copyright { color: #999; font-size: 0.75rem; margin-top: 20px; }
  </style>
</head>
<body>
  <header class="site-header">
    <a href="https://training-memo.com/" class="logo">トレメモ</a>
  </header>
  <main>
    <h1>トレーニングメモ、Web版で完結。トレメモの使い方</h1>

    <p>トレメモは、日々のトレーニングをカレンダー形式で記録できるWebアプリです。</p>

    <p>使い方はシンプルで、トップページのカレンダーから記録したい日を選んで、その日にやった種目・重量・回数を入力するだけ。</p>

    <p>よく使うトレーニングメニューは事前に登録しておけるので、毎回同じ内容を入力し直す必要はありません。</p>

    <p>記録した日はカレンダー上に印がつくので、月単位でどれくらい継続できているかが一目でわかります。継続が苦手な自分にとって、この可視化が地味に効きました。</p>

    <p>ログインには、メールアドレスとパスワードのほか、Googleアカウントでのログインにも対応しています。</p>

    <p>もともと自分用のメモとして作ったものですが、同じように「トレーニングを続けたいけどうまく記録が続かない」という人の役に立てば、と思って公開しています。</p>
  </main>
  <footer class="site-footer">
    <p class="footer-links">
      <a href="/guide/free-browser-training-log.html">ブラウザで無料に記録する</a> ・
      <a href="/guide/no-install-training-log.html">インストール不要という選択</a> ・
      <a href="/guide/web-training-memo.html">Web版の使い方</a>
    </p>
    <a href="https://training-memo.com/register" class="cta-button">無料で始める</a>
    <p class="copyright">&copy; トレメモ</p>
  </footer>
</body>
</html>
```

- [ ] **Step 2: 構文を確認する**

Run: `docker exec trainingmemoapp-app-1 node -e "require('fs').readFileSync('/var/www/html/public/guide/web-training-memo.html','utf8')" && echo OK`
Expected: `OK`

---

### Task 4: home.vueにガイドページへの内部リンクを追加

**Files:**
- Modify: `src/resources/js/views/home.vue:1-14`

- [ ] **Step 1: テンプレート末尾にリンクセクションを追加する**

`src/resources/js/views/home.vue` の `<template>` を以下に置き換える:

```html
<template>
  <div class="h-full mt-8">
    <!-- トレーニング記録画面へ遷移 -->
    <RecordToday :compGetData="compGetData" />
    <div class="bar-kind w-full mt-2 mb-2">
      <div class="category-1 grid grid-cols-4 ml-10 md:ml-96 items-center mb-1">
        <div class="event-bar1 h-0.5 w-5 bg-red-600 col-start-3 ml-auto"></div>
        <p class="text-sm col-start-4">：筋トレ日</p>
      </div>
    </div>
    <!-- カレンダーコンポーネント -->
    <Calendar @compGetData="IsCompGetData" />
    <div class="text-center text-xs text-gray-400 mt-8 mb-4">
      <a href="/guide/free-browser-training-log.html" class="mx-1 hover:underline">ブラウザで無料に記録する</a>・
      <a href="/guide/no-install-training-log.html" class="mx-1 hover:underline">インストール不要という選択</a>・
      <a href="/guide/web-training-memo.html" class="mx-1 hover:underline">Web版の使い方</a>
    </div>
  </div>
</template>
```

- [ ] **Step 2: 変更内容を確認する**

Run: `docker exec trainingmemoapp-app-1 grep -c "guide/" resources/js/views/home.vue`
Expected: `3`(3つのガイドページへのリンクが含まれること。実際のビルド可否はTask 5で確認する)

---

### Task 5: ビルドして静的ファイルが正しく出力されることを確認する

**Files:**
- なし(検証のみ)

- [ ] **Step 1: フロントエンドをビルドする**

Run: `docker exec trainingmemoapp-app-1 npm run build`
Expected: `vite v3.2.3 building for production...` に続き `✓ ... modules transformed.` が表示され、エラーなく終了する

- [ ] **Step 2: 3つのガイドページがdist配下に出力されていることを確認する**

Run: `docker exec trainingmemoapp-app-1 sh -c "ls dist/guide/"`
Expected:
```
free-browser-training-log.html
no-install-training-log.html
web-training-memo.html
```

- [ ] **Step 3: 各ページのtitleタグが個別に正しいことを確認する**

Run: `docker exec trainingmemoapp-app-1 sh -c "grep '<title>' dist/guide/*.html"`
Expected:
```
dist/guide/free-browser-training-log.html:  <title>トレーニング記録、ブラウザだけで無料でできます | トレメモ</title>
dist/guide/no-install-training-log.html:  <title>筋トレ記録アプリ、インストール不要のWeb版という選択肢 | トレメモ</title>
dist/guide/web-training-memo.html:  <title>トレーニングメモ、Web版で完結。トレメモの使い方 | トレメモ</title>
```

- [ ] **Step 4: home.vueのビルド後アセットにガイドリンクが含まれることを確認する**

Run: `docker exec trainingmemoapp-app-1 sh -c "grep -l 'guide/free-browser-training-log' dist/assets/home.*.js"`
Expected: `dist/assets/home.<hash>.js` のパスが1行出力される(home.vueのバンドルにリンクが含まれていることの確認)

---

### Task 6: サイトマップ(S3上のsitemap.xml)に3件追加する

**Files:**
- 変更対象はリポジトリ外(S3バケット `training-memo` の `sitemap.xml`)

- [ ] **Step 1: 現在のsitemap.xmlを取得する**

Run: `curl -s https://training-memo.s3.ap-northeast-1.amazonaws.com/sitemap.xml -o /tmp/sitemap.xml && cat /tmp/sitemap.xml`
Expected: 既存6件の`<url>`エントリ(`/` `/login` `/register` `/password/forget` `/inquiry` `/recordRanking`)が出力される

- [ ] **Step 2: 3件の`<url>`エントリを追記した新しいsitemap.xmlを作成する**

実装時点の実日付(例: `2026-07-27`)を`YYYY-MM-DD`部分に使い、`</urlset>`の直前に以下を挿入する(既存6件はそのまま変更しない):

```xml
          <url>
            <loc>https://training-memo.com/guide/free-browser-training-log.html</loc>
            <lastmod>YYYY-MM-DD</lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.6</priority>
          </url>

          <url>
            <loc>https://training-memo.com/guide/no-install-training-log.html</loc>
            <lastmod>YYYY-MM-DD</lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.6</priority>
          </url>

          <url>
            <loc>https://training-memo.com/guide/web-training-memo.html</loc>
            <lastmod>YYYY-MM-DD</lastmod>
            <changefreq>monthly</changefreq>
            <priority>0.6</priority>
          </url>
        </urlset>
```

- [ ] **Step 3: 更新したsitemap.xmlをS3にアップロードする**

Run(`AWS_PROFILE=trainingmemo-mfa`のMFAセッションが有効な状態で実行):
```bash
aws s3 cp /tmp/sitemap.xml s3://training-memo/sitemap.xml --content-type "application/xml"
```
Expected: `upload: /tmp/sitemap.xml to s3://training-memo/sitemap.xml`

- [ ] **Step 4: `/tr-sitemap`経由で反映されていることを確認する**

Run: `curl -s https://training-memo.com/tr-sitemap | grep -c "<loc>"`
Expected: `9`(既存6件+新規3件)

---

### Task 7: 本番デプロイ後の最終確認

**Files:**
- なし(検証のみ、本番反映後に実施)

- [ ] **Step 1: mainにマージ後、Deploy Frontendワークフローの完了を待つ**

Run: `gh run list --branch main --workflow "Deploy Frontend" --limit 1`
Expected: 直近の実行が `completed success` になっている

- [ ] **Step 2: 本番で3ページが200 OKで、個別のtitleが返ることを確認する**

Run:
```bash
for f in free-browser-training-log no-install-training-log web-training-memo; do
  echo "=== $f ==="
  curl -s -o /dev/null -w "HTTP:%{http_code}\n" "https://training-memo.com/guide/$f.html"
  curl -s "https://training-memo.com/guide/$f.html" | grep "<title>"
done
```
Expected: 3ページとも`HTTP:200`、それぞれ固有の`<title>`が返る

- [ ] **Step 3: トップページからガイドページへのリンクが実際に表示されることをブラウザで確認する**

`chrome-screen-check`スキル相当の手動確認: `https://training-memo.com/` を開き、カレンダー下部に3つのリンクとCTAが表示されていることを目視確認する。

---

## 最終コミット

全タスク完了後、以下を実行する:

```bash
git add -A
git commit -m "feat: add long-tail SEO guide pages and update sitemap

3本の静的HTMLガイドページ(ブラウザ無料記録・インストール不要・Web版の使い方)を追加し、
トップページから内部リンクを張った。サイトマップ(S3)にも3件追記。"
```

(Task 6のsitemap.xml更新はリポジトリ管理外のため、この最終コミットには含まれない)

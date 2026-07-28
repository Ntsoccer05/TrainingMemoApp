---
name: chrome-screen-check
description: Use when you need to visually verify the Vue frontend (http://localhost:5173) after implementing or changing a feature - drives real Chrome via the claude-in-chrome skill (mcp__claude-in-chrome__* tools), logs in with the seeded test account, and checks screenshots/console/network for regressions before claiming a UI change works
---

# Chromeでの画面確認フロー

## 概要

コードの変更を「動くはず」で終わらせず、実際にブラウザで動かして確認する。`claude-in-chrome` スキル(`mcp__claude-in-chrome__*` ツール群)を使い、実ブラウザでログイン→対象画面への遷移→スクリーンショット・コンソール・ネットワークの確認までを行う。MCPサーバー(`chrome-devtools-mcp`等)は使わない。

**核心原則:** UI変更を完了と主張する前に、必ず実ブラウザで見る。型チェックやビルド成功はUIが正しく動く証拠にならない。

## 使うタイミング

- フロントエンド(`src/resources/js` 配下)のコンポーネント・画面を追加/修正した後
- APIレスポンスの形が変わり、画面表示への影響を確認したいとき
- 「画面で確認して」「動作確認して」とユーザーに言われたとき
- バグ修正後、実際に直ったか目視確認したいとき

## 前提条件

1. Dockerコンテナが起動していること(Laravel API側)
   ```bash
   docker-compose ps
   # 動いていなければ
   docker-compose up -d
   ```
2. Vite dev serverが `http://localhost:5173` で起動していること
   ```bash
   cd src && npm run dev
   ```
   すでに起動しているかは `Invoke-WebRequest http://localhost:5173/` 等で200が返るか確認できる。200が返らない場合、他プロセスとのポート競合で別ポートにフォールバックしている可能性があるので、起動ログに出るURLを確認すること。

## テスト用ログインアカウント

`database/seeders/UserSeeder.php` で作成される、画面確認用のアカウント。

| 項目 | 値 |
|---|---|
| メール | `test@gmail.com` |
| パスワード | `password` |

データが壊れた/リセットしたい場合は再実行する:
```bash
docker exec trainingmemo-app-1 php artisan db:seed --class=UserSeeder
```

## 手順

### 1. claude-in-chromeスキルを呼び出し、必要なツールを読み込む

まず `Skill` ツールで `claude-in-chrome` を呼び出す(このスキル自体が `mcp__claude-in-chrome__*` の使い方・注意点を教えてくれる)。ツールが未読み込み(deferred)の場合は、必要な分をまとめて1回の `ToolSearch` で読み込む。1つずつ読み込まない。

```
ToolSearch(query: "select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__read_page,mcp__claude-in-chrome__tabs_create_mcp,mcp__claude-in-chrome__read_console_messages,mcp__claude-in-chrome__read_network_requests")
```

最初に `mcp__claude-in-chrome__tabs_context_mcp` を呼び、現在のタブ状況を把握する。既存タブを使うようユーザーに明示的に指示されていない限り、新規タブ(`tabs_create_mcp`、または `navigate` を tabId なしで呼ぶと自動生成される)を使う。

### 2. トップページへ遷移してログインする

1. `mcp__claude-in-chrome__navigate` で `http://localhost:5173/` へ遷移
2. `mcp__claude-in-chrome__read_page`(filter: `interactive`)でログインフォーム(email/password入力欄、ログインボタン)の要素参照(ref)を特定
3. `mcp__claude-in-chrome__computer`(action: `left_click` → `type`)でemail欄に `test@gmail.com`、password欄に `password` を入力
4. ログインボタンをrefで `left_click`
5. 対象画面へ遷移するまで待機(`computer` action: `wait`)
6. `computer`(action: `screenshot`)または `read_page` でログイン成功を確認(ログインフォームが消えている、ユーザー名等が表示されている等)

ログインに失敗した場合は、まずテストアカウントの状態(`email_verified_at`・パスワード)をDB側で確認してから原因調査する。フォーム操作の問題とアカウント状態の問題を切り分けること。

### 3. 確認したい画面へ遷移し、証拠を集める

- 対象画面(記録一覧、カレンダー、メニュー管理など)へ `navigate` またはUI操作(`computer`)で遷移
- `computer`(action: `screenshot`、細部は `zoom`)でレイアウト崩れや意図した表示になっているか確認
- `mcp__claude-in-chrome__read_console_messages`(pattern指定必須)でJSエラー・Vueの警告が出ていないか確認
- `mcp__claude-in-chrome__read_network_requests` でAPI呼び出しが失敗(4xx/5xx)や `pending` のまま止まっていないか確認

### 4. 結果を報告する

- 何を確認し、何が見えたか(スクリーンショットの内容、コンソール/ネットワークの状態)を簡潔に報告する
- 問題を見つけた場合は、そのまま「直りました」と言わず、`systematic-debugging` スキルに従って原因を特定してから修正する
- 修正後は同じ画面を再度確認し、直ったことをスクリーンショット等の新しい証拠で確認する(`verification-before-completion` の原則: 新しい証拠なしに完了を主張しない)

## よくある失敗

**❌ ビルドが通ったから完了と報告する** — ビルド成功はUIの見た目や挙動を保証しない
**✅ 実際にブラウザで開いて確認する**

**❌ スクリーンショットを撮って中身を見ずに「確認しました」と報告する**
**✅ スクリーンショットの内容を実際に読み、意図通りか判断してから報告する**

**❌ コンソールエラーを見ずにスクリーンショットだけで判断する**
**✅ 見た目が正常でも、コンソール・ネットワークのエラーは別途確認する**

**❌ Vite/Dockerが起動しているか確認せずナビゲートしていきなり失敗する**
**✅ 前提条件(サーバー起動状況)を先に確認する**

**❌ ページ操作が失敗・タイムアウトしても同じ手順を繰り返し試行し続ける**
**✅ 2〜3回失敗したら状況を説明してユーザーに確認する(`claude-in-chrome` スキルの「rabbit holes」注意点に従う)**

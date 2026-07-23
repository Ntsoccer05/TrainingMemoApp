---
description: "FE(Vite dev server)とBE(Docker)をまとめて起動する"
allowed-tools:
  - PowerShell
---

# 起動

BE(Docker: app/web/db/phpmyadmin/mail)とFE(Vite dev server)をまとめて起動する。

## 手順

### 1. BE起動

リポジトリルートで以下を実行する:
```powershell
docker-compose up -d
```
`docker-compose ps` で各コンテナが起動していることを確認する。

### 2. FE起動

**注意**: `docker-compose.yml` の `app` コンテナが既にポート5173をホストに公開する設計になっており(Dockerfileに `n stable` でNode.jsがインストール済み、`vite.config.ts` も `server.host: true` 設定済み)、Viteは**appコンテナ内で起動する**構成になっている。ホスト側(Windows)で `npm run dev` を実行しても、UNCパス(`\\wsl.localhost\...`)の場合は `cmd.exe` がカレントディレクトリを認識できず失敗し、そもそもポート5173はDockerの転送プロセスが既に握っているため正しく動かない。

まずViteが応答するか確認する:
```powershell
try { (Invoke-WebRequest -Uri "http://localhost:5173/" -UseBasicParsing -TimeoutSec 5).StatusCode } catch { "not responding" }
```
既に応答していればスキップする。応答していなければappコンテナ内でバックグラウンド起動する:
```powershell
docker exec -d trainingmemo-app-1 npm run dev
```
数秒待ってから再度上記コマンドで応答を確認し、起動できたことを確認する。

### 3. ユーザーへの報告

以下をまとめて伝える:
- FE: `http://localhost:5173`
- BE(アプリ): `.env` の `WEB_PORT` で指定したポート(Nginx経由)
- phpMyAdmin: `.env` の `PMA_PORT`
- Mailhog: `http://localhost:8025`
- 停止する場合は `/stop` を使うこと

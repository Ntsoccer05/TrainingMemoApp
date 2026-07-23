---
description: "FE(Vite dev server)とBE(Docker)をまとめて停止する"
allowed-tools:
  - PowerShell
---

# 停止

FE(Vite dev server)とBE(Docker: app/web/db/phpmyadmin/mail)をまとめて停止する。

## 手順

### 1. BE・FE停止

**重要な注意**: ポート5173をLISTENしているのは個別のViteプロセスではなく、Dockerのポート転送プロセス(`com.docker.backend` / `wslrelay.exe` 等)である。Viteは `app` コンテナ内で起動している([`/start`](start.md)参照)ため、`Get-NetTCPConnection -LocalPort 5173` で見つかったPIDを `taskkill` するとDocker Desktop本体を巻き添えで落とす危険がある。**ポート番号からPIDを特定してのtaskkillは絶対に行わないこと。**

BE(app内のViteを含む)をまとめて停止するには、リポジトリルートで以下を実行するだけでよい:
```powershell
docker-compose down
```
`docker-compose ps` でコンテナが残っていないことを確認する。

### 2. ユーザーへの報告

BE・FEが停止したこと(コンテナが残っていないこと)を伝える。

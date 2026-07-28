---
description: "FE(Vite dev server)とBE(Docker)をまとめて停止する"
allowed-tools:
  - PowerShell
---

# 停止

FE(ホストで起動したVite dev server)とBE(Docker: app/web/db/phpmyadmin/mail)をまとめて停止する。

## 手順

### 1. FE停止

Vite dev serverは([`/start`](start.md)参照)ホスト側で直接起動された`node`プロセスである。ポート5173・5174でLISTENしているプロセスを確認し、それが`node`プロセスであることを確かめた上で終了する:
```powershell
$connections = Get-NetTCPConnection -LocalPort 5173,5174 -ErrorAction SilentlyContinue
foreach ($conn in $connections) {
    $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
    if ($proc) {
        "PID $($proc.Id): $($proc.ProcessName)"
    }
}
```
**重要な注意**: 必ずプロセス名が`node`であることを確認してから`Stop-Process`すること。Docker Desktop関連のプロセス(`com.docker.backend`、`wslrelay`等)がこれらのポートに関与している可能性がゼロではないため、**プロセス名を確認せずポート番号だけでtaskkill/Stop-Processしないこと**。`node`であることを確認できたPIDのみ以下で終了する:
```powershell
Stop-Process -Id <確認したPID> -Force
```

### 2. BE停止

リポジトリルートで以下を実行する:
```powershell
docker-compose down
```
`docker-compose ps` でコンテナが残っていないことを確認する。

### 3. ユーザーへの報告

BE(コンテナが残っていないこと)・FE(Viteプロセスが終了したこと)が停止したことを伝える。

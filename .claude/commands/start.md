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
`docker-compose ps` で各コンテナ(`db`, `mailhog`, `trainingmemoapp-app-1`, `trainingmemoapp-phpmyadmin-1`, `trainingmemoapp-web-1`)が起動していることを確認する。

### 2. FE起動

**重要**: Viteは`docker exec`でコンテナ内起動せず、**ホスト側で直接起動する**こと。コンテナ内(`app`コンテナ)で`npm run dev`を実行すると、Dockerのファイルシステムオーバーレイとファイル監視のポーリングによりCPU/IO競合が発生し、Vite自体だけでなくphp-fpm側のリクエスト応答まで極端に遅くなる(数秒〜数十秒)既知の問題がある。過去にこの方式で構築されていたが、動作検証の結果ホスト起動に切り替えている。

まずポート5173が既に応答しているか確認する:
```powershell
try { (Invoke-WebRequest -Uri "http://localhost:5173/" -UseBasicParsing -TimeoutSec 5).StatusCode } catch { "not responding" }
```
応答していればスキップする。応答していなければ、ホストで`npm run dev`をバックグラウンド起動する(`src`ディレクトリで実行、ポート5173がDockerのポート公開設定等で既に使用されていれば、Viteは自動的に5174などにフォールバックする):
```powershell
$proc = Start-Process -FilePath "npm.cmd" -ArgumentList "run","dev" -WorkingDirectory "src" -WindowStyle Hidden -PassThru
$proc.Id
```
表示されたPIDを控えておく(`/stop`実行時に使う場合がある)。数秒待ってから、実際に使われたポートを確認する:
```powershell
try { (Invoke-WebRequest -Uri "http://localhost:5173/" -UseBasicParsing -TimeoutSec 5).StatusCode } catch { "not responding" }
try { (Invoke-WebRequest -Uri "http://localhost:5174/" -UseBasicParsing -TimeoutSec 5).StatusCode } catch { "not responding" }
```
200が返ってきたポートを、このセッションでの「FEのURL」として扱う。

**前提となる`.env`設定**: `src/.env`に以下が設定されていることを確認する(なければ追加し、Vite起動後に`docker exec trainingmemoapp-app-1 php artisan config:clear`を実行する)。
```
VITE_API_BASE_URL=http://localhost:1001
SANCTUM_STATEFUL_DOMAINS=localhost:1001,localhost:5174
```
- `VITE_API_BASE_URL`: Vite dev serverのポートとAPI(Nginx経由、`.env`の`WEB_PORT`)のポートが異なるため、`bootstrap.ts`でaxiosの`baseURL`をこの値で上書きしている(未設定時は本番同様に相対パスのままで無害)。
- `SANCTUM_STATEFUL_DOMAINS`: 実際にVite dev serverが使っているポートを含めること。上記確認で5173ではなく5174(またはそれ以外)が応答した場合は、この値にそのポートを追記して`php artisan config:clear`を実行する。

### 3. ユーザーへの報告

以下をまとめて伝える:
- FE: 実際に応答したポート(`http://localhost:5173` または `http://localhost:5174` など)
- BE(アプリ): `.env` の `WEB_PORT` で指定したポート(Nginx経由)
- phpMyAdmin: `.env` の `PMA_PORT`
- Mailhog: `http://localhost:8025`
- 停止する場合は `/stop` を使うこと

# Training Memo サーバーレス移行設計書

## 0. 目的・スコープ

- 現在 EC2 上で稼働中の Training Memo (Laravel 9 API + Filament 管理画面 + Vue3 SPA) を、**EC2 は残したまま**同一ドメインでサーバーレス構成へ移行し、サーバー費用(EC2 常時起動 + ALB)を削減する。
- **対象**: Laravel API(Filament 管理画面含む) + Vue3 静的フロントエンドの両方。
- **対象外(現状のまま維持)**: RDS for MySQL、Route53、ACM。これらはコスト削減効果が見込めない限り変更しない。
- IaC(Terraform)と GitHub Actions の両方からデプロイできること。
- 切替はドメインを変えずに行い、問題があれば即座に EC2 側へフォールバックできる設計とする。

### 前提(AWSコンソール確認済み・確定情報)

| 項目 | 現状 |
|---|---|
| アカウント | trainingMemo (533267300159) / ap-northeast-1 |
| VPC | `trainingMemo-vpc` (`vpc-04b784aabe610a416`, CIDR `10.0.0.0/16`) |
| サブネット | public-1a(`10.0.11.0/24`) / public-1c(`10.0.12.0/24`) / private-1a(`10.0.21.0/24`) / private-1c(`10.0.22.0/24`) |
| NAT Gateway | **なし**。private-rt(`rtb-028f20f3691f5932a`)は`local`ルートのみで外部通信不可 → NAT Instance新設で対応(後述5.3) |
| EC2 | `trainingMemo-ec2` (`i-067e395afed2772ca`, t2.micro, ap-northeast-1a, EIP `54.65.130.29`)。**現在は使わない時に手動停止する運用**(RDSと同様) |
| RDS | `training-memo` (db.t3.micro, MySQL Community, ap-northeast-1c)。パブリックアクセス無効・IAM認証無効。SG `db-sg`(`sg-022418ac5cba00d1b`)はEC2のSGからのInboundのみ許可。**EC2と同じく使わない時は手動停止**。維持する(コスト削減効果が無い限り変更しない) |
| ALB | `trainingMemo-lb`(Internet-facing)。**EC2/RDSが停止中でも常時アクティブで課金継続中** → 早期の停止・削除を推奨 |
| ドメイン | `training-memo.com`(Route53パブリックホストゾーン `Z05596822Q5INOY9TDTX0`、Aレコード(Alias)がALBを指す)。維持する |
| ACM証明書(既存) | `arn:aws:acm:ap-northeast-1:533267300159:certificate/555de339-4fdb-482c-a37f-70645e4a29f3`(`training-memo.com`のみ、ワイルドカード/追加SANなし、ALBに関連付け済み)。維持する |
| PHP/Laravel | PHP ^8.0.2 / Laravel 9 / Filament 2.0 / Sanctum 3.0 / Socialite 5.6 |
| セッション | `SESSION_DRIVER=file`(要変更) |
| ファイルストレージ | `FILESYSTEM_DISK=local`(アップロード機能があれば要変更) |
| キュー | `QUEUE_CONNECTION=sync` |

> **NAT方針の決定**: Socialite(OAuth)はLambdaがVPC内でRDSに接続しつつ外部通信も行う必要があるため、NATが必須。NAT Gateway(月$32〜)ではなくコスト最小の **NAT Instance(t4g.nano クラスの格安AMI、月$4〜6程度)** を新設する。Aurora Serverless v2 + Data API化(NAT完全不要)も検討したが、DB移行の実装リスクに対してコスト削減効果が小さい(月$3〜5程度の差)ため今回は採用しない。RDS for MySQLは変更しない。

---

## 1. 全体アーキテクチャ

```
                            Route53 (既存/変更なし)
                                   │
                       ACM証明書(ap-northeast-1: ALB用/既存)
                       ACM証明書(us-east-1: CloudFront用/新規・無料)
                                   │
                        ┌──────────┴──────────┐
                        │     CloudFront        │  ← 同一ドメインの入口を集約
                        │  (Distribution 1台)   │
                        └──────────┬──────────┘
                behavior: /api/*   │   behavior: /* (default)
                        ┌──────────┴──────────┐
                        ▼                      ▼
              ┌─────────────────┐      ┌──────────────────┐
              │  API Gateway     │      │   S3 (静的サイト)  │
              │  (HTTP API)      │      │  Vue3 build成果物  │
              └────────┬────────┘      └──────────────────┘
                        ▼
              ┌─────────────────┐
              │ Lambda (Bref)    │  PHP-FPM ランタイム
              │ Laravel 9 App    │  (API + Filament管理画面)
              └────────┬────────┘
                        │ VPC内 ENI (private-1a/1c)
                        ├───────────────┐
                        ▼               ▼(外部OAuth通信)
              ┌─────────────────┐  ┌───────────────┐
              │  RDS for MySQL   │  │ NAT Instance   │ ← public subnet, t4g.nano
              │  (既存/変更なし)  │  │ (新規・最安構成) │
              └─────────────────┘  └───────┬───────┘
                                            ▼
                                      Internet Gateway(既存)

  [既存 EC2 + ALB] は並行して残置 → Route53 or CloudFrontのオリジン切替で
  段階移行、安定後に停止・削除(ALBは現在EC2停止中も課金継続のため優先的に停止検討)
```

**ポイント**: CloudFront を「同一ドメインの唯一の入口」にすることで、フロント(S3)とAPI(Lambda)を1つのドメイン配下に同居させる。EC2+ALBは別オリジン・別ルーティングとして並行稼働させ、切替はCloudFrontのオリジン/behavior変更 or Route53の重み付けルーティングで行う。

---

## 2. コンポーネント設計

### 2.1 フロントエンド(Vue3) → S3 + CloudFront

- `npm run build` の成果物を S3 バケット(非公開、CloudFront経由のみアクセス可)に配置。
- S3へのアクセスは **CloudFront OAC (Origin Access Control)** を使用し、バケットポリシーでCloudFront以外からの直接アクセスを拒否。
- SPAのルーティング対応: 403/404を `/index.html` にカスタムエラーレスポンスでフォールバック(Vue Router history mode対応)。
- キャッシュ: `index.html` は短TTL(または no-cache)、ハッシュ付きJS/CSSアセットは長期TTL。

### 2.2 バックエンド(Laravel) → Lambda + API Gateway (Bref)

- **[Bref](https://bref.sh/)**(OSS, MITライセンス, 無料)を採用。Laravel Vaporは月額プラットフォーム利用料が発生するためコスト削減目的に反し不採用。
- ランタイム: `bref/bref` の `php-82-fpm` レイヤー(Bref公式Lambdaレイヤー)+ `bref/laravel-bridge`。
  - Laravel 9 は PHP 8.0.2 を要求しているが `composer.json` の制約は `^8.0.2` なので PHP 8.2 でも動作可能。事前に `composer install` でエラーが出ないことを確認する。
- API Gateway は **HTTP API**(REST APIより安価、ペイロード制限に注意しつつ採用)。
- Lambda は RDS のある VPC内 private subnet に配置(ENI経由でRDS接続)。
- ハンドラは `bref/laravel-bridge` の `Bref\LaravelBridge\Http\LaravelFpmHandler` を利用し、Laravelアプリをそのまま実行(コード変更は最小限)。

#### Laravel側で必要な変更

| 項目 | 変更内容 | 理由 |
|---|---|---|
| セッション | `SESSION_DRIVER=file` → `database` | Lambdaのファイルシステムは実行毎に破棄されるため file/local ドライバ不可。RDSに `sessions` テーブルを追加(`php artisan session:table` → migrate)。 |
| ファイルストレージ | **変更不要**(確認済み: アップロード機能なし) | アプリ内にファイルアップロード実装が存在しないため、`FILESYSTEM_DISK`/S3化の対応は不要。将来アップロード機能を追加する場合は `s3` diskを使うこと。 |
| キャッシュ | `CACHE_DRIVER=file` の場合は `database` か `array` に変更 | 同上 |
| ログ | `stderr`(CloudWatch Logsに集約) | Lambda標準出力がCloudWatch Logsに流れる |
| Socialite(OAuth) | 変更不要だが外部通信のため NAT Gateway 経由の疎通が必須 | 後述 5.3 |

### 2.3 Filament管理画面の扱い

- Filament(Livewire)はステートレスなHTTPリクエスト+セッションで動作するため、セッションをdatabaseドライバにすれば Lambda 上でも動作する。
- 管理画面はアクセス頻度が低いため、コールドスタートの影響を受けやすいが、その分「使った時だけ課金」の恩恵が最も大きい領域でもある。
- 初期移行では API と同じ Lambda 関数で管理画面ルートも処理する(ルーティングはLaravel側の `web.php`/`routes/` に既存のまま委譲)。

### 2.4 同一ドメイン配信(CloudFront ルーティング)

- CloudFront の Behavior:
  - `/api/*` → API Gateway オリジン(Lambda)
  - `/admin*`(Filament管理画面パス) → API Gateway オリジン(Lambda)
  - デフォルト(`/*`) → S3 オリジン(Vue3 SPA)
- Cookie/Authorizationヘッダは API Gateway 向け behavior でオリジンにフォワード(Sanctum SPA Cookie認証に必要)。S3向け behavior ではCookie転送不要。

---

## 3. 認証(Sanctum SPA)への影響

- Sanctum の SPA 認証(stateful cookie)は「同一ドメイン(またはサブドメイン)」であることが前提。CloudFrontで同一ドメイン配信するため、この点は維持可能。
- `SANCTUM_STATEFUL_DOMAINS` は変更不要(ドメインが変わらないため)。
- セッションドライバを `database` に変更することで、Lambdaのマルチインスタンス実行でもセッション共有が可能。

---

## 4. コスト比較(目安・東京リージョン/オンデマンド価格・低トラフィック想定)

| 構成 | 内訳 | 月額目安 |
|---|---|---|
| **EC2+RDS+ALBを24時間稼働させた場合(本来の想定コスト)** | EC2 t2.micro ≈$8.5 + RDS db.t3.micro ≈$17.5 + ALB ≈$19 + 諸経費 | **≈$45〜50** |
| **現状の実運用(EC2/RDSは使わない時に手動停止、ALBのみ常時稼働)** | ALB ≈$18〜20 + EC2/RDSのストレージ分のみ ≈$3〜4 | **≈$22〜25**(ほぼALBが無駄打ちしている状態) |
| **移行後(サーバーレス + NAT Instance、RDSは現状と同じ停止運用を継続)** | NAT Instance ≈$4〜6 + Lambda ≈$0〜1 + API Gateway ≈$0〜1 + S3 ≈$0.2 + CloudFront ≈$0.5〜2 + RDSストレージ ≈$2.5〜3 + Route53 $0.5 | **≈$12〜14** |
| **移行後、RDSも常時起動にする場合(手動停止の手間をなくす)** | 上記からRDS分を≈$17.5に置き換え | **≈$27〜28** |

- 「24時間稼働の本来コスト」比で **約$30〜35/月(≈70%)** の削減。
- 「現状の実運用(ALB無駄打ち込み)」比でも **約$8〜13/月** の削減になる。
- NAT Instance採用により、NAT Gateway($32〜/月)を使う場合より **月$27前後安い**。
- あくまで概算。移行後はAWS Budgets/Cost Explorerで実測値を監視すること。

---

## 5. 移行時の技術的論点・リスク

### 5.1 コールドスタート
- Lambda + Bref はコールドスタート(初回起動)で数百ms〜1秒程度のレイテンシが発生し得る。管理画面やAPIのレスポンスタイムSLAが厳しい場合は Provisioned Concurrency の検討余地があるが、これは追加コストになるため今回は「許容する」前提とする。

### 5.2 VPC Lambda + RDS接続
- 既存RDSと同じVPC・サブネットにLambdaをアタッチする(ENI経由)。
- セキュリティグループはRDSの既存SGに「Lambda用SGからの3306inbound」を追加するのみで、RDS自体の変更は不要。

### 5.3 NAT Instance(確定方針)
- Socialite による外部IdP(Google等)へのOAuth通信は、1リクエストの中で「外部HTTPSアクセス」と「RDSへの書き込み」を両方行うため、LambdaはVPC内(RDSに到達可能)かつインターネットにも出られる必要がある。
- VPC確認の結果、既存VPCに **NAT Gatewayは存在せず**、private-rt(`rtb-028f20f3691f5932a`)は`local`ルートのみで外部通信不可なことを確認済み。
- **検討した選択肢と結論**:
  1. NAT Gateway新設 → シンプルだが月$32〜+データ処理料でコスト増が大きい。不採用。
  2. **NAT Instance(t4g.nano等の格安AMI、例: fck-nat)を新設 → 採用**。月$4〜6程度。可用性は単一インスタンスのため自己責任(低トラフィックの個人利用アプリのため許容)。
  3. Aurora Serverless v2 + RDS Data API 化(LambdaをVPC外に置きNATを完全に不要にする) → NATコストはゼロにできるが、RDS MySQL → Aurora MySQL互換への移行(データ移行 + LaravelのDB接続層をData API対応に変更)が必要で実装リスクが高い。NAT Instance採用時との差額(月$3〜5程度)に対して見合わないため不採用。
- **採用構成**: public subnet(`trainingMemo-public-1a` or `1c`)に NAT Instance を1台配置し、private-rt に `0.0.0.0/0 → NAT Instance の ENI` ルートを追加。Lambda用SGは既存 `db-sg`(`sg-022418ac5cba00d1b`)のInboundにLambda用SGからの3306許可を追加。

### 5.4 ファイルアップロード機能 — 確認済み・対応不要
- リポジトリ全体(Controllers/Filament Resources/Vueフロントエンド/マイグレーション)を調査した結果、ファイルアップロード機能は実装されていない。ローカルディスク書き込みへの依存がないため、S3化などの追加対応は不要。

### 5.5 デプロイパッケージサイズ
- Lambda のデプロイパッケージ(zip)上限は 250MB(解凍後)。`vendor/`肥大化に注意し、`composer install --no-dev --optimize-autoloader` で本番不要パッケージを除外する。

---

## 6. IaC設計(Terraform)

既存の RDS / Route53 / ACM(ap-northeast-1側) は **data source として参照するのみ**(新規作成しない)。

```
infra/
  terraform/
    backend.tf              # S3 tfstate + DynamoDBロック
    providers.tf            # AWSプロバイダ(ap-northeast-1 / us-east-1のエイリアス)
    variables.tf
    data.tf                 # 既存VPC/RDS/Route53 zone/ACM証明書をdata参照
    modules/
      frontend/             # S3 + CloudFront OAC
        main.tf
        variables.tf
        outputs.tf
      backend-lambda/        # Lambda(Bref) + API Gateway HTTP API + SG
        main.tf
        variables.tf
        outputs.tf
      routing/                # CloudFront distribution + behaviors + (必要なら)Route53レコード切替
        main.tf
    envs/
      production/
        main.tf              # 上記モジュールを呼び出す
        terraform.tfvars
```

### 主なリソース

**frontend モジュール**
- `aws_s3_bucket`(private) / `aws_s3_bucket_policy`(OAC専用)
- `aws_cloudfront_origin_access_control`

**backend-lambda モジュール**
- `aws_lambda_function`(Bref layer ARNを`layers`に指定、`runtime = "provided.al2"`)
- `aws_lambda_function` のコードは CI から S3 へ zip をアップロードし `s3_bucket` / `s3_key` で参照(Terraform apply では毎回コード再ビルドしない運用)
- `aws_apigatewayv2_api`(HTTP API) / `aws_apigatewayv2_integration` / `aws_apigatewayv2_route` / `aws_apigatewayv2_stage`
- `aws_apigatewayv2_domain_name` + `aws_apigatewayv2_api_mapping`(既存ACM証明書をdata参照)
- `aws_security_group`(Lambda用、RDS SGへのegressのみ許可)
- `aws_lambda_permission`(API Gatewayからの実行許可)
- Lambdaは既存VPCの `private-1a` / `private-1c` サブネットにアタッチ(`subnet-09d69270cce63bcb7` / `subnet-063c99718d795d88a`)

**nat-instance モジュール(新規)**
- `aws_instance`(t4g.nano、fck-nat等の軽量NAT AMI、`trainingMemo-public-1a` に配置、`source_dest_check = false`)
- `aws_eip` + `aws_eip_association`(NAT Instance用の固定IP)
- `aws_security_group`(private subnet CIDR `10.0.20.0/23`相当からのInboundのみ許可)
- `aws_route`(既存 `trainingMemo-private-rt` に `0.0.0.0/0 → NAT InstanceのENI` を追加)
- 既存RDSの `db-sg`(`sg-022418ac5cba00d1b`)にLambda用SGからの3306 Inboundルールを追加

**routing モジュール**
- `aws_cloudfront_distribution`
  - origin 1: S3(OAC)
  - origin 2: API Gateway カスタムドメイン
  - ordered_cache_behavior: `/api/*`, `/admin*` → origin2 (forward cookies/headers)
  - default_cache_behavior → origin1
  - `viewer_certificate`: **us-east-1 の ACM証明書**(CloudFront専用、新規発行が必須。既存ACMがap-northeast-1にある場合は流用不可のためus-east-1に無料で追加発行)
- 切替時のみ `aws_route53_record`(既存zoneをdata参照し、Aレコード/AliasをCloudFrontへ向ける。既存レコードのTarget変更のみで、Route53自体の新規作成はしない)

### tfstate管理
- 既存のtfstate管理基盤なし(確認済み・9章参照)のため新規作成する。
- S3バケット `trainingmemo-terraform-state`(バージョニング有効・暗号化有効・パブリックアクセスブロック)+ DynamoDBテーブル `trainingmemo-terraform-lock`(PAY_PER_REQUEST)を、Terraform管理対象の外で先に1回だけ作成(手動 or `infra/bootstrap/` 配下に別途小さなTerraform構成を分離して`terraform apply`)。
- 追加コストは月$1未満(S3数十円 + DynamoDB PAY_PER_REQUESTの低頻度アクセス分)。

---

## 7. GitHub Actions設計

### 認証
- 長期IAMアクセスキーは使わず、**GitHub OIDC + IAM Role(AssumeRoleWithWebIdentity)** を使用。

```
.github/workflows/
  terraform-plan.yml     # PR作成時: terraform plan をPRコメントに出力
  terraform-apply.yml    # main マージ時: terraform apply(要Environment承認)
  deploy-frontend.yml    # src/resources/js/** 変更時: build → S3 sync → CloudFront invalidation
  deploy-backend.yml     # src/app,src/routes等 変更時: composer install → zip作成 → S3アップロード
                         #   → Lambda関数コード更新 → artisan migrate 実行
```

### deploy-backend.yml の流れ(概要)
1. `composer install --no-dev --optimize-autoloader`
2. Bref向けにvendor同梱でzip作成(`bref/laravel-bridge`のビルド手順に準拠)
3. zipをS3にアップロード
4. `aws lambda update-function-code` でLambda関数コードを更新
5. マイグレーションが必要な場合、Lambda関数を `php artisan migrate --force` 実行用イベントで invoke(Brefの `bref cli` 機構、または専用の migration用Lambda関数を用意)
6. デプロイ後ヘルスチェック(`/api/health`等)

### deploy-frontend.yml の流れ(概要)
1. `npm ci && npm run build`
2. `aws s3 sync dist/ s3://<bucket> --delete`
3. `aws cloudfront create-invalidation --paths "/*"`

### terraform-apply.yml
- GitHub Environments の Required reviewers で本番適用前に人手承認を挟む(誤適用防止)。

---

## 8. 移行手順(段階移行・ロールバック容易性重視)

1. **Phase 0**: Terraform一式・GitHub Actions一式を構築し、Lambda/S3/CloudFrontを本番ドメインとは別のテスト用サブドメイン(例: `serverless-stg.example.com`)で先行検証。
2. **Phase 1(フロント先行)**: CloudFrontを本番ドメインに向ける。behaviorはまず全て EC2/ALB オリジンにフォワードしつつ、静的アセットのみ S3 に向けて動作確認。
3. **Phase 2(API切替)**: `/api/*`, `/admin*` の behavior を API Gateway(Lambda)へ切替。EC2/ALBは引き続き待機させ、異常時はCloudFront設定を即座に旧behaviorへ戻す。
4. **Phase 3(安定化)**: 一定期間(例: 2週間)問題がないことを確認。
5. **Phase 4(EC2撤去)**: EC2/ALBを停止 → スナップショット取得後に削除。NAT Gatewayを新設していた場合は継続利用有無を再評価。

---

## 9. 未確定事項(すべて解消済み)

- [x] ファイルアップロード機能の有無 → **確認済み・機能なし**。`app/`全体・Filamentリソース・Vueフロントエンドいずれにも `Storage::disk`、`FileUpload`、`<input type="file">` 等の実装なし。`users`/`record_contents`テーブルにも画像等のカラムなし。→ **2.2のファイルストレージ変更(`local`→`s3`)は不要**、S3ファイルストレージ用の追加実装・設定は本移行では対応不要と確定。
- [x] tfstate用S3/DynamoDBの新規要否 → **確認済み・新規作成が必要**。リポジトリ内に既存Terraform資材なし(`.tf`ファイル・`infra/`ディレクトリなし)、AWSアカウント内にもS3バケット0件・DynamoDBテーブル0件で既存のtfstate管理基盤は存在しない。本移行で以下を新規作成する。
  - `aws_s3_bucket`(tfstate保存用。例: `trainingmemo-terraform-state`、バージョニング有効・パブリックアクセスブロック有効)
  - `aws_dynamodb_table`(ロック用。例: `trainingmemo-terraform-lock`、PAY_PER_REQUESTでオンデマンド課金・容量プランニング不要。追加コストは月$1未満)
  - これらはTerraform管理外で先に一度だけ手動 or ブートストラップ用スクリプトで作成する(backend自身の状態は循環参照になるため)。
- [x] ~~VPCのサブネット構成・NAT Gateway有無~~ → 確認済み(0章参照)。NAT Gatewayなし、NAT Instanceで対応
- [x] ~~本番ドメイン名・Route53 Hosted Zone ID~~ → `training-memo.com` / `Z05596822Q5INOY9TDTX0`
- [x] ~~既存ACM証明書のARN~~ → `arn:aws:acm:ap-northeast-1:533267300159:certificate/555de339-4fdb-482c-a37f-70645e4a29f3`

## 10. 早期に実施できるコスト削減(移行を待たずに実施可能)

- **ALBの停止/削除**: EC2が停止中の間はALBにヘルシーなターゲットが存在せず、ユーザーには503が返っている状態で無駄打ちになっている。サーバーレス移行の切替タイミングまで使わないなら、今すぐALBを削除(または停止相当の対応)してよい。ただしRoute53のAレコードがALBを指しているため、削除する場合はDNS切れを避けるよう切替計画と合わせて実施する。

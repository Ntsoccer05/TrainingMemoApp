# バックエンドアーキテクチャルール(サービスベース × レイヤードアーキテクチャ)

Laravel側(`src/app/`)の実装で従うアーキテクチャルール。CLAUDE.md から参照される詳細ドキュメント。

## 背景・現状

現状のコントローラーは HTTP 処理・バリデーション・クエリ・業務ロジックが混在している(例: `RecordMenuController::index/create` が直接 Eloquent クエリと分岐ロジックを持つ)。

**新規実装・まとまった変更からは以下の構成に従う。既存コードの一括リファクタリングはしない。** 触る機会があるファイルから段階的に移行する。

## 全体方針: 縦軸(サービス)× 横軸(レイヤー)の複合構成

- **縦軸(サービスベース)**: 機能ドメイン(Record, RecordMenu, RecordContent, Menu, Ranking, Auth, Inquiry 等)ごとにコードをグルーピングする。1ドメイン = 1つの業務ロジックの入り口(Service)を持つ
- **横軸(レイヤード)**: 各ドメインの内部は技術的責務でレイヤーを分ける(Controller → Service → Repository(任意) → Model)

つまり「ドメインごとに分割された、内部はレイヤードアーキテクチャのモジュール」を採用する。

## レイヤー定義

### 1. Controller (`app/Http/Controllers/`)

- **責務**: HTTPリクエストの受け取り、FormRequestへのバリデーション委譲、Serviceの呼び出し、レスポンス整形のみ
- **禁止**: Eloquentクエリの直書き、業務ロジックの記述、`$request` の生プロパティを Service に渡さず必ず FormRequest 経由にする

### 2. FormRequest (`app/Http/Requests/{Domain}/`)

- **責務**: 入力値検証(`rules()`)と認可(`authorize()`)
- 既存コードは未整備な箇所が多いが、新規エンドポイントには必ず作成する

### 3. Service (`app/Services/{Domain}/`)

- **責務**: ユースケース単位の業務ロジック。トランザクション制御、複数モデルにまたがる処理の調整
- **命名**: `{Domain}Service`(例: `RecordMenuService`)
- **メソッド**: 1メソッド = 1ユースケース。動詞+名詞で意図が明確な名前にする
  - ❌ `index()` `create()`(CRUD動詞そのまま)
  - ✅ `getSecondLatestRecordState()` `startRecordingSet()`

### 4. Repository(任意, `app/Repositories/{Domain}/`)

- **責務**: 複雑なクエリのカプセル化
- **目安**: 単純なCRUDしかない間はRepositoryを省略し、ServiceがEloquentモデルを直接使ってよい。複雑なwhere条件・集計・N+1対策が必要になった時点で切り出す
- 切り出す場合の命名: `{Domain}Repository`

### 5. Model (`app/Models/`)

- **責務**: リレーション定義、アクセサ/ミューテタ、スコープ
- 業務ロジック(条件分岐・計算・複数モデルの調整)は持たない

## ディレクトリ構造(例: RecordMenuドメイン)

```
app/
  Http/
    Controllers/
      RecordMenuController.php       # Serviceを呼ぶだけの薄いコントローラー
    Requests/
      RecordMenu/
        StoreRecordMenuRequest.php
  Services/
    RecordMenu/
      RecordMenuService.php
  Repositories/                      # 必要になったドメインのみ作成
    RecordMenu/
      RecordMenuRepository.php
  Models/
    RecordMenu.php
```

## 依存関係のルール(重要)

- 依存方向は **上位 → 下位の一方向のみ**: Controller → Service → Repository → Model
- 下位レイヤーは上位レイヤーの存在を知らない(ServiceがControllerを参照する、ModelがServiceを参照する、は禁止)
- Serviceから別ドメインのServiceを呼び出すのは許可するが、循環依存は禁止
- Controllerからモデルを直接操作するのは禁止(既存コードに実例があっても新規コードでは踏襲しない)

## 既存コードとの付き合い方(移行方針)

- 一括移行はしない。差分は最小限に留める
- 既存コントローラーのメソッドを修正・拡張する機会があれば、その範囲だけ対応するServiceに切り出す
- 新規エンドポイント・新規ドメインは必ずこの構成で作成する

## Before/After 例

**Before**(`RecordMenuController.php` 現状: コントローラーに業務ロジックとクエリが直書き)

```php
public function index(Request $request, RecordMenu $recordMenu){
    $user_id = $request->user_id;
    // ...クエリ条件の組み立てがそのままコントローラーに書かれている
    $secondRecordState = $recordMenu->where(...)->orderBy(...)->first();
    if($secondRecordState){
        // ...
    }
}
```

**After**(Service へ分離)

```php
// app/Http/Controllers/RecordMenuController.php
public function index(GetSecondLatestRecordStateRequest $request, RecordMenuService $service)
{
    $result = $service->getSecondLatestRecordState($request->toDto());
    return response()->json($result);
}

// app/Services/RecordMenu/RecordMenuService.php
class RecordMenuService
{
    public function getSecondLatestRecordState(GetSecondLatestRecordStateDto $dto): array
    {
        // クエリ組み立て・分岐ロジックはここに集約
    }
}
```

## テスト方針

- Serviceはコントローラーから独立してユニットテストできることを分離の主目的の一つとする
- 新規Serviceを追加する際は `test-driven-development` スキルに従い、Service層のテストを先に書く

## 命名規約まとめ

| 種別 | 命名パターン | 例 |
|---|---|---|
| Service | `{Domain}Service` | `RecordMenuService` |
| Repository | `{Domain}Repository` | `RecordMenuRepository` |
| FormRequest | `{動詞}{Domain}Request` | `StoreRecordMenuRequest` |
| Serviceメソッド | 動詞+名詞のユースケース名 | `startRecordingSet()` |

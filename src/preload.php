<?php

/**
 * OPcache preload script (opcache.preload で指定)
 *
 * Windows上のDocker Desktopではバインドマウント越しのファイルI/Oが遅く、
 * Laravelが1リクエストで読み込む800ファイル超(大半はvendor配下)を毎回
 * stat()し直すと数秒〜数十秒のレイテンシになる。
 *
 * vendor配下は composer install/update 時以外変わらないため、ここで
 * バイトコードをOPcacheの immutable な領域に載せておき、以降は
 * validate_timestamps の対象外にする(= 毎リクエストのstat()を回避)。
 * opcache_compile_file() はクラスを宣言(require)せずコンパイルのみ行うため、
 * 依存関係の宣言順序によるエラーを避けられる。
 *
 * app/ 配下のコードはここでは対象にしないため、通常通り毎リクエスト
 * 検証され、編集内容は即座に反映される。
 *
 * vendorを更新した場合(composer install/update)は preload-manifest.txt の
 * 再生成とphp-fpmの再起動(docker-compose restart app)が必要。
 *
 * --- なぜ vendor 全体をスキャンしないのか ---
 * vendor全体を対象にすると、
 * - filament/forms/.stubs.php のようなIDEヘルパー用スタブ(正しいPHP構文でない)
 * - nikic/php-parser の巨大な生成ファイル(コンパイルにmemory_limitを使い切る)
 * などでコンパイル時 Fatal error が発生し、preload中のエラーは
 * try/catch で捕捉できずphp-fpm自体が起動に失敗する。
 * そのため、実際のAPIリクエスト(GET /api/menus, /api/health)を
 * get_included_files() で実測し、本当に読み込まれているファイルだけを
 * preload-manifest.txt に列挙して対象にしている。
 * 再生成方法は docs/development-guidelines.md 等を参照(なければ下記手順):
 *
 *   docker exec <appコンテナ> php -r '
 *     require "/var/www/html/vendor/autoload.php";
 *     $app = require "/var/www/html/bootstrap/app.php";
 *     try {
 *       $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
 *       $response = $kernel->handle(Illuminate\Http\Request::create("/api/menus", "GET"));
 *     } catch (\Throwable $e) {}
 *     foreach (get_included_files() as $f) {
 *       if (str_contains($f, "/vendor/")) echo str_replace("/var/www/html/", "", $f).PHP_EOL;
 *     }
 *   ' | sort -u > src/preload-manifest.txt
 *
 * (複数のエンドポイントを叩いて出力をマージすると網羅性が上がる)
 */

$manifestPath = __DIR__ . '/preload-manifest.txt';

if (!is_file($manifestPath)) {
    return;
}

$paths = file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($paths as $relativePath) {
    $relativePath = trim($relativePath);
    if ($relativePath === '' || str_starts_with($relativePath, '#')) {
        continue;
    }

    $fullPath = __DIR__ . '/' . $relativePath;

    if (!is_file($fullPath)) {
        continue;
    }

    try {
        opcache_compile_file($fullPath);
    } catch (\Throwable $e) {
        // マニフェスト生成後にファイルが変わった等でコンパイルできない場合はスキップする
        continue;
    }
}

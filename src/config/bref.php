<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servable Assets
    |--------------------------------------------------------------------------
    |
    | Bref(FPM runtime)は、ここに列挙したパスに完全一致するリクエストのみを
    | 静的ファイルとしてLambda自身から配信する(ワイルドカード非対応、
    | Illuminate\Http\Middleware\ServeStaticAssets::in_array()による完全一致判定)。
    |
    | CloudFrontはFilament v3のアセットパス(/js/filament/*, /css/filament/*)を
    | API Gateway(このLambda)へルーティングしているが、Laravel側にこれらのパスへの
    | ルートが存在しないと404になり、CloudFrontのcustom_error_responseでSPAの
    | index.htmlへフォールバックしてしまう(Filament管理画面のCSS/JSが読み込めず
    | 崩れる障害の原因になっていた)。
    |
    | Filamentのバージョンアップでアセットファイルが増減しても追随できるよう、
    | ハードコードせず`php artisan filament:assets`が生成した実ファイルを
    | 実行時にスキャンして列挙する。
    |
    */

    'assets' => (function () {
        $paths = [];

        foreach (['js/filament', 'css/filament'] as $prefix) {
            $dir = public_path($prefix);

            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                $paths[] = $prefix.'/'.$relative;
            }
        }

        return $paths;
    })(),

    /*
    |--------------------------------------------------------------------------
    | Shared Log Context
    |--------------------------------------------------------------------------
    |
    | In order to make debugging a little easier, the Lambda `X-Request-ID`
    | value can be added to the shared log context automatically.
    |
    */

    'request_context' => false,

    /*
    |--------------------------------------------------------------------------
    | Jobs Logging
    |--------------------------------------------------------------------------
    |
    | Here you can disable detailed logging of every job execution.
    |
    */

    'log_jobs' => true,

];

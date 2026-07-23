<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // docker-compose.ymlのappサービスがDB_DATABASE等をコンテナの実環境変数として
        // 注入しているため、.env.testingやphpunit.xmlの<env>では上書きできない。
        // ここで直接テスト用DBに切り替えることで、テストが開発用DBに接続するのを防ぐ。
        config(['database.connections.mysql.database' => 'trainingmemo_test']);

        return $app;
    }
}

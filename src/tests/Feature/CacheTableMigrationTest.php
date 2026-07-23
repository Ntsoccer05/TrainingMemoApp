<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CacheTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasColumns('cache', ['key', 'value', 'expiration']));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertTrue(Schema::hasColumns('cache_locks', ['key', 'owner', 'expiration']));
    }

    public function test_cache_is_persisted_to_database_when_driver_is_database(): void
    {
        config(['cache.default' => 'database']);

        Cache::store('database')->put('test-key', 'test-value', 60);

        // DatabaseStore は config('cache.prefix') をキーの先頭に付与して保存するため、
        // 環境依存のプレフィックスを問わず末尾一致で書き込みを検証する。
        $this->assertTrue(
            DB::table('cache')->where('key', 'like', '%test-key')->exists()
        );
        $this->assertEquals('test-value', Cache::store('database')->get('test-key'));
    }
}

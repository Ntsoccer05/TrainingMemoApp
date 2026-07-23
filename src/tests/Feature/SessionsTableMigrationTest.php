<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionsTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumns('sessions', [
            'id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity',
        ]));
    }

    public function test_session_is_persisted_to_database_when_driver_is_database(): void
    {
        config(['session.driver' => 'database']);

        $sessionId = 'test-session-' . uniqid();
        $handler = app('session')->driver('database')->getHandler();
        $handler->write($sessionId, serialize(['foo' => 'bar']));

        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
        ]);
    }
}

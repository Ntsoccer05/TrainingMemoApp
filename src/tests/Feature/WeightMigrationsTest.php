<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WeightMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weight_tags_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('weight_tags'));
        $this->assertTrue(Schema::hasColumns('weight_tags', ['id', 'content', 'created_at', 'updated_at']));
    }

    public function test_record_state_weight_tag_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('record_state_weight_tag'));
        $this->assertTrue(Schema::hasColumns('record_state_weight_tag', [
            'id', 'record_state_id', 'weight_tag_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_record_states_table_has_weight_memo_column(): void
    {
        $this->assertTrue(Schema::hasColumn('record_states', 'weight_memo'));
    }

    public function test_users_table_has_target_weight_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'target_weight'));
    }

    public function test_weight_tags_table_has_user_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('weight_tags', 'user_id'));
    }

    public function test_users_table_has_target_weight_date_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'target_weight_date'));
    }
}

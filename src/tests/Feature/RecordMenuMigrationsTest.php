<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordMenuMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_menus_has_composite_search_index(): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM record_menus'))
            ->where('Key_name', 'idx_record_menus_search')
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->all();

        $this->assertSame(['user_id', 'category_id', 'menu_id', 'recorded_at'], $indexes);
    }
}

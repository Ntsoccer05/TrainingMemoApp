<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecordContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 20件のデータを生成します
        for ($i = 1; $i <= 20; $i++) {
            $data = [
                'user_id' => 1,
                'record_state_id' =>rand(1, 10),
                'record_menu_id' => rand(1, 20),
                'category_id' => 1,
                'menu_id' => 1,
                'weight' => rand(70, 80),
                'right_weight' => null,
                'left_weight' => null,
                'set' => rand(1, 5),
                'rep' => rand(5, 9),
                'memo' => 'Sample Memo ' . $i, // メモの例
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('record_contents')->insert($data);
        }
    }
}

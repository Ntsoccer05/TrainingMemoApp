<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $params =[
            [
                'user_id' => 1,
                'category_id' => 1,
                'content' => 'ベンチプレス',
                'oneSide' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'category_id' => 2,
                'content' => 'ベントオーバーロー',
                'oneSide' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'category_id' => 2,
                'content' => 'ダンベルワンハンドロー',
                'oneSide' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'category_id' => 3,
                'content' => 'スクワット',
                'oneSide' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'category_id' => 4,
                'content' => 'ダンベルカール',
                'oneSide' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        foreach ($params as $param) {
            DB::table('menus')->insert($param);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
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
                'content' => '胸',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => '背中',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => '足',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 1,
                'content' => '腕',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach($params as $param){
            DB::table('categories')->insert($param);
        }
    }
}

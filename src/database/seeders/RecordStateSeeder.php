<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecordStateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        // 20件のデータを生成します
        for ($i = 1; $i <= 20; $i++) {
            $data = [
                'id' => $i,
                'user_id' => 1,
                'bodyWeight' => null,
                'recorded_at' => Carbon::now()->subDays(mt_rand(0, 20)),
                'created_at' => Carbon::now()->subDays(mt_rand(0, 20)),
                'updated_at' => null,
            ];

            DB::table('record_states')->insert($data);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ranking_records', function (Blueprint $table) {
            $table->id();
             //小数点あり：float('カラム名', 小数点を含めた桁数, 小数点以下の桁数)
             $table->json('bench_weight')->nullable();
             $table->json('squat_weight')->nullable();
             $table->json('deadlift_weight')->nullable();
             $table->json('bench_volume')->nullable();
             $table->json('squat_volume')->nullable();
             $table->json('deadlift_volume')->nullable();
             $table->date('recorded_at')->nullable();
             $table->json('bench_weight_users')->nullable();
             $table->json('squat_weight_users')->nullable();
             $table->json('deadlift_weight_users')->nullable();
             $table->json('bench_volume_users')->nullable();
             $table->json('squat_volume_users')->nullable();
             $table->json('deadlift_volume_users')->nullable();
             // 2038年問題対策
            // $table->timestamps();
            $table->dateTime('created_at')->default(now());
            $table->dateTime('updated_at')->default(now());
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ranking_records');
    }
};

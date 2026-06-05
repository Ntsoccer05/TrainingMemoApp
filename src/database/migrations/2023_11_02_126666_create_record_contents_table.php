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
        Schema::create('record_contents', function (Blueprint $table) {
            $table->id();
            //外部キーに紐づくテーブルはreferences->onかconstrained
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // cascadeOnDelete()は親のテーブルのレコードが削除された場合に一緒に削除
            // 紐づける際に相手のテーブル名は単数形
            $table->foreignId('record_state_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('record_menu_id')->nullable()->constrained()->cascadeOnDelete();
            //外部キーの制約（null許容）はforeignIdの直後
            $table->foreignId('category_id')->nullable()->references('id')->on('categories')->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->cascadeOnDelete();
            //小数点あり：float('カラム名', 小数点を含めた桁数, 小数点以下の桁数)
            $table->float('weight', 8,1)->nullable();
            $table->float('right_weight',8,1)->nullable();
            $table->integer('right_rep')->nullable();
            $table->float('left_weight',8,1)->nullable();
            $table->integer('left_rep')->nullable();
            $table->float('volume', 8,3)->nullable();
            $table->float('right_volume', 8,3)->nullable();
            $table->float('left_volume', 8,3)->nullable();
            $table->integer('set')->nullable();
            $table->integer('rep')->nullable();
            $table->string('memo')->nullable();
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
        Schema::dropIfExists('record_contents');
    }
};

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
        Schema::create('record_menus', function (Blueprint $table) {
            // 自動で連番とするためbigIncrements()を利用
            $table->bigIncrements('id');
            // $table->id();
            //外部キーに紐づくテーブルはreferences->onかconstrained
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            //外部キーの制約（null許容）はforeignIdの直後
            $table->foreignId('category_id')->nullable()->references('id')->on('categories')->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->cascadeOnDelete();
            // cascadeOnDelete()は親のテーブルのレコードが削除された場合に一緒に削除
            // 紐づける際に相手のテーブル名は単数形
            $table->foreignId('record_state_id')->nullable()->constrained()->cascadeOnDelete();
            // 2038年問題対策
            // $table->timestamps();
            $table->date('recorded_at');
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
        Schema::dropIfExists('record_menus');
    }
};

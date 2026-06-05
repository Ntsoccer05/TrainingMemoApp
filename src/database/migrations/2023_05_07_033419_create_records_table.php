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
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            //外部キーに紐づくテーブルはreferences->onかconstrained
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // cascadeOnDelete()は親のテーブルのレコードが削除された場合に一緒に削除
            //外部キーの制約（null許容）はforeignIdの直後
            $table->foreignId('category_id')->nullable()->references('id')->on('categories')->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('bodyWeight')->nullable();
            $table->integer('weight')->nullable();
            $table->integer('right-weight')->nullable();
            $table->integer('left-weight')->nullable();
            $table->integer('set')->nullable();
            $table->integer('rep')->nullable();
            $table->string('memo')->nullable();
            $table->date('recorded_at');
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
        Schema::dropIfExists('records');
    }
};

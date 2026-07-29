<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RecordMenuController::index、RecordContentController::index/show/create/delete など
     * ほぼ全ての主要APIが record_menus を user_id + category_id + menu_id (+ recorded_at) の
     * 組み合わせで検索している。既存はuser_id/category_id/menu_id/record_state_idそれぞれの
     * 単一カラムFKインデックスしか無く、EXPLAINで確認したところ複合検索ではどれも使われず
     * フルスキャン(type=ALL)になっていたため、この複合インデックスを追加する。
     *
     * @return void
     */
    public function up()
    {
        Schema::table('record_menus', function (Blueprint $table) {
            $table->index(['user_id', 'category_id', 'menu_id', 'recorded_at'], 'idx_record_menus_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('record_menus', function (Blueprint $table) {
            $table->dropIndex('idx_record_menus_search');
        });
    }
};

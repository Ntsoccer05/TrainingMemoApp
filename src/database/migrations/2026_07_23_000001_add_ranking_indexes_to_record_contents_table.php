<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RecordRankingController::index (メニュー別MAX記録)が
     * (user_id, menu_id) ごとにMAX(weight/right_weight/left_weight/volume/right_volume/left_volume)を
     * 求める処理を高速化するための複合インデックス。
     * カラムごとに専用のインデックスを持たせることで、GROUP BY集計がインデックスのみで完結し
     * (loose index scan)、対象ユーザーの全行スキャンや一時テーブルを避けられる。
     *
     * @return void
     */
    public function up()
    {
        Schema::table('record_contents', function (Blueprint $table) {
            $table->index(['user_id', 'menu_id', 'weight'], 'idx_rc_user_menu_weight');
            $table->index(['user_id', 'menu_id', 'right_weight'], 'idx_rc_user_menu_rweight');
            $table->index(['user_id', 'menu_id', 'left_weight'], 'idx_rc_user_menu_lweight');
            $table->index(['user_id', 'menu_id', 'volume'], 'idx_rc_user_menu_volume');
            $table->index(['user_id', 'menu_id', 'right_volume'], 'idx_rc_user_menu_rvolume');
            $table->index(['user_id', 'menu_id', 'left_volume'], 'idx_rc_user_menu_lvolume');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('record_contents', function (Blueprint $table) {
            $table->dropIndex('idx_rc_user_menu_weight');
            $table->dropIndex('idx_rc_user_menu_rweight');
            $table->dropIndex('idx_rc_user_menu_lweight');
            $table->dropIndex('idx_rc_user_menu_volume');
            $table->dropIndex('idx_rc_user_menu_rvolume');
            $table->dropIndex('idx_rc_user_menu_lvolume');
        });
    }
};

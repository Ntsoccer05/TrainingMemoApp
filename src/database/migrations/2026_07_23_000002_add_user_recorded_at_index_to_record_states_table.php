<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RecordContentService::getRecordsInRange() の
     * WHERE user_id = ? AND recorded_at BETWEEN ? AND ? を高速化するための複合インデックス。
     *
     * @return void
     */
    public function up()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->index(['user_id', 'recorded_at'], 'idx_record_states_user_recorded_at');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->dropIndex('idx_record_states_user_recorded_at');
        });
    }
};

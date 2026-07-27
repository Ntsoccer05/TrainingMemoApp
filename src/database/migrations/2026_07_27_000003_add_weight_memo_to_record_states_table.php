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
        Schema::table('record_states', function (Blueprint $table) {
            $table->text('weight_memo')->nullable()->after('bodyWeight');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('record_states', function (Blueprint $table) {
            $table->dropColumn('weight_memo');
        });
    }
};

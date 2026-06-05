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
        Schema::table('record_contents', function (Blueprint $table) {
            //
            $table->integer('right_rep')->length(3)->nullable()->change();
            $table->integer('left_rep')->length(3)->nullable()->change();
            $table->integer('rep')->length(3)->nullable()->change();
            $table->float('right_volume', 11,3)->nullable()->change();
            $table->float('left_volume', 11,3)->nullable()->change();
            $table->float('volume', 11,3)->nullable()->change();
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
            //
        });
    }
};

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
        Schema::create('record_state_weight_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_state_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weight_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['record_state_id', 'weight_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('record_state_weight_tag');
    }
};

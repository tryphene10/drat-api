<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCooperativesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cooperatives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('name_quartier');
            $table->string('name_ville');
            $table->string('longitude');
            $table->string('latitude');
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cooperatives');
    }
}

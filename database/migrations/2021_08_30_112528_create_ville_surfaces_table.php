<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVilleSurfacesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ville_surfaces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('surface_partage_id');
            $table->string('name');
            $table->boolean('published')->default(1);
            $table->foreign('surface_partage_id')->references('id')->on('surface_partages');
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
        Schema::dropIfExists('ville_surfaces');
    }
}

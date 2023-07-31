<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSurfacePartagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surface_partages', function (Blueprint $table) {
            $table->bigIncrements('id');
            //$table->unsignedBigInteger('quartier_surface_id');
            $table->string('name');
            $table->string('longitude');
            $table->string('latitude');
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            //$table->foreign('quartier_surface_id')->references('id')->on('quartier_surfaces');
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
        Schema::dropIfExists('surface_partages');
    }
}

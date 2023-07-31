<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateQuartierSurfacesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quartier_surfaces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ville_surface_id');
            $table->string('name');
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('ville_surface_id')->references('id')->on('ville_surfaces');
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
        Schema::dropIfExists('quartier_surfaces');
    }
}

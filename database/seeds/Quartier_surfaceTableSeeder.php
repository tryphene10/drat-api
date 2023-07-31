<?php

use Illuminate\Database\Seeder;
use App\Quartier_surface;

class Quartier_surfaceTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //Q1-ville Douala
        $objQuartier_surface = new Quartier_surface();
        $objQuartier_surface->name = 'New deido';
        $objQuartier_surface->published   = 1;
        $objQuartier_surface->ville_surface_id   = 1;
        $objQuartier_surface->generateReference();
        $objQuartier_surface->generateAlias($objQuartier_surface->name);
        if(!$objQuartier_surface->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier_surface->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier_surface->name);
        }

        //Q2-Ville Edea
        $objQuartier_surface = new Quartier_surface();
        $objQuartier_surface->name = 'Gare Edéa';
        $objQuartier_surface->published   = 1;
        $objQuartier_surface->ville_surface_id   = 2;
        $objQuartier_surface->generateReference();
        $objQuartier_surface->generateAlias($objQuartier_surface->name);
        if(!$objQuartier_surface->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier_surface->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier_surface->name);
        }


        //Q3-Ville Dibombari
        $objQuartier_surface = new Quartier_surface();
        $objQuartier_surface->name = 'Dibombari gare';
        $objQuartier_surface->published   = 1;
        $objQuartier_surface->ville_surface_id   = 3;
        $objQuartier_surface->generateReference();
        $objQuartier_surface->generateAlias($objQuartier_surface->name);
        if(!$objQuartier_surface->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier_surface->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier_surface->name);
        }


        //Q4-Ville Bonaberi
        $objQuartier_surface = new Quartier_surface();
        $objQuartier_surface->name = 'Grand Angar';
        $objQuartier_surface->published   = 1;
        $objQuartier_surface->ville_surface_id   = 4;
        $objQuartier_surface->generateReference();
        $objQuartier_surface->generateAlias($objQuartier_surface->name);
        if(!$objQuartier_surface->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier_surface->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier_surface->name);
        }


        //Q5-Ville Yokadouma
        $objQuartier_surface = new Quartier_surface();
        $objQuartier_surface->name = 'Bangué';
        $objQuartier_surface->published   = 1;
        $objQuartier_surface->ville_surface_id   = 5;
        $objQuartier_surface->generateReference();
        $objQuartier_surface->generateAlias($objQuartier_surface->name);
        if(!$objQuartier_surface->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier_surface->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier_surface->name);
        }


        //Q6-Ville Lomie
        $objQuartier_surface = new Quartier_surface();
        $objQuartier_surface->name = 'Abakoum';
        $objQuartier_surface->published   = 1;
        $objQuartier_surface->ville_surface_id   = 6;
        $objQuartier_surface->generateReference();
        $objQuartier_surface->generateAlias($objQuartier_surface->name);
        if(!$objQuartier_surface->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier_surface->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier_surface->name);
        }
    }
}

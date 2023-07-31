<?php

use Illuminate\Database\Seeder;
use App\Quartier;

class QuartierTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $objQuartier = new Quartier();
        $objQuartier->name = 'Vallée Bessengue';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }

        //
        $objQuartier = new Quartier();
        $objQuartier->name = 'Ange Raphael';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }

        //
        $objQuartier = new Quartier();
        $objQuartier->name = 'Rue marché new deido';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }

        //
        $objQuartier = new Quartier();
        $objQuartier->name = 'Mabanda';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }

        //
        $objQuartier = new Quartier();
        $objQuartier->name = 'Jardin Logbaba';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }

        //
        $objQuartier = new Quartier();
        $objQuartier->name = 'Akwa Nord';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }

        //
        $objQuartier = new Quartier();
        $objQuartier->name = 'Pk8';
        $objQuartier->published   = 1;
        $objQuartier->ville_id   = 1;
        $objQuartier->generateReference();
        $objQuartier->generateAlias($objQuartier->name);
        if(!$objQuartier->save())
        {
            $this->command->info("Fail Seeded Quartier: ". $objQuartier->name);
        }else{
            $this->command->info("Seeded Quartier: ". $objQuartier->name);
        }


    }
}

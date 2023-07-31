<?php

use Illuminate\Database\Seeder;
use App\Surface_partage;

class Surface_partageTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $objSurface = new Surface_partage();
        $objSurface->name = 'T@S';
        $objSurface->longitude = '4.05730717711363';
        $objSurface->latitude = '9.72034643857094';
        //$objSurface->quartier_surface_id = 1;
        $objSurface->published   = 1;
        $objSurface->generateReference();
        $objSurface->generateAlias($objSurface->name);
        if(!$objSurface->save())
        {
            $this->command->info("Fail Seeded Surface: T@S ");
        }else{
            $this->command->info("Seeded Surface: ". $objSurface->name);
        }

        $objSurface = new Surface_partage();
        $objSurface->name = 'T@S2';
        $objSurface->longitude = '4.065526230595336';
        $objSurface->latitude = '9.73940085178859';
        //$objSurface->quartier_surface_id = 2;
        $objSurface->published   = 1;
        $objSurface->generateReference();
        $objSurface->generateAlias($objSurface->name);
        if(!$objSurface->save())
        {
            $this->command->info("Fail Seeded Surface: T@S2");
        }else{
            $this->command->info("Seeded Surface: ". $objSurface->name);
        }


        $objSurface = new Surface_partage();
        $objSurface->name = 'T@S3';
        $objSurface->longitude = '4.006029931808787';
        $objSurface->latitude = '9.745886906382575';
        //$objSurface->quartier_surface_id = 2;
        $objSurface->published   = 1;
        $objSurface->generateReference();
        $objSurface->generateAlias($objSurface->name);
        if(!$objSurface->save())
        {
            $this->command->info("Fail Seeded Surface: T@S3");
        }else{
            $this->command->info("Seeded Surface: ". $objSurface->name);
        }


        $objSurface = new Surface_partage();
        $objSurface->name = 'T@S4';
        $objSurface->longitude = '4.032910658218663';
        $objSurface->latitude = '9.763001898963477';
        //$objSurface->quartier_surface_id = 2;
        $objSurface->published   = 1;
        $objSurface->generateReference();
        $objSurface->generateAlias($objSurface->name);
        if(!$objSurface->save())
        {
            $this->command->info("Fail Seeded Surface: T@S4");
        }else{
            $this->command->info("Seeded Surface: ". $objSurface->name);
        }


        $objSurface = new Surface_partage();
        $objSurface->name = 'T@S5';
        $objSurface->longitude = '3.971365934726241';
        $objSurface->latitude = '9.784893375010885';
        //$objSurface->quartier_surface_id = 2;
        $objSurface->published   = 1;
        $objSurface->generateReference();
        $objSurface->generateAlias($objSurface->name);
        if(!$objSurface->save())
        {
            $this->command->info("Fail Seeded Surface: T@S5");
        }else{
            $this->command->info("Seeded Surface: ". $objSurface->name);
        }


        $objSurface = new Surface_partage();
        $objSurface->name = 'T@S6';
        $objSurface->longitude = '4.080317513081389';
        $objSurface->latitude = '9.791559648991123';
        //$objSurface->quartier_surface_id = 2;
        $objSurface->published   = 1;
        $objSurface->generateReference();
        $objSurface->generateAlias($objSurface->name);
        if(!$objSurface->save())
        {
            $this->command->info("Fail Seeded Surface: T@S6");
        }else{
            $this->command->info("Seeded Surface: ". $objSurface->name);
        }

    }
}

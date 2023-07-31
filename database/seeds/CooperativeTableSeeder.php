<?php

use Illuminate\Database\Seeder;
use App\Cooperative;

class CooperativeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $objCooperative = new Cooperative();
        $objCooperative->name = 'coop-ndjombe-penja';
        $objCooperative->name_quartier = 'Quartier Haoussa';
        $objCooperative->name_ville = 'Moungo';
        $objCooperative->longitude = '4.286515414769977';
        $objCooperative->latitude = '9.708788665464668';
        $objCooperative->published   = 1;
        $objCooperative->generateReference();
        $objCooperative->generateAlias($objCooperative->name);
        if(!$objCooperative->save())
        {
            $this->command->info("Fail Seeded Cooperative: coop-ndjombe-penja");
        }else{
            $this->command->info("Seeded Cooperative: ". $objCooperative->name);
        }


        $objCooperative = new Cooperative();
        $objCooperative->name = 'coop-kongsamba ';
        $objCooperative->name_quartier = 'Bonangoh';
        $objCooperative->name_ville = 'Moungo';
        $objCooperative->longitude = '4.645224662934435';
        $objCooperative->latitude = '9.684069426695824';
        $objCooperative->published   = 1;
        $objCooperative->generateReference();
        $objCooperative->generateAlias($objCooperative->name);
        if(!$objCooperative->save())
        {
            $this->command->info("Fail Seeded Cooperative: coop-kongsamba");
        }else{
            $this->command->info("Seeded Cooperative: ". $objCooperative->name);
        }


        $objCooperative = new Cooperative();
        $objCooperative->name = 'coop-manjo';
        $objCooperative->name_quartier = 'Manengoteng';
        $objCooperative->name_ville = 'Moungo';
        $objCooperative->longitude = '4.580889077426124';
        $objCooperative->latitude = '9.86122397120588';
        $objCooperative->published   = 1;
        $objCooperative->generateReference();
        $objCooperative->generateAlias($objCooperative->name);
        if(!$objCooperative->save())
        {
            $this->command->info("Fail Seeded Cooperative: coop-manjo");
        }else{
            $this->command->info("Seeded Cooperative: ". $objCooperative->name);
        }


        $objCooperative = new Cooperative();
        $objCooperative->name = 'coop-mbanga';
        $objCooperative->name_quartier = 'Dikouma 2';
        $objCooperative->name_ville = 'Moungo';
        $objCooperative->longitude = '4.980494143253865';
        $objCooperative->latitude = '9.954607762110406';
        $objCooperative->published   = 1;
        $objCooperative->generateReference();
        $objCooperative->generateAlias($objCooperative->name);
        if(!$objCooperative->save())
        {
            $this->command->info("Fail Seeded Cooperative: coop-mbanga");
        }else{
            $this->command->info("Seeded Cooperative: ". $objCooperative->name);
        }

        $objCooperative = new Cooperative();
        $objCooperative->name = 'coop-loum';
        $objCooperative->name_quartier = 'Bonalebe';
        $objCooperative->name_ville = 'Moungo';
        $objCooperative->longitude = '5.025640082236604';
        $objCooperative->latitude = '9.96010092628126';
        $objCooperative->published   = 1;
        $objCooperative->generateReference();
        $objCooperative->generateAlias($objCooperative->name);
        if(!$objCooperative->save())
        {
            $this->command->info("Fail Seeded Cooperative: coop-loum");
        }else{
            $this->command->info("Seeded Cooperative: ". $objCooperative->name);
        }


    }
}

<?php

use Illuminate\Database\Seeder;
use App\Statut_proposition;

class Statut_propositionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
    	$objStatut_proposition = new Statut_proposition();
    	$objStatut_proposition->name = 'Waiting';
    	$objStatut_proposition->published   = 1;
    	$objStatut_proposition->generateReference();
    	$objStatut_proposition->generateAlias($objStatut_proposition->name);
    	if(!$objStatut_proposition->save())
    	{
    		$this->command->info("Fail Seeded Statut_proposition: Waiting");
    	}else{
    		$this->command->info("Seeded Statut_proposition: ". $objStatut_proposition->name);
    	}

        //2
    	$objStatut_proposition = new Statut_proposition();
    	$objStatut_proposition->name = 'Accept';
    	$objStatut_proposition->published   = 1;
    	$objStatut_proposition->generateReference();
    	$objStatut_proposition->generateAlias($objStatut_proposition->name);
    	if(!$objStatut_proposition->save())
    	{
    		$this->command->info("Fail Seeded Statut_proposition: Accept");
    	}else{
    		$this->command->info("Seeded Statut_proposition: ". $objStatut_proposition->name);
    	}

        //3
    	$objStatut_proposition = new Statut_proposition();
    	$objStatut_proposition->name = 'Denied';
    	$objStatut_proposition->published   = 1;
    	$objStatut_proposition->generateReference();
    	$objStatut_proposition->generateAlias($objStatut_proposition->name);
    	if(!$objStatut_proposition->save())
    	{
    		$this->command->info("Fail Seeded Statut_proposition: Denied");
    	}else{
    		$this->command->info("Seeded Statut_proposition: ". $objStatut_proposition->name);
    	}

        //4
    	$objStatut_proposition = new Statut_proposition();
    	$objStatut_proposition->name = 'Cancel';
    	$objStatut_proposition->published   = 1;
    	$objStatut_proposition->generateReference();
    	$objStatut_proposition->generateAlias($objStatut_proposition->name);
    	if(!$objStatut_proposition->save())
    	{
    		$this->command->info("Fail Seeded Statut_proposition: Cancel");
    	}else{
    		$this->command->info("Seeded Statut_proposition: ". $objStatut_proposition->name);
    	}
    }
}

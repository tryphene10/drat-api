<?php

use Illuminate\Database\Seeder;
use App\Statut_cmd;

class Statut_cmdTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
    	$objStatut_commande = new Statut_cmd();
    	$objStatut_commande->name = 'Waiting';
    	$objStatut_commande->published   = 1;
    	$objStatut_commande->generateReference();
    	$objStatut_commande->generateAlias($objStatut_commande->name);
    	if(!$objStatut_commande->save())
    	{
    		$this->command->info("Fail Seeded Statut_commande: Waiting");
    	}else{
    		$this->command->info("Seeded Statut_commande: ". $objStatut_commande->name);
    	}

        //2
    	$objStatut_commande = new Statut_cmd();
    	$objStatut_commande->name = 'Pay';
    	$objStatut_commande->published   = 1;
    	$objStatut_commande->generateReference();
    	$objStatut_commande->generateAlias($objStatut_commande->name);
    	if(!$objStatut_commande->save())
    	{
    		$this->command->info("Fail Seeded Statut_commande: Pay");
    	}else{
    		$this->command->info("Seeded Statut_commande: ". $objStatut_commande->name);
    	}

        //3
    	$objStatut_commande = new Statut_cmd();
    	$objStatut_commande->name = 'Cancel';
    	$objStatut_commande->published   = 1;
    	$objStatut_commande->generateReference();
    	$objStatut_commande->generateAlias($objStatut_commande->name);
    	if(!$objStatut_commande->save())
    	{
    		$this->command->info("Fail Seeded Statut_commande: Cancel");
    	}else{
    		$this->command->info("Seeded Statut_commande: ". $objStatut_commande->name);
    	}

        //4
    	$objStatut_commande = new Statut_cmd();
    	$objStatut_commande->name = 'Failed';
    	$objStatut_commande->published   = 1;
    	$objStatut_commande->generateReference();
    	$objStatut_commande->generateAlias($objStatut_commande->name);
    	if(!$objStatut_commande->save())
    	{
    		$this->command->info("Fail Seeded Statut_commande: Failed");
    	}else{
    		$this->command->info("Seeded Statut_commande: ". $objStatut_commande->name);
    	}
    }
}

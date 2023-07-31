<?php

use Illuminate\Database\Seeder;
use App\Type_livraison;

class Type_livraisonTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
    	$objType_livraison = new Type_livraison();
    	$objType_livraison->name = 'A domicile';
    	$objType_livraison->published   = 1;
    	$objType_livraison->generateReference();
    	$objType_livraison->generateAlias($objType_livraison->name);
    	if(!$objType_livraison->save())
    	{
    		$this->command->info("Fail Seeded unite: A domicil");
    	}else{
    		$this->command->info("Seeded unite: ". $objType_livraison->name);
    	}

        //2
    	$objType_livraison = new Type_livraison();
    	$objType_livraison->name = 'Point distribution drat';
    	$objType_livraison->published   = 1;
    	$objType_livraison->generateReference();
    	$objType_livraison->generateAlias($objType_livraison->name);
    	if(!$objType_livraison->save())
    	{
    		$this->command->info("Fail Seeded unite: Point distribution drat");
    	}else{
    		$this->command->info("Seeded unite: ". $objType_livraison->name);
    	}
    }
}

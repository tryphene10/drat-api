<?php

use Illuminate\Database\Seeder;
use App\Unite;

class UniteTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
    	$objUnite = new Unite();
    	$objUnite->name = 'Sacs';
    	$objUnite->published   = 1;
    	$objUnite->generateReference();
    	$objUnite->generateAlias($objUnite->name);
    	if(!$objUnite->save())
    	{
    		$this->command->info("Fail Seeded unite: Sacs");
    	}else{
    		$this->command->info("Seeded unite: ". $objUnite->name);
    	}

        //2
    	$objUnite = new Unite();
    	$objUnite->name = 'Sceau';
    	$objUnite->published   = 1;
    	$objUnite->generateReference();
    	$objUnite->generateAlias($objUnite->name);
    	if(!$objUnite->save())
    	{
    		$this->command->info("Fail Seeded unite: Sceau");
    	}else{
    		$this->command->info("Seeded unite: ". $objUnite->name);
    	}

        //3
    	$objUnite = new Unite();
    	$objUnite->name = 'Régime';
    	$objUnite->published   = 1;
    	$objUnite->generateReference();
    	$objUnite->generateAlias($objUnite->name);
    	if(!$objUnite->save())
    	{
    		$this->command->info("Fail Seeded unite: Régime");
    	}else{
    		$this->command->info("Seeded unite: ". $objUnite->name);
    	}

        //4
    	$objUnite = new Unite();
    	$objUnite->name = 'Cageots';
    	$objUnite->published   = 1;
    	$objUnite->generateReference();
    	$objUnite->generateAlias($objUnite->name);
    	if(!$objUnite->save())
    	{
    		$this->command->info("Fail Seeded unite: Cageots");
    	}else{
    		$this->command->info("Seeded unite: ". $objUnite->name);
    	}

        //5
    	$objUnite = new Unite();
    	$objUnite->name = 'Filet';
    	$objUnite->published   = 1;
    	$objUnite->generateReference();
    	$objUnite->generateAlias($objUnite->name);
    	if(!$objUnite->save())
    	{
    		$this->command->info("Fail Seeded unite: Filet");
    	}else{
    		$this->command->info("Seeded unite: ". $objUnite->name);
    	}

        //6
    	$objUnite = new Unite();
    	$objUnite->name = 'Bidons';
    	$objUnite->published   = 1;
    	$objUnite->generateReference();
    	$objUnite->generateAlias($objUnite->name);
    	if(!$objUnite->save())
    	{
    		$this->command->info("Fail Seeded unite: Bidons");
    	}else{
    		$this->command->info("Seeded unite: ". $objUnite->name);
    	}

    }
}

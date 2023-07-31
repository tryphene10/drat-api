<?php

use Illuminate\Database\Seeder;
use App\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
        $objRole = new Role();
        $objRole->name = 'Administrateur';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Administrateur');
        $objRole->save();
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Administrateur");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }

        //2
        $objRole = new Role();
        $objRole->name = 'Client';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Client');
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Client");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }

        //3
        $objRole = new Role();
        $objRole->name = 'Gestionnaire_surface';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Gestionnaire_surface');
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Gestionnaire_surface");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }

        //4
        $objRole = new Role();
        $objRole->name = 'Gestionnaire_cooperative';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Gestionnaire_cooperative');
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Gestionnaire_cooperative");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }

        //5
        $objRole = new Role();
        $objRole->name = 'Livreur';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Livreur');
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Livreur");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }

        //6
        $objRole = new Role();
        $objRole->name = 'Coursier';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Coursier');
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Coursier");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }

        //7
        $objRole = new Role();
        $objRole->name = 'Producteur';
        $objRole->published   = 1;
        $objRole->generateReference();
        $objRole->generateAlias('Producteur');
        if(!$objRole->save())
        {
            $this->command->info("Fail Seeded Role: Producteur");
        }else{
            $this->command->info("Seeded Role: ". $objRole->name);
        }


    }
}

<?php

use App\Statut_livraison;
use Illuminate\Database\Seeder;

class Statut_livraisonTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $objlivraisonStatut = new statut_livraison();
        $objlivraisonStatut->name = 'waiting';
        $objlivraisonStatut->published   = 1;
        $objlivraisonStatut->generateReference();
        $objlivraisonStatut->generateAlias('waiting');
        $objlivraisonStatut->save();
        if(!$objlivraisonStatut->save())
        {
            $this->command->info("Fail Seeded statut_livraison: waiting");
        }else{
            $this->command->info("Seeded statut_livraison: ". $objlivraisonStatut->name);
        }

        $objlivraisonStatut = new statut_livraison();
        $objlivraisonStatut->name = 'delivered';
        $objlivraisonStatut->published   = 1;
        $objlivraisonStatut->generateReference();
        $objlivraisonStatut->generateAlias('delivered');
        if(!$objlivraisonStatut->save())
        {
            $this->command->info("Fail Seeded statut_livraison: delivered");
        }else{
            $this->command->info("Seeded statut_livraison: ". $objlivraisonStatut->name);
        }

        $objlivraisonStatut = new statut_livraison();
        $objlivraisonStatut->name = 'not delivered';
        $objlivraisonStatut->published   = 1;
        $objlivraisonStatut->generateReference();
        $objlivraisonStatut->generateAlias('not delivered');
        if(!$objlivraisonStatut->save())
        {
            $this->command->info("Fail Seeded statut_livraison: not delivered");
        }else{
            $this->command->info("Seeded statut_livraison: ". $objlivraisonStatut->name);
        }

    }
}

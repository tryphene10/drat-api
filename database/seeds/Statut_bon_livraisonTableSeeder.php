<?php

use App\Statut_bon_livraison;
use Illuminate\Database\Seeder;

class Statut_bon_livraisonTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $objStatutBonLivraison = new Statut_bon_livraison();
        $objStatutBonLivraison->name = 'Waiting';
        $objStatutBonLivraison->published   = 1;
        $objStatutBonLivraison->generateReference();
        $objStatutBonLivraison->generateAlias('Waiting');
        $objStatutBonLivraison->save();
        if(!$objStatutBonLivraison->save())
        {
            $this->command->info("Fail Seeded statut_bon_livraison: Waiting");
        }else{
            $this->command->info("Seeded statut_bon_livraison: ". $objStatutBonLivraison->name);
        }

        $objStatutBonLivraison = new Statut_bon_livraison();
        $objStatutBonLivraison->name = 'In progress';//encours
        $objStatutBonLivraison->published   = 1;
        $objStatutBonLivraison->generateReference();
        $objStatutBonLivraison->generateAlias('In progress');
        $objStatutBonLivraison->save();
        if(!$objStatutBonLivraison->save())
        {
            $this->command->info("Fail Seeded statut_bon_livraison: In progress");
        }else{
            $this->command->info("Seeded statut_bon_livraison: ". $objStatutBonLivraison->name);
        }

        $objStatutBonLivraison = new Statut_bon_livraison();
        $objStatutBonLivraison->name = 'Delivered';
        $objStatutBonLivraison->published   = 1;
        $objStatutBonLivraison->generateReference();
        $objStatutBonLivraison->generateAlias('Delivered');
        $objStatutBonLivraison->save();
        if(!$objStatutBonLivraison->save())
        {
            $this->command->info("Fail Seeded statut_bon_livraison: Delivered");
        }else{
            $this->command->info("Seeded statut_bon_livraison: ". $objStatutBonLivraison->name);
        }
    }
}

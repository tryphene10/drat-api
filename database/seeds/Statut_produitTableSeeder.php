<?php

use Illuminate\Database\Seeder;
use App\Statut_produit;
class Statut_produitTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $objStatut_produit = new Statut_produit();
        $objStatut_produit->name = 'vente en cours';
        $objStatut_produit->published   = 1;
        $objStatut_produit->generateReference();
        $objStatut_produit->generateAlias($objStatut_produit->name);
        if(!$objStatut_produit->save())
        {
            $this->command->info("Fail Seeded Statut_produit: vente en cours");
        }else{
            $this->command->info("Seeded Statut_produit: ". $objStatut_produit->name);
        }

        $objStatut_produit = new Statut_produit();
        $objStatut_produit->name = 'vendu';
        $objStatut_produit->published   = 1;
        $objStatut_produit->generateReference();
        $objStatut_produit->generateAlias($objStatut_produit->name);
        if(!$objStatut_produit->save())
        {
            $this->command->info("Fail Seeded Statut_produit: vendu");
        }else{
            $this->command->info("Seeded Statut_produit: ". $objStatut_produit->name);
        }

        $objStatut_produit = new Statut_produit();
        $objStatut_produit->name = 'depose';
        $objStatut_produit->published   = 1;
        $objStatut_produit->generateReference();
        $objStatut_produit->generateAlias($objStatut_produit->name);
        if(!$objStatut_produit->save())
        {
            $this->command->info("Fail Seeded Statut_produit: depose");
        }else{
            $this->command->info("Seeded Statut_produit: ". $objStatut_produit->name);
        }
    }
}

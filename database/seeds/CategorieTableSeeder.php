<?php

use Illuminate\Database\Seeder;
use App\Categorie;

class CategorieTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
        $objCategorie = new Categorie();
        $objCategorie->name = 'Fruits';
        $objCategorie->published   = 1;
        $objCategorie->generateReference();
        $objCategorie->generateAlias( $objCategorie->name);
        if(!$objCategorie->save())
        {
            $this->command->info("Fail Seeded Categorie: Fruits");
        }else{
            $this->command->info("Seeded Categorie: ". $objCategorie->name);
        }

        //2
        $objCategorie = new Categorie();
        $objCategorie->name = 'Legumes';
        $objCategorie->published   = 1;
        $objCategorie->generateReference();
        $objCategorie->generateAlias( $objCategorie->name);
        if(!$objCategorie->save())
        {
            $this->command->info("Fail Seeded Categorie: Legumes");
        }else{
            $this->command->info("Seeded Categorie: ". $objCategorie->name);
        }

        //3
        $objCategorie = new Categorie();
        $objCategorie->name = 'Tubercules';
        $objCategorie->published   = 1;
        $objCategorie->generateReference();
        $objCategorie->generateAlias( $objCategorie->name);
        if(!$objCategorie->save())
        {
            $this->command->info("Fail Seeded Categorie: Tubercules");
        }else{
            $this->command->info("Seeded Categorie: ". $objCategorie->name);
        }

        /*//4
        $objCategorie = new Categorie();
        $objCategorie->name = 'Palmeraies';
        $objCategorie->published   = 1;
        $objCategorie->generateReference();
        $objCategorie->generateAlias( $objCategorie->name);
        if(!$objCategorie->save())
        {
            $this->command->info("Fail Seeded Categorie: Palmeraies");
        }else{
            $this->command->info("Seeded Categorie: ". $objCategorie->name);
        }*/
    }
}

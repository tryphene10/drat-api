<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(RoleTableSeeder::class);
        $this->call(Type_livraisonTableSeeder::class);
        $this->call(CooperativeTableSeeder::class);
        $this->call(Surface_partageTableSeeder::class);
        $this->call(Ville_surfaceTableSeeder::class);
        $this->call(Quartier_surfaceTableSeeder::class);
        $this->call(UserTableSeeder::class);
        $this->call(VilleTableSeeder::class);
        $this->call(OptionTableSeeder::class);
        $this->call(Statut_cmdTableSeeder::class);
        $this->call(Statut_propositionTableSeeder::class);
        $this->call(UniteTableSeeder::class);
        $this->call(CategorieTableSeeder::class);
        $this->call(QuartierTableSeeder::class);
        $this->call(Statut_produitTableSeeder::class);
        $this->call(VolumeTableSeeder::class);
        $this->call(ProduitTableSeeder::class);
        $this->call(Statut_livraisonTableSeeder::class);
        $this->call(Statut_bon_livraisonTableSeeder::class);
    }
}

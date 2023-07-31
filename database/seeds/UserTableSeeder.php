<?php

use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
        $objUser = new User();
        $objUser->name = 'Administrateur';
        $objUser->surname = 'momo';
        $objUser->email = 'admin@domain.cm';
        $objUser->phone = '694945673';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 1;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Administrateur');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Administrateur");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //2
        $objUser = new User();
        $objUser->surface_partage_id = 1;
        $objUser->name = 'Gestionnaire surface 1';
        $objUser->surname = 'mama1';
        $objUser->email = 'gestsurface1@domain.cm';
        $objUser->phone = '698295787';//joel
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 3;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire surface1');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire surface1");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //id = 3
        /**************************************************************
         * Cooperative1 : coop-ndjombe-penja
         */
        $objUser = new User();
        $objUser->name = 'Gestionnaire cooperative 1';
        $objUser->surname = 'meme';
        $objUser->email = 'gestcoop1@domain.cm';
        $objUser->phone = '690265947';//patricia
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 1;
        $objUser->role_id = 4;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire cooperative 1');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire cooperative 1");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //4
        /**************************************************************
         * Cooperative2 : coop-kongsamba
         */
        $objUser = new User();
        $objUser->name = 'Gestionnaire cooperative 2';
        $objUser->surname = 'meme';
        $objUser->email = 'gestcoop2@domain.cm';
        $objUser->phone = '697812272';//Rufus
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 2;
        $objUser->role_id = 4;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire cooperative 2');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire cooperative 2");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //5
        /**************************************************************
         * Cooperative3 : coop-manjo
         */
        $objUser = new User();
        $objUser->name = 'Gestionnaire cooperative 3';
        $objUser->surname = 'meme';
        $objUser->email = 'gestcoop3@domain.cm';
        $objUser->phone = '694940672';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 3;
        $objUser->role_id = 4;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire cooperative 3');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire cooperative 3");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //6
        /**************************************************************
         * Cooperative4 : coop-mbanga
         */
        $objUser = new User();
        $objUser->name = 'Gestionnaire cooperative 4';
        $objUser->surname = 'meme';
        $objUser->email = 'gestcoop4@domain.cm';
        $objUser->phone = '694940672';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 4;
        $objUser->role_id = 4;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire cooperative 4');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire cooperative 4");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //7
        /**************************************************************
         * Cooperative5 : coop-loum
         */
        $objUser = new User();
        $objUser->name = 'Gestionnaire cooperative 5';
        $objUser->surname = 'meme';
        $objUser->email = 'gestcoop5@domain.cm';
        $objUser->phone = '694940672';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 5;
        $objUser->role_id = 4;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire cooperative 5');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire cooperative 5");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //8
        $objUser = new User();
        $objUser->name = 'Client1';
        $objUser->surname = 'meme2';
        $objUser->email = 'client1@domain.cm';
        $objUser->phone = '697847117';//arnold
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 2;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Client1');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Client1");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //9
        $objUser = new User();
        $objUser->surface_partage_id = 1;
        $objUser->name = 'Livreur1';
        $objUser->surname = 'meme3';
        $objUser->email = 'livreur1@domain.cm';
        $objUser->phone = '694899843';//monique
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 5;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Livreur1');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Livreur1");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //10
        $objUser = new User();
        $objUser->surface_partage_id = 1;
        $objUser->name = 'Coursier1';
        $objUser->surname = 'meme5';
        $objUser->email = 'coursier1@domain.cm';
        $objUser->phone = '652227748';//Rufus
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 6;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Coursier1');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Coursier1");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //11
        $objUser = new User();
        $objUser->name = 'Producteur1';
        $objUser->surname = 'meme4';
        $objUser->email = 'producteur1@domain.cm';
        $objUser->phone = '697203525';//Fredi
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 1;
        $objUser->role_id = 7;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Producteur1');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Producteur1");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }


        //12
        $objUser = new User();
        $objUser->name = 'Producteur2';
        $objUser->surname = 'meme6';//armand
        $objUser->email = 'producteur2@domain.cm';
        $objUser->phone = '694347232';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 2;
        $objUser->role_id = 7;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Producteur2');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Producteur2");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }


        //13
        $objUser = new User();
        $objUser->name = 'Producteur3';
        $objUser->surname = 'meme7';
        $objUser->email = 'producteur3@domain.cm';
        $objUser->phone = '698205787';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 3;
        $objUser->role_id = 7;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Producteur3');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Producteur3");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }


        //14
        $objUser = new User();
        $objUser->name = 'Producteur4';
        $objUser->surname = 'meme8';
        $objUser->email = 'producteur4@domain.cm';
        $objUser->phone = '698195787';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 4;
        $objUser->role_id = 7;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Producteur4');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Producteur4");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }


        //15
        $objUser = new User();
        $objUser->name = 'Producteur5';
        $objUser->surname = 'meme9';
        $objUser->email = 'producteur5@domain.cm';
        $objUser->phone = '699295787';
        $objUser->password = Hash::make('12345678');
        $objUser->cooperative_id = 5;
        $objUser->role_id = 7;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Producteur5');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Producteur5");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //16
        $objUser = new User();
        $objUser->surface_partage_id = 2;
        $objUser->name = 'Gestionnaire surface 2';
        $objUser->surname = 'mama2';
        $objUser->email = 'gestsurface2@domain.cm';
        $objUser->phone = '694945770';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 3;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire surface2');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire surface2");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //17
        $objUser = new User();
        $objUser->surface_partage_id = 2;
        $objUser->name = 'Livreur2';
        $objUser->surname = 'meme2';
        $objUser->email = 'livreur2@domain.cm';
        $objUser->phone = '694445612';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 5;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Livreur2');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Livreur2");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //18
        $objUser = new User();
        $objUser->surface_partage_id = 2;
        $objUser->name = 'Coursier2';
        $objUser->surname = 'meme2';
        $objUser->email = 'coursier2@domain.cm';
        $objUser->phone = '694941672';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 6;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Coursier2');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Coursier2");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //19
        $objUser = new User();
        $objUser->surface_partage_id = 3;
        $objUser->name = 'Gestionnaire surface 3';
        $objUser->surname = 'mama3';
        $objUser->email = 'gestsurface3@domain.cm';
        $objUser->phone = '694945700';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 3;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire surface3');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire surface3");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //20
        $objUser = new User();
        $objUser->surface_partage_id = 3;
        $objUser->name = 'Livreur3';
        $objUser->surname = 'meme3';
        $objUser->email = 'livreur3@domain.cm';
        $objUser->phone = '694445512';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 5;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Livreur3');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Livreur3");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //21
        $objUser = new User();
        $objUser->surface_partage_id = 3;
        $objUser->name = 'Coursier3';
        $objUser->surname = 'meme3';
        $objUser->email = 'coursier3@domain.cm';
        $objUser->phone = '694901672';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 6;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Coursier3');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Coursier3");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //22
        $objUser = new User();
        $objUser->surface_partage_id = 4;
        $objUser->name = 'Gestionnaire surface 4';
        $objUser->surname = 'mama4';
        $objUser->email = 'gestsurface4@domain.cm';
        $objUser->phone = '692945770';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 3;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire surface4');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire surface4");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //23
        $objUser = new User();
        $objUser->surface_partage_id = 4;
        $objUser->name = 'Livreur4';
        $objUser->surname = 'meme4';
        $objUser->email = 'livreur4@domain.cm';
        $objUser->phone = '694345612';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 5;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Livreur4');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Livreur4");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //24
        $objUser = new User();
        $objUser->surface_partage_id = 4;
        $objUser->name = 'Coursier4';
        $objUser->surname = 'meme4';
        $objUser->email = 'coursier4@domain.cm';
        $objUser->phone = '694741672';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 6;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Coursier4');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Coursier4");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //25
        $objUser = new User();
        $objUser->surface_partage_id = 5;
        $objUser->name = 'Gestionnaire surface 5';
        $objUser->surname = 'mama5';
        $objUser->email = 'gestsurface5@domain.cm';
        $objUser->phone = '696945770';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 3;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire surface5');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire surface5");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //26
        $objUser = new User();
        $objUser->surface_partage_id = 5;
        $objUser->name = 'Livreur5';
        $objUser->surname = 'meme5';
        $objUser->email = 'livreur5@domain.cm';
        $objUser->phone = '694845612';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 5;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Livreur5');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Livreur5");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //27
        $objUser = new User();
        $objUser->surface_partage_id = 5;
        $objUser->name = 'Coursier5';
        $objUser->surname = 'meme5';
        $objUser->email = 'coursier5@domain.cm';
        $objUser->phone = '699941672';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 6;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Coursier5');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Coursier5");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //28
        $objUser = new User();
        $objUser->surface_partage_id = 6;
        $objUser->name = 'Gestionnaire surface 6';
        $objUser->surname = 'mama6';
        $objUser->email = 'gestsurface6@domain.cm';
        $objUser->phone = '694945070';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 3;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Gestionnaire surface6');
        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Gestionnaire surface6");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //29
        $objUser = new User();
        $objUser->surface_partage_id = 6;
        $objUser->name = 'Livreur6';
        $objUser->surname = 'meme6';
        $objUser->email = 'livreur6@domain.cm';
        $objUser->phone = '694443612';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 5;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Livreur6');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Livreur6");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

        //30
        $objUser = new User();
        $objUser->surface_partage_id = 6;
        $objUser->name = 'Coursier6';
        $objUser->surname = 'meme6';
        $objUser->email = 'coursier6@domain.cm';
        $objUser->phone = '694941679';
        $objUser->password = Hash::make('12345678');
        $objUser->role_id = 6;
        $objUser->published   = 1;
        $objUser->generateReference();
        $objUser->generateAlias('Coursier6');

        if(!$objUser->save())
        {
            $this->command->info("Fail Seeded user: Coursier6");
        }else{
            $this->command->info("Seeded user: ". $objUser->name);
        }

    }
}

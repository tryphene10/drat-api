<?php

use App\Option;
use Illuminate\Database\Seeder;


class OptionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    // $table->string('name');
    // $table->string('sms_basic');
    // $table->string('sms_number');
    // $table->string('tas_tel');
    // $table->string('tas_eamil');
    // $table->string('tas_site');
    // $table->string('tas_adresse');
    // $table->text('tas_about');

    public function run()
    {
        $objOption = new Option();
        $objOption->key = 'NAME';
        $objOption->value = 'SIMKHAA';
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: SIMKHAA");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = 'LOGO';
        $objOption->value = 'api/simkaah-api/public/logos/logo.png';
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: SIMKHAA");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "SMS_BASIC";
        $objOption->value = "Basic M0czcUZiTkhHQzVzYUFUYk03cXlyd1VLdW9IRm81UU46OTJaQ2o2MWlBNmlQdGp3Ug==";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: SMS_BASIC");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "SMS_NUMBER";
        $objOption->value = "694347232";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: TAS_NUMBER");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "TAS_TEL";
        $objOption->value = "694899843";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: TAS_TEL");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "TAS_EMAIL";
        $objOption->value = "info@team-solutions.org";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: TAS_EMAIL");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "TAS_SITE";
        $objOption->value = "https://team-solutions.org";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: TAS_SITE");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "TAS_ADRESS";
        $objOption->value = "Douala - Rue du marché new-deido (à 150 m de l'école primaire Petit Monde par Quifeurou)";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: TAS_ADRESS");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }

        $objOption = new Option();
        $objOption->key = "TAS_ABOUT";
        $objOption->value = "L'incubateur NTIC pour la Digitalisation & l'entreprenariat Social Durable Accompagner l'Afrique dans sa transformation digitale";
        $objOption->generateReference();
        $objOption->generateAlias($objOption->key);
        if(!$objOption->save())
        {
            $this->command->info("Fail Seeded Option: TAS_ABOUT");
        }else{
            $this->command->info("Seeded Option: ". $objOption->key);
        }
    }
}

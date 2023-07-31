<?php

use Illuminate\Database\Seeder;
use App\Volume;

class VolumeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //1
        $objVolume = new Volume();
        $objVolume->name = '50Kg';
        $objVolume->published   = 1;
        $objVolume->generateReference();
        $objVolume->generateAlias($objVolume->name);
        if(!$objVolume->save())
        {
            $this->command->info("Fail Seeded Volume: 50Kg ");
        }else{
            $this->command->info("Seeded Volume: ". $objVolume->name);
        }

        //2
        $objVolume = new Volume();
        $objVolume->name = '15L';
        $objVolume->published   = 1;
        $objVolume->generateReference();
        $objVolume->generateAlias($objVolume->name);
        if(!$objVolume->save())
        {
            $this->command->info("Fail Seeded Volume: 15L ");
        }else{
            $this->command->info("Seeded Volume: ". $objVolume->name);
        }

        //3
        $objVolume = new Volume();
        $objVolume->name = '25Kg';
        $objVolume->published   = 1;
        $objVolume->generateReference();
        $objVolume->generateAlias($objVolume->name);
        if(!$objVolume->save())
        {
            $this->command->info("Fail Seeded Volume: 25Kg");
        }else{
            $this->command->info("Seeded Volume: ". $objVolume->name);
        }

        //4
        $objVolume = new Volume();
        $objVolume->name = '12Kg';
        $objVolume->published   = 1;
        $objVolume->generateReference();
        $objVolume->generateAlias($objVolume->name);
        if(!$objVolume->save())
        {
            $this->command->info("Fail Seeded Volume: 12Kg");
        }else{
            $this->command->info("Seeded Volume: ". $objVolume->name);
        }

        //5
        $objVolume = new Volume();
        $objVolume->name = '25L';
        $objVolume->published   = 1;
        $objVolume->generateReference();
        $objVolume->generateAlias($objVolume->name);
        if(!$objVolume->save())
        {
            $this->command->info("Fail Seeded Volume: 25L");
        }else{
            $this->command->info("Seeded Volume: ". $objVolume->name);
        }

        //6
        $objVolume = new Volume();
        $objVolume->name = '5L';
        $objVolume->published   = 1;
        $objVolume->generateReference();
        $objVolume->generateAlias($objVolume->name);
        if(!$objVolume->save())
        {
            $this->command->info("Fail Seeded Volume: 5L");
        }else{
            $this->command->info("Seeded Volume: ". $objVolume->name);
        }
    }
}

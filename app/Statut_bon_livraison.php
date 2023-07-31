<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Statut_bon_livraison extends Model
{
    public function bon_livraisons(){
        return $this->hasMany('App\Bon_livraison');
    }
    
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while (Statut_bon_livraison::where('ref',$token)->first() instanceof Statut_bon_livraison);
            $this->attributes['ref'] = $token;

            return true;
        }
        return false;
    }

    //To generate an alias for the object based on the name of that object.
    public function generateAlias($name){
        $append = Config::get('constants.values.zero');
        if(empty($this->attributes['alias'])){
            do{
                if($append == Config::get('constants.values.zero')){
                    $alias = CustFunc::toAscii($name);
                }else{
                    $alias = CustFunc::toAscii($name)."-".$append;
                }
                $append += Config::get('constants.values.one');
            }while(Statut_bon_livraison::where('alias',$alias)->first() instanceof Statut_bon_livraison);
            $this->attributes['alias'] = $alias;
        }
    }

}

<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Ville_surface extends Model
{

    public function surfacePartage(){
        return $this->belongsTo('App\Surface_partage','surface_partage_id');
    }

    /*
    * Children relationship
    */
    public function Quartiers(){
        return $this->hasMany('App\Quartier_surface');
    }
    /*

    //To generate a 15 character reference
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while (Ville_surface::where('ref',$token)->first() instanceof Ville_surface);
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
            }while(Ville_surface::where('alias',$alias)->first() instanceof Ville_surface);
            $this->attributes['alias'] = $alias;
        }
    }*/
}

<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Surface_partage extends Model
{
    protected $fillable = [
        'name','published'
    ];

    public function userCooperative(){
        return $this->hasMany('App\User');
    }

    public function commandes(){
        return $this->hasMany('App\Commande');
    }

    public function villeSurfaces(){
        return $this->hasMany('App\Ville_surface');
    }

    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Surface_partage::where('ref',$token)->first() instanceof Surface_partage);
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
            }while(Surface_partage::where('alias',$alias)->first() instanceof Surface_partage);
            $this->attributes['alias'] = $alias;
        }
    }
}

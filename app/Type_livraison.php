<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Type_livraison extends Model
{
    protected $fillable = [
        'name'
    ];
    public function commandes(){
        return $this->hasMany('App\Commande');
    }
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while (Type_livraison::where('ref',$token)->first() instanceof Type_livraison);
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
            }while(Type_livraison::where('alias',$alias)->first() instanceof Type_livraison);
            $this->attributes['alias'] = $alias;
        }
    }
}

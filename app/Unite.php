<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Unite extends Model
{
    protected $fillable = [
        'published',
        'name'
    ];

    public function produits(){
        return $this->hasMany('App\Produit');
    }
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Unite::where('ref',$token)->first() instanceof Unite);
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
            }while(Unite::where('alias',$alias)->first() instanceof Unite);
            $this->attributes['alias'] = $alias;
        }
    }
}

<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Quartier extends Model
{   
    protected $fillable = [
        'name',
        'published'
    ];

    public function commandes(){
        return $this->hasMany('App\Commande');
    }
    public function ville(){
        return $this->belongsTo('App\Ville', 'ville_id');
    }

    //To generate a 15 character reference
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Quartier::where('ref',$token)->first() instanceof Quartier);
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
            }while(Quartier::where('alias',$alias)->first() instanceof Quartier);
            $this->attributes['alias'] = $alias;
        }
    }
}

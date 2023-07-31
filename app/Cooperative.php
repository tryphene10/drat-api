<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Cooperative extends Model
{
    protected $fillable=[
        'published',
        'name_quartier',
        'name',
        'name_ville'
    ];

    public function userProducteurCooperatives(){
        return $this->hasMany('App\User');
    }
    public function detailBonCmds(){
        return $this->hasMany('App\Detail_bon_cmd');
    }

    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Cooperative::where('ref',$token)->first() instanceof Cooperative);
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
            }while(Cooperative::where('alias',$alias)->first() instanceof Cooperative);
            $this->attributes['alias'] = $alias;
        }
    }
}

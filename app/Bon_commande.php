<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Bon_commande extends Model
{
    protected $fillable = [
        'user_coursier_id',
        'signature_bon_cmd'
    ];
    public function userGsp(){
        return $this->belongsTo('App\User','user_gsp_id');
    }
    public function userCoursier(){
        return $this->belongsTo('App\User', 'user_coursier_id');
    }
    public function detailBonCommandes(){
        return $this->hasMany('App\Detail_bon_cmd');
    }
    public function commandes(){
        return $this->hasMany('App\Commande');
    }
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Bon_commande::where('ref',$token)->first() instanceof Bon_commande);
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
            }while(Bon_commande::where('alias',$alias)->first() instanceof Bon_commande);
            $this->attributes['alias'] = $alias;
        }
    }
}

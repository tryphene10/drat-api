<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Bon_livraison extends Model
{
    protected $fillable = [
        'bon_livraison_id',
        'statut_bon_livraison_id',
        'signature_bon_livraison',
        'livraison_statut_id'
    ];
    //
    public function statutBonLivraison(){
        return $this->belongsTo('App\Statut_bon_livraison', 'statut_bon_livraison_id');
    }
    public function userLivreur(){
        return $this->belongsTo('App\User', 'user_livreur_id');
    }
    public function userGsp(){
        return $this->belongsTo('App\User', 'user_gsp_id');
    }
    public function commandes(){
        return $this->hasMany('App\Commande');
    }
    
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Bon_livraison::where('ref',$token)->first() instanceof Bon_livraison);
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
            }while(Bon_livraison::where('alias',$alias)->first() instanceof Bon_livraison);
            $this->attributes['alias'] = $alias;
        }
    }
}

<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Detail_bon_cmd extends Model
{
    protected $fillable = [
        'signature_detail_bon_commande'
    ];
    public function cooperative(){
        return $this->belongsTo('App\Cooperative', 'cooperative_id');
    }
    public function bonCommande(){
        return $this->belongsTo('App\Bon_commande', 'bon_commande_id');
    }
    public function produits(){
        return $this->hasMany('App\Produit');
    }
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Detail_bon_cmd::where('ref',$token)->first() instanceof Detail_bon_cmd);
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
            }while(Detail_bon_cmd::where('alias',$alias)->first() instanceof Detail_bon_cmd);
            $this->attributes['alias'] = $alias;
        }
    }
}

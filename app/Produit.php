<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Produit extends Model
{
    protected $fillable = [
        'published',
        'designation',
        'description',
        'prix_produit',
        'qte',
        'categorie_id',
        'unite_id',
        'type_vente',
        'prix_min_enchere',
        'begin_date',
        'end_date',
        'commande_id',
        'detail_bon_cmd_id',
        'statut_produit_id',
        'statut',
        'created_at',
        'updated_at',
        'delai'
    ];

    public function detailBonCmd(){
        return $this->belongsTo('App\Detail_bon_cmd', 'detail_bon_cmd_id');
    }
    public function commande(){
        return $this->belongsTo('App\Commande', 'commande_id');
    }
    public function unite(){
        return $this->belongsTo('App\Unite', 'unite_id');
    }
    public function userProducteur(){
        return $this->belongsTo('App\User', 'user_prod_id');
    }
    public function userGestionnaireCoop(){
        return $this->belongsTo('App\User', 'user_coop_id');
    }
    public function categorie(){
        return $this->belongsTo('App\Categorie', 'categorie_id');
    }
    public function propositions(){
        return $this->hasMany('App\Proposition');
    }
    public function produitImg(){
        return $this->hasMany('App\Produit_img');
    }
    public function statutProduit(){
        return $this->belongsTo('App\Statut_produit', 'statut_produit_id');
    }
    public function volume(){
        return $this->belongsTo('App\Volume', 'volume_id');
    }

    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Produit::where('ref',$token)->first() instanceof Produit);
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
            }while(Produit::where('alias',$alias)->first() instanceof Produit);
            $this->attributes['alias'] = $alias;
        }
    }
}

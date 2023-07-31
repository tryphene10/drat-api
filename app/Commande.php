<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Commande extends Model
{
    protected $fillable = [
        'statut_cmd_id',
        'bon_livraison_id',
        'bon_commande_id',
        'signature',
        'cni',
        'statut_livraison_id',
        'mode_payment',
        'paie_phone',
        'lieu_livraison',
        'type_livraison_id',
        'quartier_id',
        'transaction',
        'image_qrcode'
    ];


    public function bonLivraison(){
        return $this->belongsTo('App\Bon_livraison', 'bon_livraison_id');
    }

    public function surfacePartage(){
        return $this->belongsTo('App\Surface_partage', 'surface_partage_id');
    }

    public function quartier(){
        return $this->belongsTo('App\Quartier', 'quartier_id');
    }

    public function typeLivraison(){
        return $this->belongsTo('App\Type_livraison', 'type_livraison_id');
    }

    public function statutCmd(){
        return $this->belongsTo('App\Statut_cmd', 'statut_cmd_id');
    }

    public function bonCommande(){
        return $this->belongsTo('App\Bon_commande', 'bon_commande_id');
    }

    public function UserClient(){
        return $this->belongsTo('App\User', 'user_client_id');
    }

    public function produits(){
        return $this->hasMany('App\Produit');
    }

    public function statutLivraison(){
        return $this->belongsTo('App\Statut_livraison','statut_livraison_id');
    }

    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while ( Commande::where('ref',$token)->first() instanceof Commande);
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
            }while(Commande::where('alias',$alias)->first() instanceof Commande);
            $this->attributes['alias'] = $alias;
        }
    }

}

<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Proposition extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'statut_proposition_id'
    ];
    public function produit(){
        return $this->belongsTo('App\Produit', 'produit_id');
    }
    public function UserClient(){
        return $this->belongsTo('App\User', 'user_client_id');
    }
    public function statutProposition(){
        return $this->belongsTo('App\Statut_proposition', 'statut_proposition_id');
    }

    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while (Proposition::where('ref',$token)->first() instanceof Proposition);
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
            }while(Proposition::where('alias',$alias)->first() instanceof Proposition);
            $this->attributes['alias'] = $alias;
        }
    }
}

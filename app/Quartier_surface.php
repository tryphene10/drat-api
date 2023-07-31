<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Quartier_surface extends Model
{
    protected $fillable = [
        'name',
        'published'
    ];
    /*public function surfacePartages(){
        return $this->hasMany('App\Surface_partage');
    }*/
    public function villeSurface(){
        return $this->belongsTo('App\Ville_surface', 'ville_surface_id');
    }

    //To generate a 15 character reference
    public function generateReference(){

        if(empty($this->attributes['ref'])){
            do{
                $token = CustFunc::getToken(Config::get('constants.values.reference'));
            }
            while (Quartier_surface::where('ref',$token)->first() instanceof Quartier_surface);
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
            }while(Quartier_surface::where('alias',$alias)->first() instanceof Quartier_surface);
            $this->attributes['alias'] = $alias;
        }
    }
}

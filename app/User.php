<?php

namespace App;

use App\Helpers\CustFunc;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasApiTokens , Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'phone',
        'quartier',
        'ville',
        'published',
        'cooperative',
        'surface_partage',
        'role',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /*
   * Convert dates to Carbon.
   *
   */

    protected $dates = [
        'created_at',
        'updated_at'
    ];

    public function validatePassword($value)
    {
        $toReturn = [
            'success'=>false,
            'message'=>""
        ];
        if(empty($this->attributes['password']))
        {
            $toReturn['message']    = trans('messages.password.invalid.empty');
            return $toReturn;
        }
        if(!Hash::check($value, $this->attributes['password']))
        {
            $toReturn['message']    = trans('messages.password.invalid.default');
            return $toReturn;
        }

        $toReturn['success'] = true;
        return $toReturn;
    }


    public function isPublished()
    {
        return $this->published;
    }

    /*
     * Children relationship
         */
    public function positions(){
        return $this->hasMany('App\Position');
    }

    /*
         * Children relationship
         */
    public function demandesSender(){
        return $this->hasMany('App\Demande', 'user_sender_id');
    }

    /*
     * Children relationship
     */
    public function demandesRecever(){
        return $this->hasMany('App\Demande', 'user_receiver_id');
    }

    /*
         * Children relationship
         */
    public function role(){
        return $this->belongsTo('App\Role','role_id');
    }
    /*public function bonCommandeGsps(){
        return $this->hasMany('App\Bon_commande');
    }
    public function userCoursier(){
        return $this->belongsTo('App\User', 'user_coursier_id');
    }*/
   /*
   public function detailBonCommandes(){
        return $this->hasMany('App\User');
    }*/
    public function gspBonCommandeCreates(){
        return $this->hasMany('App\Bon_commande');
    }
    public function bonCommandeCousiers(){
        return $this->hasMany('App\Bon_commande');
    }
    public function cooperative(){
        return $this->belongsTo('App\Cooperative', 'cooperative_id');
    }
    public function surfacePartage(){
        return $this->belongsTo('App\Surface_partage', 'surface_partage_id');
    }
    public function bonLivraisonAssign(){
        return $this->hasMany('App\Bon_livraison');
    }
    public function bonLivraisonUserlivreur(){
        return $this->hasMany('App\Bon_livraison');
    }
    public function commandeUserClient(){
        return $this->hasMany('App\Commande');
    }
    public function prodProduitCreate(){
        return $this->hasMany('App\Produit');
    }
    public function gestionnaireProduitCreate(){
        return $this->hasMany('App\Produit');
    }
    public function proposition(){
        return $this->hasMany('App\Proposition');
    }
    public function users(){
        return $this->hasMany('App\User');
    }
    public function user(){
        return $this->belongsTo('App\User','user_id');
    }
    public function mailConfirmAcount($objUser) {
        $result = 'email envoyé!';
        $user = $objUser;
        $data = array('ref_user'=>$user->ref);
        Mail::send('mail_to_client', $data, function($message) use ($user) {
            $message->to($user->email, $user->name)->subject
            ('confirmation de votre compte');
            $message->from('mail@team-solutions.net','DRAT');
        });

        return $result;
    }
    public function mailChangePassword($objUser) {
        $result = 'email envoyé!';
        $user = $objUser;
        $data = array('ref_user'=>$user->ref);
        Mail::send('mail_to_change', $data, function($message) use ($user) {
            $message->to($user->email, $user->name)->subject
            ('confirmation de votre compte');
            $message->from('mail@team-solutions.net','DRAT');
        });

        return $result;
    }

    public function generateAlias($name)
    {
        $append = Config::get('constants.values.zero');
        if(empty($this->attributes['alias']))
        {
            do
            {
                if($append == Config::get('constants.values.zero'))
                {
                    $alias = CustFunc::toAscii($name);
                }
                else
                {
                    $alias = CustFunc::toAscii($name)."-".$append;
                }

                $append   += 1;
            }
            while
            (
                User::where('alias',$alias)
                    ->first()
                instanceof User
            );

            $this->attributes['alias'] = $alias;
        }
    }

    public function generateReference()
    {

        if(empty($this->attributes['ref']))
        {
            do
            {
                $token = CustFunc::getToken(Config::get('constants.size.ref.user'));
            }
            while
            (
                User::where('ref',$token)
                    ->first()
                instanceof User
            );

            $this->attributes['ref'] = $token;

            return true;
        }
        return false;
    }

    public function getAcceptApply(){
        /*$demdReceved=collect();
        $dmds=$this->demdReceved();
        foreach ($dmds as $dmd){
            if($dmd->statuts->alias="")
        }*/
    }
    public function sms($message, $to)
    {
        $sms_basic = Option::where("key", "=", "SMS_BASIC")->first();
        $sms_number = Option::where("key", "=", "SMS_NUMBER")->first();

        if(empty($sms_basic) and empty($sms_number)){
            $this->_errorCode = 1;
            $this->_response['message'][] = "Aucun paramètre dans la base de données pour les SMS.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //Récupération du Token
        //---------------------------------------------
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.orange.com/oauth/v2/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            CURLOPT_HTTPHEADER => array(
                "Authorization: ". $sms_basic->value,
                "Content-Type: application/x-www-form-urlencoded"
            )
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true);
        if(!isset($response["access_token"])) {
            return $response;
        }
        //---------------------------------------------

        //Envoie du SMS
        //------------------------------------------------
        $args = array(
            "outboundSMSMessageRequest" => array(
                "senderAddress" => "tel:+237". $sms_number->value,
                "senderName" => "SIMKAAH",
                "address" => "tel:+237".$to,
                "outboundSMSTextMessage" => array("message" => $message))
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B237694347232/requests",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($args),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "Authorization: Bearer ".$response["access_token"]
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response, true);
        return $response;
    }

}

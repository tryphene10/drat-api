<?php

namespace App\Http\Controllers\Api;

use App\Commande;
use App\Statut_livraison;
use App\Statut_produit;
use App\Statut_cmd;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Role;
use App\Proposition;
use App\Statut_proposition;
use App\Produit;
use App\Produit_img;
use DateTime;
use DateTimeZone;
use App\Type_livraison;
use App\Quartier;
use App\Quartier_surface;
use App\Surface_partage;
use App\Ville_surface;
use Carbon\Carbon;

class PropositionApiController extends Controller
{
    //function de vente au enchère d'un produit
    public function createAuction(Request $request){
        $this->_fnErrorCode = 1;

        $validator = Validator::make($request->all(), [
            'ref_produit'=>'String|required',
            'prix_propose'=>'integer|required'
        ]);

        if ($validator->fails())
        {
            if (!empty($validator->errors()->all()))
            {
                foreach ($validator->errors()->all() as $error)
                {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode               = 3;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = 'utilisateur non connecté';
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }
         //On vérifie le rôle client
         $objRole = Role::where('id', '=', $objUser->role_id)->first();
         if($objRole->alias != "client") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous devez être client pour effectuer une proposition.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
         }

        //Récupération de l'objet produit
        $objProduit = Produit::where('ref', '=', $request->get('ref_produit'))->where('published','=',1)->first();
        if(empty($objProduit))
        {
            $this->_errorCode               = 4;
            $this->_response['message'][]   = "Le produit n'existe pas";
            $this->_response['error_code']   = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //Récupération de l'objet statut_proposition
        $objStatutPropositon = Statut_proposition::where('alias', '=', 'waiting')->first();
        if (empty($objStatutPropositon)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Statut n'existe pas";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $hightPrix = DB::table("propositions")
        ->join('produits','produits.id','=','propositions.produit_id')
        ->join('statut_propositions','statut_propositions.id','=','propositions.statut_proposition_id')
        ->select('propositions.prix_proposition as prix')
        ->where('propositions.produit_id', '=', $objProduit->id)
        ->where('propositions.statut_proposition_id', '=', $objStatutPropositon->id)
        ->where('produits.published', '=', 1)
        ->orderBy('propositions.id','desc')
        ->first();

        // Start transaction!
        DB::beginTransaction();

        //Date du jour actuel
        date_default_timezone_set('Africa/Douala');
        $date_now = date('Y/m/d H:i:s', time());
        $dt = strtotime($date_now);//strtotime transforme $date_now en timestamp (nbre de seconde)


        $begin_date = $objProduit->begin_date;//Date de debut d'enchère
        $dt1 = strtotime($begin_date);//strtotime transforme $begin_date en timestamp (nbre de seconde)

        $end_date = $objProduit->end_date;//Date de fin d'enchère
        $dt2 = strtotime($end_date);//strtotime transforme $end_date en timestamp (nbre de seconde)

        if ($objProduit->prix_min_enchere != null) {
            if (($dt >= $dt1) && ($dt2 >= $dt)) {
                if($request->get('prix_propose') > $objProduit->prix_min_enchere){
                    if(empty($hightPrix) || ($request->get('prix_propose') > $hightPrix->prix)){
                        try{
                            $objProposition = new Proposition();
                            $objProposition->produit()->associate($objProduit);
                            $objProposition->userClient()->associate($objUser);
                            $objProposition->statutProposition()->associate($objStatutPropositon);
                            $objProposition->prix_proposition = $request->get('prix_propose');
                            $objProposition->published = 1;
                            $objProposition->generateReference();
                            $objProposition->generateAlias("proposition ".$objProposition->id."-".$objProposition->prix_proposition);
                            $objProposition->save();

                        }catch(Exception $objException){
                            $this->_errorCode = 6;
                            if (in_array($this->_env, ['local', 'development']))
                            {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code']  = $this->prepareErrorCode();
                            return response()->json( $this->_response );
                        }

                    }else{

                        $this->_errorCode = 7;
                        $this->_response['message'][] = "Le prix proposé est bas. ";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);

                    }

                }else{
                    DB::rollback();
                    $this->_errorCode = 8;
                    $this->_response['message'][] = "Le prix proposé est bas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

            }else {

                DB::rollback();
                $this->_errorCode = 9;
                $this->_response['message'][] = "la vente aux enchères n'a pas encore débuté";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);

            }

        }else {

            DB::rollback();
            $this->_errorCode = 10;
            $this->_response['message'][] = "Le produit n'a pas de prix d'enchère.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);

        }

        DB::commit();

        $toReturn = [
            'objet' => $objProposition
        ];
        $this->_response['message']    = "Proposition effectuée.";
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json( $this->_response );
    }

    // liste des enchères
    public function listAuctions(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'ref_produit'=>'String|required'
        ]);

        if ($validator->fails())
        {
            if (!empty($validator->errors()->all()))
            {
                foreach ($validator->errors()->all() as $error)
                {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode               = 3;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = 'utilisateur non connecté';
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien admin ou gestionnaire-surface
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if(!in_array($objRole->alias, array("administrateur","gestionnaire-surface","client"))) {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        $objProduit = Produit::where('ref','=',$request->get('ref_produit'))->where('published','=',1)->first();
        if(empty($objProduit)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le produit n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        $auctionColl = collect();

        try
        {
            //liste d'enchère acceptée pour un produit
            $objAuctionAccepted = DB::table('propositions')
            ->join('produits', 'produits.id', '=', 'propositions.produit_id')
            ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
            ->join('users', 'users.id', '=', 'propositions.user_client_id')
            ->select('propositions.ref as ref_proposition',
                'propositions.created_at as date_post_proposition',
                'propositions.prix_proposition as prix_proposition',
                'users.name as name_customer',
                'users.surname as surname_customer',
                'users.email as email_customer',
                'users.phone as phone_customer',
                'statut_propositions.ref as ref_statut_proposition',
                'statut_propositions.name as name_statut_proposition')
            ->where('propositions.published', '=', 1)
            ->where('propositions.statut_proposition_id', '=', 2)
            ->where('propositions.produit_id', '=', $objProduit->id)
            ->first();

            if (!empty($objAuctionAccepted)){

                foreach($objAuctionAccepted as $itemAccepted) {
                    $auctionColl->push($itemAccepted);
                }

            }


        }catch (Exception $objException){

            $this->_errorCode               = 6;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = $objException->getMessage();
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        try
        {
            //liste des enchères exceptée l'enchère acceptée
            $objAuctions = DB::table('propositions')
            ->join('produits', 'produits.id', '=', 'propositions.produit_id')
            ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
            ->join('users', 'users.id', '=', 'propositions.user_client_id')
            ->select('propositions.ref as ref_proposition',
                'propositions.created_at as date_post_proposition',
                'propositions.prix_proposition as prix_proposition',
                'users.name as name_user',
                'users.surname as surname_user',
                'users.email as email_user',
                'users.phone as phone_user',
                'statut_propositions.ref as ref_statut_proposition',
                'statut_propositions.name as name_statut_proposition')
            ->where('propositions.published', '=', 1)
            ->where('propositions.statut_proposition_id', '!=', 2)
            ->where('propositions.produit_id', '=', $objProduit->id)
            ->orderBy('propositions.id', 'desc')
            ->get();

            foreach($objAuctions as $item) {
                $auctionColl->push($item);
            }

        }catch (Exception $objException){

            $this->_errorCode               = 7;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = $objException->getMessage();
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $toReturn = [
            'auctions'=> $auctionColl
        ];
        $this->_response['message']    = "Liste des enchères d'un produit.";
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    // Affichage la date actuelle
    public function getCurrentDate(Request $request)
    {
        $this->_fnErrorCode = 1;

        date_default_timezone_set('Africa/Douala');
        $date_now = date('Y-m-d'.'\T'.'H:i:s', time());

        $toReturn = [
            'current_date'=> $date_now
        ];
        $this->_response['message']    = "Date courante du serveur.";
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    // liste des enchères d'un client sur un produit
    public function listAuctionsOfCustomer(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'ref_produit'=>'String|required'
        ]);

        if ($validator->fails())
        {
            if (!empty($validator->errors()->all()))
            {
                foreach ($validator->errors()->all() as $error)
                {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode               = 2;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = 'utilisateur non connecté';
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien client
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if(!in_array($objRole->alias, array("client"))) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        $objProduit = Produit::where('ref','=',$request->get('ref_produit'))->where('published','=',1)->first();
        if(empty($objProduit)) {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Le produit n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        $auctionColl = collect();

        try
        {
            //liste d'enchère acceptée pour un produit
            $objAuctionAccepted = DB::table('propositions')
            ->join('produits', 'produits.id', '=', 'propositions.produit_id')
            ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
            ->join('users', 'users.id', '=', 'propositions.user_client_id')
            ->select('propositions.ref as ref_proposition',
                'propositions.created_at as date_post_proposition',
                'propositions.prix_proposition as prix_proposition',
                'users.name as name_user',
                'users.surname as surname_user',
                'users.email as email_user',
                'users.phone as phone_user',
                'statut_propositions.ref as ref_statut_proposition',
                'statut_propositions.name as name_statut_proposition')
            ->where('propositions.published', '=', 1)
            ->where('propositions.statut_proposition_id', '=', 2)
            ->where('propositions.user_client_id', '=', $objUser->id)
            ->where('propositions.produit_id', '=', $objProduit->id)
            ->first();

            if (!empty($objAuctionAccepted)) {

                foreach($objAuctionAccepted as $itemAccepted) {
                    $auctionColl->push($itemAccepted);
                }
            }

        }catch (Exception $objException){

            $this->_errorCode               = 5;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = $objException->getMessage();
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        try
        {
            //liste des enchères exceptée l'enchère acceptée
            $objAuctions = DB::table('propositions')
            ->join('produits', 'produits.id', '=', 'propositions.produit_id')
            ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
            ->join('users', 'users.id', '=', 'propositions.user_client_id')
            ->select('propositions.ref as ref_proposition',
                'propositions.created_at as date_post_proposition',
                'propositions.prix_proposition as prix_proposition',
                'users.name as name_user',
                'users.surname as surname_user',
                'users.email as email_user',
                'users.phone as phone_user',
                'statut_propositions.ref as ref_statut_proposition',
                'statut_propositions.name as name_statut_proposition')
            ->where('propositions.published', '=', 1)
            ->where('propositions.statut_proposition_id', '!=', 2)
            ->where('propositions.user_client_id', '=', $objUser->id)
            ->where('propositions.produit_id', '=', $objProduit->id)
            ->get();

            foreach($objAuctions as $item) {
                $auctionColl->push($item);
            }

        }catch (Exception $objException){

            $this->_errorCode               = 6;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = $objException->getMessage();
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $toReturn = [
            'produit'=> $objProduit,
            'propositions'=> $auctionColl
        ];
        $this->_response['message']    = "Liste des enchères d'un client sur un produit.";
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Fonction qui permet d'afficher le détail d'une proposition d'enchère
	 public function auctionDetail(Request $request)
	 {
		  $this->_fnErrorCode = 1;
		  $validator = Validator::make($request->all(), [
			'ref_proposition'=>'string|required'
          ]);

		if ($validator->fails()){
			if (!empty($validator->errors()->all())){
				foreach ($validator->errors()->all() as $error){
					$this->_response['message'][] = $error;
				}
			}
			$this->_errorCode = 2;
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objProposition = Proposition::where('ref','=',$request->get('ref_proposition'))->first();
		if (empty($objProposition)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "La proposition n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		try {

			//liste d'enchère pour un produit
            $objDetailAuction = DB::table('propositions')
            ->join('produits', 'produits.id', '=', 'propositions.produit_id')
            ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
            ->join('users', 'users.id', '=', 'propositions.user_client_id')
            ->select('produits.ref as ref_produit',
                'produits.designation as designation',
                'produits.description as description',
                'produits.qte as quantite_produit',
                'users.name as name_user',
                'users.surname as surname_user',
                'users.email as email_user',
                'users.phone as phone_user',
                'statut_propositions.ref as ref_statut_proposition',
                'statut_propositions.name as name_statut_proposition')
            ->where('propositions.published', '=', 1)
            ->where('propositions.id', '=', $objProposition->id)
            ->first();

		} catch (Exception $objException) {
			$this->_errorCode = 4;
			if (in_array($this->_env, ['local', 'development'])) {
			}
			$this->_response['message'] = $objException->getMessage();
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		 $toReturn = [
			 'proposition' => $objProposition,
			 'detail_proposition' => $objDetailAuction
		 ];
		 $this->_response['message'] = "Detail d'une enchère.";
		 $this->_response['data'] = $toReturn;
		 $this->_response['success'] = true;
		 return response()->json($this->_response);
	 }

     public function auctionStop(Request $request){
        // dd($request);
         $this->_fnErrorCode = 1;
         $validator = Validator::make($request->all(), [
             'ref_produit'=>'string|required'
         ]);

         if ($validator->fails()){
             if (!empty($validator->errors()->all())){
                 foreach ($validator->errors()->all() as $error){
                     $this->_response['message'][] = $error;
                 }
             }
             $this->_errorCode = 2;
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         $objUser = Auth::user();

         DB::beginTransaction();

         $objProduit = Produit::where('ref','=',$request->get('ref_produit'))->first();
         if(empty($objProduit)) {
             DB::rollback();
             $this->_errorCode = 3;
             $this->_response['message'][] = "Le produit n'existe pas.";
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
         if(empty($objStatutCmde)) {
             DB::rollback();
             $this->_errorCode = 5;
             $this->_response['message'][] = "Le statut de la commande n'existe pas!";
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         $objStatutPropositon = Statut_proposition::where('alias', '=', 'accept')->first();
         if(empty($objStatutPropositon)) {
             DB::rollback();
             $this->_errorCode = 6;
             $this->_response['message'][] = "Statut n'existe pas";
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         $objStatutPropositonWaiting = Statut_proposition::where('alias', '=', 'waiting')->first();
         if(empty($objStatutPropositonWaiting)) {
             DB::rollback();
             $this->_errorCode = 7;
             $this->_response['message'][] = "Statut n'existe pas";
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         $data = array();
         $objCommande = array();
         $proposition = (object)[];
         $returnSms = array();
        if(!empty($objUser)){
            //On vérifie le rôle client
            $objRole = Role::where('id', '=', $objUser->role_id)->first();

            if($objRole->alias != "client") {

               if($objProduit->type_vente != "vente directe"){

                    $proposition = Proposition::where('propositions.produit_id', '=', $objProduit->id)
                    ->latest('propositions.created_at')
                    ->first();

                    if(empty($proposition)){

                        date_default_timezone_set('Africa/Douala');
                        $date_now = date('Y-m-d H:i:s', time());
                        # Le produit passe à la vente directe
                        try {

                            $objProduit->update([
                                'published' => 1,
                                'type_vente' => 'vente directe',
                                'prix_produit' => $objProduit->prix_min_enchere,
                                'prix_min_enchere' => NULL,
                                'delai' => NULL,
                                'begin_date' => NULL,
                                'end_date' => NULL,
                                'created_at' => $date_now,
                                'updated_at' => $date_now
                            ]);

                        }catch (Exception $objException){
                            DB::rollback();
                            $this->_errorCode = 8;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        # Notification du producteur du produit
                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                        //envoie de sms au producteur
                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                        try{

                            $msg = "Le produit ".$objProduit->designation.", n'a pas été payé. Par consequent, il est automatiquement passé en vente directe. Connectez-vous a " .$cooll. "pour le remettre au enchére si vous le voulez.";
                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);

                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 9;
                            if (in_array($this->_env, ['local', 'development'])) {
                                $this->_response['message'] = $objException->getMessage();
                            }
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }else{

                        if($proposition->statut_proposition_id == $objStatutPropositonWaiting->id){
                            try{
                                $proposition->update(['statut_proposition_id' => $objStatutPropositon->id]);
                            }catch(Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 10;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objCustomer = User::where('id','=',$proposition->user_client_id)->first();

                            try {

                                $objCommande = new Commande();
                                $objCommande->montant = $proposition->prix_proposition;
                                $objCommande->published = 1;
                                $objCommande->generateReference();
                                $objCommande->generateAlias("Commande".$objCommande->id);
                                $objCommande->UserClient()->associate($objCustomer);
                                $objCommande->statutCmd()->associate($objStatutCmde);
                                $objCommande->image_qrcode = $this->generateQRCode($objCommande->ref);
                                if($objCommande->save()){
                                    $objProduit->update(['commande_id' => $objCommande->id]);
                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 11;
                                    $this->_response['message'][] = "echec de modifi";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 12;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                            try{

                                $msg = "Vous avez gagné : ".$objProduit->designation." a la vente au enchére. Votre facture s'éleve à ".$objCommande->montant." cliquer sur ce lien pour les voir ".$cooll." NB : si dans un délai de 30min, votre commande est impayée, le produit vous sera retiré.";
                                $returnSms = $objCustomer->sms($msg, $objCustomer->phone);

                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 13;
                                if (in_array($this->_env, ['local', 'development'])) {
                                    $this->_response['message'] = $objException->getMessage();
                                }
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }

                    }
               }
            }

            if($objRole->alias == "client"){

                if($objProduit->type_vente != "vente directe"){

                    $proposition = Proposition::where('propositions.produit_id', '=', $objProduit->id)
                    ->latest('propositions.created_at')
                    ->first();

                    $maxPrice = Proposition::where('produit_id','=',$objProduit->id)
                    ->max('prix_proposition');

                    if(!empty($proposition)) {

                        if($proposition->prix_proposition == $maxPrice){
                            if($objUser->id == $proposition->user_client_id){
                                if($proposition->statut_proposition_id == $objStatutPropositonWaiting->id){
                                    try{
                                        $proposition->update(['statut_proposition_id' => $objStatutPropositon->id]);
                                    }catch(Exception $objException){
                                        DB::rollback();
                                        $this->_errorCode = 14;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    try {

                                        $objCommande = new Commande();
                                        $objCommande->montant = $proposition->prix_proposition;
                                        $objCommande->published = 1;
                                        $objCommande->generateReference();
                                        $objCommande->generateAlias("Commande".$objCommande->id);
                                        $objCommande->UserClient()->associate($objUser);
                                        $objCommande->statutCmd()->associate($objStatutCmde);
                                        $objCommande->image_qrcode=$this->generateQRCode($objCommande->ref);
                                        if($objCommande->save()){
                                            $objProduit->update(['commande_id' => $objCommande->id]);
                                        }else {
                                            DB::rollback();
                                            $this->_errorCode = 15;
                                            $this->_response['message'][] = "echec de modifi";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 16;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                    try{

                                        $msg = "Vous avez gagné : ".$objProduit->designation." a la vente au enchére. Votre facture s'éleve à ".$objCommande->montant." cliquer sur ce lien pour les voir ".$cooll." NB : si dans un délai de 30min, votre commande est impayée, le produit vous sera retiré.";
                                        $returnSms = $objUser->sms($msg, $objUser->phone);

                                    } catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 17;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else{

                                    DB::rollback();
                                    $this->_errorCode = 18;
                                    $this->_response['message'][] = "cette proposition a déjà été acceptée";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
                            }

                        }else {
                            DB::rollback();
                            $this->_errorCode = 19;
                            $this->_response['message'][] = "vous n'avez pas gagné Ce produit !";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }else{

                        if ($objProduit->type_vente != "vente directe")  {

                            date_default_timezone_set('Africa/Douala');
                            $date_now = date('Y-m-d H:i:s', time());
                            # Le produit passe à la vente directe
                            try {
                                $objProduit->update([
                                    'published' => 1,
                                    'type_vente'=>'vente directe',
                                    'prix_produit'=>$objProduit->prix_min_enchere,
                                    'prix_min_enchere'=>NULL,
                                    'delai'=>NULL,
                                    'begin_date'=>NULL,
                                    'end_date'=>NULL,
                                    'created_at'=> $date_now,
                                    'updated_at'=> $date_now
                                ]);
                            }catch (Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 20;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            # Notification du producteur du produit
                            $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                            //envoie de sms au producteur
                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                            try{
                                $msg = "Le produit ".$objProduit->designation.", n'a pas été payé. Par consequent, il est automatiquement passé en vente directe. Connectez-vous a " .$cooll. "pour le remettre au enchére si vous le voulez.";
                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                //dd($returnSms);
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 21;
                                if (in_array($this->_env, ['local', 'development'])) {
                                    $this->_response['message'] = $objException->getMessage();
                                }
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }
                    }
                }
            }

        }else{

            if($objProduit->type_vente != "vente directe"){

                $proposition = Proposition::where('propositions.produit_id', '=', $objProduit->id)
                ->latest('propositions.created_at')
                ->first();

                if(empty($proposition)){

                    date_default_timezone_set('Africa/Douala');
                    $date_now = date('Y-m-d H:i:s', time());
                    # Le produit passe à la vente directe
                    try {

                        $objProduit->update([
                            'published' => 1,
                            'type_vente' => 'vente directe',
                            'prix_produit' => $objProduit->prix_min_enchere,
                            'prix_min_enchere' => NULL,
                            'delai' => NULL,
                            'begin_date' => NULL,
                            'end_date' => NULL,
                            'created_at' => $date_now,
                            'updated_at' => $date_now
                        ]);

                    }catch (Exception $objException){
                        DB::rollback();
                        $this->_errorCode = 22;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    # Notification du producteur du produit
                    $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                    //envoie de sms au producteur
                    $cooll = "https://draht.team-solutions.net/#/dashboards/";
                    try{

                        $msg = "Le produit ".$objProduit->designation.", n'a pas été payé. Par consequent, il est automatiquement passé en vente directe. Connectez-vous a " .$cooll. "pour le remettre au enchére si vous le voulez.";
                        $returnSms = $userProducteur->sms($msg, $userProducteur->phone);

                    } catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 23;
                        if (in_array($this->_env, ['local', 'development'])) {
                            $this->_response['message'] = $objException->getMessage();
                        }
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }else{

                    if($proposition->statut_proposition_id == $objStatutPropositonWaiting->id){
                        try{
                            $proposition->update(['statut_proposition_id' => $objStatutPropositon->id]);
                        }catch(Exception $objException){
                            DB::rollback();
                            $this->_errorCode = 24;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $objCustomer = User::where('id','=',$proposition->user_client_id)->first();

                        try {

                            $objCommande = new Commande();
                            $objCommande->montant = $proposition->prix_proposition;
                            $objCommande->published = 1;
                            $objCommande->generateReference();
                            $objCommande->generateAlias("Commande".$objCommande->id);
                            $objCommande->UserClient()->associate($objCustomer);
                            $objCommande->statutCmd()->associate($objStatutCmde);
                            $objCommande->image_qrcode=$this->generateQRCode($objCommande->ref); 
                            if($objCommande->save()){
                                $objProduit->update(['commande_id' => $objCommande->id]);
                            }else {
                                DB::rollback();
                                $this->_errorCode = 25;
                                $this->_response['message'][] = "echec de modifi";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 26;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                        try{
                            $msg = "Vous avez gagné : ".$objProduit->designation." a la vente au enchére. Votre facture s'éleve à ".$objCommande->montant." cliquer sur ce lien pour les voir ".$cooll." NB : si dans un délai de 30min, votre commande est impayée, le produit vous sera retiré.";
                            $returnSms = $objCustomer->sms($msg, $objCustomer->phone);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 27;
                            if (in_array($this->_env, ['local', 'development'])) {
                                $this->_response['message'] = $objException->getMessage();
                            }
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }

                }
            }
        }

        $data = [
            'produit' => $objProduit,
            'proposition' => $proposition,
            'commande' => $objCommande,
            'sms' => $returnSms
        ];
        // Commit the queries!
        DB::commit();
        $toReturn = [
            "objet" => $data
        ];
        $this->_response['message'] = "le produit a été ajouter à la commande";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
     }

     //Fonction permettant de payer une commande après qu'une enchère ait été acceptée
     public function orderPayment(Request $request)
     {
         $this->_fnErrorCode = 1;

         //On vérifie que la commande est bien envoyé !
         $objData= collect(json_decode($request->getContent(), true));
         if (empty($objData)) {
             $this->_errorCode = 2;
             $this->_response['message'][] = "La liste est vide!";
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         $objUser = Auth::user();
         if(empty($objUser)){
             $this->_errorCode = 3;
             if(in_array($this->_env, ['local', 'development'])){
                 $this->_response['message'] = 'Cette action nécéssite une connexion.';
             }
             $this->_response['error_code']  = $this->prepareErrorCode();
             return response()->json( $this->_response );
         }

         //On vérifie le rôle client
         $objRole = Role::where('id', '=', $objUser->role_id)->first();
         if($objRole->alias != "client") {
             $this->_errorCode = 4;
             $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
             $this->_response['error_code'] = $this->prepareErrorCode();
             return response()->json($this->_response);
         }

         DB::beginTransaction();

         $objStatutCmde = (object)[];
         $objCmde = (object)[];
         $resultOperateur = (object)[];
         $resultTransaction = (object)[];
         $objStatut_produit = (object)[];
         $returnSms = (object)[];

         if($objData->has("paiement_methode")){

             //--------------------------------------------------------------------
             // CURL
             //--------------------------------------------------------------------

             if($objData->has("paie_phone")) {

                 $phone = $objData["paie_phone"];

                 if($objData->has("type_livraison")) {
                    //-----------------------------------------------------------------------------------------------
                    //Recupère l'objet Type_livraison
                    //-----------------------------------------------------------------------------------------------
                    $objType_livraison = Type_livraison::where('ref', '=', $objData["type_livraison"])->first();
                    if(empty($objType_livraison)) {
                        DB::rollback();
                        $this->_errorCode = 5;
                        $this->_response['message'][] = "Le type_livraison n'existe pas!";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    if($objType_livraison->id == 1) {//1:A domicile

                        if($objData->has("ville")) {

                            $objVille_surface = Ville_surface::where('id', '=', $objData["ville"])->first();
                            if(empty($objVille_surface)) {
                                DB::rollback();
                                $this->_errorCode = 6;
                                $this->_response['message'][] = "La ville n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objSurface = Surface_partage::where('ville_surface_id','=',$objVille_surface->surface_partage_id)->first();
                            if(empty($objSurface)) {
                                DB::rollback();
                                $this->_errorCode = 7;
                                $this->_response['message'][] = "La surface n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            if($objData->has("lieu_livraison")) {

                                //-----------------------------------------------------------------------------------------------
                                //Statut waiting
                                //-----------------------------------------------------------------------------------------------
                                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                if(empty($objStatutCmde)) {
                                    DB::rollback();
                                    $this->_errorCode = 8;
                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                //-----------------------------------------------------------------------------------------------
                                //Statut_livraison : waiting
                                //-----------------------------------------------------------------------------------------------
                                $objStatut_livraison = Statut_livraison::where('alias', '=', 'waiting')->first();
                                if(empty($objStatut_livraison)) {
                                    DB::rollback();
                                    $this->_errorCode = 9;
                                    $this->_response['message'][] = "Le statut_livraison de la commande n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                if(strlen($phone) == 12) {

                                    if(preg_match('/^(237)(\d{3})(\d{3})(\d{3})$/', $phone, $matches)) {

                                        if($objData['paiement_methode'] == 'mtn') {

                                            $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                        }elseif ($objData['paiement_methode'] == 'orange') {

                                            $phone = $matches[2].$matches[3].$matches[4];

                                        }
                                    }

                                }elseif(strlen($phone) == 9) {

                                    if(preg_match('/^(6)(\d{2})(\d{3})(\d{3})$/', $phone, $matches)) {

                                        if($objData['paiement_methode'] == 'mtn') {

                                            $phone = "237".$matches[1].$matches[2].$matches[3].$matches[4];

                                        }elseif ($objData['paiement_methode'] == 'orange') {

                                            $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                        }
                                    }

                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 10;
                                    $this->_response['message'][] = "Phone incorrect!.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                $objCommande = Commande::where('ref', '=', $objData["commande"])->first();
                                if(empty($objCommande)) {
                                    DB::rollback();
                                    $this->_errorCode = 11;
                                    $this->_response['message'][] = "La commande n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                //-----------------------------------------------------------------------------------------------
                                //Mettre à jour les colonnes d'une commande
                                //-----------------------------------------------------------------------------------------------

                                try{

                                    $objCommande->update([
                                        'mode_payment' => $objData["paiement_methode"],
                                        'paie_phone' => $phone,
                                        'lieu_livraison' => $objData["lieu_livraison"],
                                        'type_livraison_id' => $objType_livraison->id,
                                        'surface_partage_id' => $objSurface->id
                                    ]);

                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 12;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                //-----------------------------------------------------------------------------------------------
                                //Initiation d'un paiement
                                //-----------------------------------------------------------------------------------------------
                                $postfields = array(
                                    'phone' => $objCommande->paie_phone,
                                    'montant' => $objCommande->montant,
                                    'transactionkey' => $objCommande->ref,
                                    'apiKey' => $objData["apiKey"],
                                    'secretKey' => $objData["secretKey"],
                                    'methode_paiement' => $objCommande->mode_payment
                                );

                                try {
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/marchand/transaction/create');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                    $result = json_decode(curl_exec($ch), true);

                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 13;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                $resultOperateur = $result['data']['operateur'];
                                $objCmde = Commande::where('ref','=',$objCommande->ref)->first();

                                if($resultOperateur['name'] == "orange") {

                                    $resultTransaction = $result['data']['transaction'];

                                    //-----------------------------------------------------------------------------------------------
                                    //Mettre à jour la colonne transaction d'une commande
                                    //-----------------------------------------------------------------------------------------------

                                    try{

                                        $objCmde->update([
                                            'transaction' => $resultTransaction['ref']
                                        ]);

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 14;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    if($resultTransaction['transaction_status'] == 'SUCCESS') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut pay
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 15;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 16;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 17;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                        try {
                                            $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 18;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
                                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                        //envoie de sms au producteur
                                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                        try{
                                            $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                        } catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 19;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                                $this->_response['message'] = $objException->getMessage();
                                            }
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 20;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 21;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }


                                    }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Check de paiement Orange
                                        //-----------------------------------------------------------------------------------------------
                                        $postfields = array(
                                            'ref_transaction' => $resultTransaction['ref']
                                        );

                                        try {
                                            $ch = curl_init();
                                            curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/orange/payment/status/check');
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                            curl_setopt($ch, CURLOPT_POST, 1);
                                            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                            $result = json_decode(curl_exec($ch), true);

                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 22;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $resultTransaction = $result['data']['objet'];

                                        if($resultTransaction['transaction_status'] == 'SUCCESS') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut pay
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 23;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 24;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 25;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();
                                            try {
                                                $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 26;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                            //envoie de sms au producteur
                                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                            try{
                                                $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                            } catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 27;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                    $this->_response['message'] = $objException->getMessage();
                                                }
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 28;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 29;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 30;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 31;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'INITIATED') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 32;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 33;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'EXPIRED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 34;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 35;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 36;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif ($resultTransaction['transaction_status'] == 'INITIATED') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Check de paiement Orange
                                        //-----------------------------------------------------------------------------------------------
                                        $postfields = array(
                                            'ref_transaction' => $resultTransaction['ref']
                                        );

                                        try {
                                            $ch = curl_init();
                                            curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/orange/payment/status/check');
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                            curl_setopt($ch, CURLOPT_POST, 1);
                                            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                            $result = json_decode(curl_exec($ch), true);

                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 37;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $resultTransaction = $result['data']['objet'];

                                        if($resultTransaction['transaction_status'] == 'SUCCESS') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut pay
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 38;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 39;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 40;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();
                                            try {
                                                $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 41;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }
                                            $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                            //envoie de sms au producteur
                                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                            try{
                                                $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                            } catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 42;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                    $this->_response['message'] = $objException->getMessage();
                                                }
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 43;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 44;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 45;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 46;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'INITIATED') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 47;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 48;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'EXPIRED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 49;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 50;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 51;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif ($resultTransaction['transaction_status'] == 'EXPIRED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 52;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 53;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 54;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }elseif($resultOperateur['name'] == "mtn") {

                                    $resultTransaction = $result['data']['transaction'];

                                    try{

                                        $objCmde->update([
                                            'transaction' => $resultTransaction['ref']
                                        ]);

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 55;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut pay
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 56;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 57;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 58;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                        try {
                                            $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 59;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                        //envoie de sms au producteur
                                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                        try{
                                            $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                        } catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 60;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                                $this->_response['message'] = $objException->getMessage();
                                            }
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }


                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 61;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 62;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Check de paiement Orange
                                        //-----------------------------------------------------------------------------------------------
                                        $postfields = array(
                                            'ref_transaction' => $resultTransaction['ref']
                                        );

                                        try {
                                            $ch = curl_init();
                                            curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/mtn/payment/status/check');
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                            curl_setopt($ch, CURLOPT_POST, 1);
                                            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                            $result = json_decode(curl_exec($ch), true);

                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 63;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $resultTransaction = $result['data']['objet'];

                                        if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut pay
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 64;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 65;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 66;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                            try {
                                                $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 67;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                            //envoie de sms au producteur
                                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                            try{
                                                $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                            } catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 68;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                    $this->_response['message'] = $objException->getMessage();
                                                }
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 69;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 70;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 71;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 72;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 73;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 74;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 75;
                                    $this->_response['message'][] = "Paramètre de payment manquant.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }else {
                                DB::rollback();
                                $this->_errorCode = 76;
                                $this->_response['message'][] = "Veuillez saisir un lieu de livraison!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }else {
                            DB::rollback();
                            $this->_errorCode = 77;
                            $this->_response['message'][] = "Veuillez choisir un quartier!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }elseif($objType_livraison->id == 2) {//2:Point distribution drat

                        if($objData->has("ville")) {

                            $objVille_surface = Ville_surface::where('id', '=', $objData["ville"])->first();
                            if(empty($objVille_surface)) {
                                DB::rollback();
                                $this->_errorCode = 78;
                                $this->_response['message'][] = "Le quartier n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objSurface = Surface_partage::where('id','=',$objVille_surface->surface_partage_id)->first();
                            if(empty($objSurface)) {
                                DB::rollback();
                                $this->_errorCode = 79;
                                $this->_response['message'][] = "La surface n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //-----------------------------------------------------------------------------------------------
                            //Statut waiting
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 80;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //-----------------------------------------------------------------------------------------------
                            //Statut_livraison : waiting
                            //-----------------------------------------------------------------------------------------------
                            $objStatut_livraison = Statut_livraison::where('alias', '=', 'waiting')->first();
                            if(empty($objStatut_livraison)) {
                                DB::rollback();
                                $this->_errorCode = 81;
                                $this->_response['message'][] = "Le statut_livraison de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            if(strlen($phone) == 12) {

                                if(preg_match('/^(237)(\d{3})(\d{3})(\d{3})$/', $phone, $matches)) {

                                    if($objData['paiement_methode'] == 'mtn') {

                                        $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                    }elseif ($objData['paiement_methode'] == 'orange') {

                                        $phone = $matches[2].$matches[3].$matches[4];

                                    }
                                }

                            }elseif(strlen($phone) == 9) {

                                if(preg_match('/^(6)(\d{2})(\d{3})(\d{3})$/', $phone, $matches)) {

                                    if($objData['paiement_methode'] == 'mtn') {

                                        $phone = "237".$matches[1].$matches[2].$matches[3].$matches[4];

                                    }elseif ($objData['paiement_methode'] == 'orange') {

                                        $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                    }
                                }

                            }else {
                                DB::rollback();
                                $this->_errorCode = 82;
                                $this->_response['message'][] = "Phone incorrect!.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objCommande = Commande::where('ref', '=', $objData["commande"])->first();
                            if(empty($objCommande)) {
                                DB::rollback();
                                $this->_errorCode = 83;
                                $this->_response['message'][] = "La commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //-----------------------------------------------------------------------------------------------
                            //Mettre à jour les colonnes d'une commande
                            //-----------------------------------------------------------------------------------------------

                            try{

                                $objCommande->update([
                                    'mode_payment' => $objData["paiement_methode"],
                                    'paie_phone' => $phone,
                                    'type_livraison_id' => $objType_livraison->id,
                                    'surface_partage_id' => $objSurface->id
                                ]);

                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 84;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //-----------------------------------------------------------------------------------------------
                            //Initiation d'un paiement
                            //-----------------------------------------------------------------------------------------------
                            $postfields = array(
                                'phone' => $objCommande->paie_phone,
                                'montant' => $objCommande->montant,
                                'transactionkey' => $objCommande->ref,
                                'apiKey' => $objData["apiKey"],
                                'secretKey' => $objData["secretKey"],
                                'methode_paiement' => $objCommande->mode_payment
                            );

                            try {
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/marchand/transaction/create');
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                $result = json_decode(curl_exec($ch), true);

                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 85;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $resultOperateur = $result['data']['operateur'];
                            $objCmde = Commande::where('ref','=',$objCommande->ref)->first();

                            if($resultOperateur['name'] == "orange"){

                                $resultTransaction = $result['data']['transaction'];

                                //-----------------------------------------------------------------------------------------------
                                //Mettre à jour la colonne transaction d'une commande
                                //-----------------------------------------------------------------------------------------------

                                try{

                                    $objCmde->update([
                                        'transaction' => $resultTransaction['ref']
                                    ]);

                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 86;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                if($resultTransaction['transaction_status'] == 'SUCCESS') {
                                    //-----------------------------------------------------------------------------------------------
                                    //Statut pay
                                    //-----------------------------------------------------------------------------------------------
                                    $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                    if(empty($objStatutCmde)) {
                                        DB::rollback();
                                        $this->_errorCode = 87;
                                        $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    try {
                                        $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 88;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    //on récupère l'objet statut_produit
                                    $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                    if(empty($objStatut_produit)) {
                                        $this->_errorCode = 89;
                                        $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                    try {
                                        $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 90;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }
                                    $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                    //envoie de sms au producteur
                                    $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                    try{
                                        $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                        $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                    } catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 91;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                            $this->_response['message'] = $objException->getMessage();
                                        }
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }
                                }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                    //-----------------------------------------------------------------------------------------------
                                    //Statut waiting
                                    //-----------------------------------------------------------------------------------------------
                                    $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                    if(empty($objStatutCmde)) {
                                        DB::rollback();
                                        $this->_errorCode = 92;
                                        $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    try {
                                        $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 93;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }


                                }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                    //-----------------------------------------------------------------------------------------------
                                    //Check de paiement Orange
                                    //-----------------------------------------------------------------------------------------------
                                    $postfields = array(
                                        'ref_transaction' => $resultTransaction['ref']
                                    );

                                    try {
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/orange/payment/status/check');
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                        $result = json_decode(curl_exec($ch), true);

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 94;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $resultTransaction = $result['data']['objet'];

                                    if($resultTransaction['transaction_status'] == 'SUCCESS') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut pay
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 95;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 96;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 97;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                        try {
                                            $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 98;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
                                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                        //envoie de sms au producteur
                                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                        try{
                                            $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                        } catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 99;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                                $this->_response['message'] = $objException->getMessage();
                                            }
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 100;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 101;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 102;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 103;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'EXPIRED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 104;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 105;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 106;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }elseif ($resultTransaction['transaction_status'] == 'INITIATED') {

                                    //-----------------------------------------------------------------------------------------------
                                    //Check de paiement Orange
                                    //-----------------------------------------------------------------------------------------------
                                    $postfields = array(
                                        'ref_transaction' => $resultTransaction['ref']
                                    );

                                    try {
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/orange/payment/status/check');
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                        $result = json_decode(curl_exec($ch), true);

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 107;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $resultTransaction = $result['data']['objet'];

                                    if($resultTransaction['transaction_status'] == 'SUCCESS') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut pay
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 108;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 109;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 110;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                        try {
                                            $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 111;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
                                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                        //envoie de sms au producteur
                                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                        try{
                                            $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu.  Connectez-vous a " .$cooll;
                                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                        } catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 112;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                                $this->_response['message'] = $objException->getMessage();
                                            }
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 113;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 114;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 115;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 116;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'INITIATED') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 117;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 118;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'EXPIRED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 119;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 120;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 121;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }elseif ($resultTransaction['transaction_status'] == 'EXPIRED') {
                                    //-----------------------------------------------------------------------------------------------
                                    //Statut waiting
                                    //-----------------------------------------------------------------------------------------------
                                    $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                    if(empty($objStatutCmde)) {
                                        DB::rollback();
                                        $this->_errorCode = 122;
                                        $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    try {
                                        $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 123;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else{
                                    DB::rollback();
                                    $this->_errorCode = 124;
                                    $this->_response['message'][] = "Statut inexistant!.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }elseif($resultOperateur['name'] == "mtn") {

                                $resultTransaction = $result['data']['transaction'];

                                //-----------------------------------------------------------------------------------------------
                                //Mettre à jour la colonne transaction d'une commande
                                //-----------------------------------------------------------------------------------------------

                                try{

                                    $objCmde->update([
                                        'transaction' => $resultTransaction['ref']
                                    ]);

                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 125;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                                    //-----------------------------------------------------------------------------------------------
                                    //Statut pay
                                    //-----------------------------------------------------------------------------------------------
                                    $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                    if(empty($objStatutCmde)) {
                                        DB::rollback();
                                        $this->_errorCode = 126;
                                        $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    try {
                                        $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 127;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    //on récupère l'objet statut_produit
                                    $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                    if(empty($objStatut_produit)) {
                                        $this->_errorCode = 128;
                                        $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $objProduit = Produit::where('commande_id','=',$objCmde->id)->get();

                                    try {
                                        $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 129;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }
                                    $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                    //envoie de sms au producteur
                                    $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                    try{
                                        $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                        $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                    } catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 130;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                            $this->_response['message'] = $objException->getMessage();
                                        }
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }
                                }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                    //-----------------------------------------------------------------------------------------------
                                    //Statut waiting
                                    //-----------------------------------------------------------------------------------------------
                                    $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                    if(empty($objStatutCmde)) {
                                        DB::rollback();
                                        $this->_errorCode = 131;
                                        $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    try {
                                        $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 132;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                    //-----------------------------------------------------------------------------------------------
                                    //Check de paiement Mtn
                                    //-----------------------------------------------------------------------------------------------
                                    $postfields = array(
                                        'ref_transaction' => $resultTransaction['ref']
                                    );

                                    try {
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/mtn/payment/status/check');
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                        $result = json_decode(curl_exec($ch), true);

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 133;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $resultTransaction = $result['data']['objet'];

                                    if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut pay
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 134;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 135;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 136;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $objProduit = Produit::where('commande_id','=',$objCmde->id)->first();

                                        try {
                                            $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 137;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
                                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                                        //envoie de sms au producteur
                                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                        try{
                                            $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. Connectez-vous a " .$cooll;
                                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                        } catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 138;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                                $this->_response['message'] = $objException->getMessage();
                                            }
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 139;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 140;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                        //-----------------------------------------------------------------------------------------------
                                        //Statut waiting
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 141;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 142;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 143;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else{
                                    DB::rollback();
                                    $this->_errorCode = 144;
                                    $this->_response['message'][] = "Statut inexistant!.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }else {
                                DB::rollback();
                                $this->_errorCode = 145;
                                $this->_response['message'][] = "Paramètre de payment manquant.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }else {
                            DB::rollback();
                            $this->_errorCode = 146;
                            $this->_response['message'][] = "Veuillez choisir un quartier!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }else {
                        DB::rollback();
                        $this->_errorCode = 147;
                        $this->_response['message'][] = "Type de livraison inexistant!";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                 }else {
                    DB::rollback();
                    $this->_errorCode = 148;
                    $this->_response['message'][] = "Veuillez choisir un type de livraison!";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                 }

             }else {
                DB::rollback();
                $this->_errorCode = 149;
                $this->_response['message'][] = "Veuillez saisir un numéro de téléphone par lequel le paiement sera effectué!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
             }

         }else {
            DB::rollback();
            $this->_errorCode = 150;
            $this->_response['message'][] = "Veuillez choisir un mode de paiement!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
         }


         // Commit the queries!
         DB::commit();
         $toReturn = [
             'operateur' => $resultOperateur,
             'objet' => $objCmde,
             'statut_cmde' => $objStatutCmde,
             'transaction' => $resultTransaction,
             'sms' => $returnSms
         ];

         $this->_response['message'] = "information sur le paiement d'une commande.";
         $this->_response['data'] = $toReturn;
         $this->_response['success'] = true;
         return response()->json($this->_response);
     }

     //Fonction qui permet de checker le statut de paiement Orange Money d'une commande lié à une proposition acceptée sur un produit
    public function checkCommandeAuctionPayOrange(Request $request)
    {

        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'commande' => 'string|required',
            'transaction' => 'string|required'
        ]);

        //Vérification des paramètres
        if ($validator->fails()) {
            if (!empty($validator->errors()->all())) {
                foreach ($validator->errors()->all() as $error) {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        //Récupération de l 'objet Commande
        $objCommande = Commande::where('ref', '=', $request->get('commande'))->first();
        if(empty($objCommande)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "La commande n'existe pas!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        //Récupération de l 'objet Statut_cmd
        $objStatut_cmd = Statut_cmd::where('id', '=', $objCommande->statut_cmd_id)->first();
        if (empty($objStatut_cmd)) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Le statut commande n'existe pas!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        $message = "";

        DB::beginTransaction();

        $resultTransaction = (object)[];

        if($objStatut_cmd->alias == "waiting") {
            //------------------------------------------------------------------------------------------------------
            //Check de paiement Orange
            //------------------------------------------------------------------------------------------------------
            $postfields = array(
                'ref_transaction' => $request->get('transaction')
            );

            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/orange/payment/status/check');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $result = json_decode(curl_exec($ch), true);

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 5;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $resultTransaction = $result['data']['objet'];

            if($resultTransaction['transaction_status'] == 'INITIATED') {
                $message = "Le paiement est initié!";
            }elseif($resultTransaction['transaction_status'] == 'PENDING') {
                $message = "Le paiement est encours de traitement chez ORANGE.";
            }elseif($resultTransaction['transaction_status'] == 'SUCCESS') {
                //-----------------------------------------------------------------------------------------------
                //Statut pay
                //-----------------------------------------------------------------------------------------------
                $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                if(empty($objStatutCmde)) {
                    DB::rollback();
                    $this->_errorCode = 6;
                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try {
                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 7;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                //on récupère l'objet statut_produit
                $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                if(empty($objStatut_produit)) {
                    $this->_errorCode = 8;
                    $this->_response['message'][] = "Le statut_produit n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $objProduit = Produit::where('commande_id','=',$objCommande->id)->first();

                try {
                    $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 9;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $message = "Succès de paiement!";

            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                //-----------------------------------------------------------------------------------------------
                //Statut waiting
                //-----------------------------------------------------------------------------------------------
                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                if(empty($objStatutCmde)) {
                    DB::rollback();
                    $this->_errorCode = 10;
                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try {
                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 11;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $message = "Echec de paiement!";

            }elseif($resultTransaction['transaction_status'] == 'EXPIRED') {

                $message = "La validité de paiement est expirée!";

            }else{
                DB::rollback();
                $this->_errorCode = 12;
                $this->_response['message'][] = "Statut inexistant!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                $this->_response['success'] = false;
                return response()->json($this->_response);
            }

        }elseif ($objStatut_cmd->alias == "pay") {
            $message = "Succès de paiement!";
        }elseif ($objStatut_cmd->alias == "cancel") {
            $message = "Echec de paiement!";
        }else{
            DB::rollback();
            $this->_errorCode = 13;
            $this->_response['message'][] = "Error Statut commande!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'commande' => $objCommande,
            'transaction' => $resultTransaction
        ];
        $this->_response['message'] = $message;
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Fonction qui permet de checker le statut de paiement Mtn Money d'une commande lié à une proposition acceptée sur un produit
    public function checkCommandeAuctionPayMtn(Request $request)
    {

        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'commande' => 'string|required',
            'transaction' => 'string|required'
        ]);

        //Vérification des paramètres
        if ($validator->fails()) {
            if (!empty($validator->errors()->all())) {
                foreach ($validator->errors()->all() as $error) {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        //Récupération de l 'objet Commande
        $objCommande = Commande::where('ref', '=', $request->get('commande'))->first();
        if(empty($objCommande)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "La commande n'existe pas!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        //Récupération de l 'objet Statut_cmd
        $objStatut_cmd = Statut_cmd::where('id', '=', $objCommande->statut_cmd_id)->first();
        if (empty($objStatut_cmd)) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Le statut commande n'existe pas!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        $message = "";

        DB::beginTransaction();

        $resultTransaction = (object)[];

        if($objStatut_cmd->alias == "waiting") {
            //-----------------------------------------------------------------------------------------------
            //Check de paiement Mtn
            //-----------------------------------------------------------------------------------------------
            $postfields = array(
                'ref_transaction' => $request->get('transaction')
            );

            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://taspay.team-solutions.net/api/api/mtn/payment/status/check');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                $result = json_decode(curl_exec($ch), true);

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 5;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $resultTransaction = $result['data']['objet'];

            if($resultTransaction['transaction_status'] == 'PENDING') {

                $message = "Le paiement est encours de traitement chez MTN.";

            }elseif($resultTransaction['transaction_status'] == 'SUCCESS') {
                //-----------------------------------------------------------------------------------------------
                //Statut pay
                //-----------------------------------------------------------------------------------------------
                $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                if(empty($objStatutCmde)) {
                    DB::rollback();
                    $this->_errorCode = 6;
                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try {
                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 7;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                //on récupère l'objet statut_produit
                $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                if(empty($objStatut_produit)) {
                    $this->_errorCode = 8;
                    $this->_response['message'][] = "Le statut_produit n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $objProduit = Produit::where('commande_id','=',$objCommande->id)->first();

                try {
                    $objProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 9;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
                $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                //envoie de sms au producteur
                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                try{
                    $msg = "Date du ".$objCommande->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$objProduit->designation. " a été vendu. connecter vous a " .$cooll;
                    $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                } catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 14;
                    if (in_array($this->_env, ['local', 'development'])) {
                        $this->_response['message'] = $objException->getMessage();
                    }
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $message = "Succès de paiement!";

            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                //-----------------------------------------------------------------------------------------------
                //Statut waiting
                //-----------------------------------------------------------------------------------------------
                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                if(empty($objStatutCmde)) {
                    DB::rollback();
                    $this->_errorCode = 10;
                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try {
                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 11;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $message = "Echec de paiement!";

            }else{
                DB::rollback();
                $this->_errorCode = 12;
                $this->_response['message'][] = "Statut inexistant!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                $this->_response['success'] = false;
                return response()->json($this->_response);
            }

        }elseif($objStatut_cmd->alias == "pay") {
            $message = "Succès de paiement!";
        }elseif($objStatut_cmd->alias == "cancel") {
            $message = "Echec de paiement!";
        }else{
            DB::rollback();
            $this->_errorCode = 13;
            $this->_response['message'][] = "Error Statut commande!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['success'] = false;
            return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'commande' => $objCommande,
            'transaction' => $resultTransaction,
            'sms'=>$returnSms
        ];
        $this->_response['message'] = $message;
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

     // liste des commandes par rapport aux enchères d'un client : customer/auctions/orders/list
    public function orderAuctionOfCustomer(Request $request)
    {
        $this->_fnErrorCode = 1;

        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode               = 2;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = 'utilisateur non connecté';
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien client
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if(!in_array($objRole->alias, array("client"))) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        DB::beginTransaction();
        try{
            //liste des commandes par rapport aux enchères
            $objOrderAuction = DB::table('propositions')
            ->join('produits', 'produits.id', '=', 'propositions.produit_id')
            ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
            ->join('users', 'users.id', '=', 'propositions.user_client_id')
            ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
            ->LeftJoin('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
            ->leftJoin('statut_livraisons','statut_livraisons.id', '=',  'commandes.statut_livraison_id')
           /*  ->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
            ->leftJoin('villes', 'villes.id', '=', 'quartiers.ville_id')*/
            ->leftJoin('surface_partages', 'surface_partages.id', '=', 'commandes.surface_partage_id')
            /*->join('quartier_surfaces', 'quartier_surfaces.id', '=', 'surface_partages.quartier_surface_id')
            ->join('ville_surfaces', 'ville_surfaces.id', '=', 'quartier_surfaces.ville_surface_id')*/
            ->select('propositions.ref as ref_proposition',
                'propositions.created_at as date_post_proposition',
                'propositions.prix_proposition as prix_proposition',
                'statut_propositions.ref as ref_statut_proposition',
                'statut_propositions.name as name_statut_proposition',
                'produits.ref as ref_produit',
                'produits.designation as designation',
                'produits.qte as qte_produit',
                'produits.description as description',
                'produits.published as published_produit',
                'produits.type_vente as type_vente_produit',
                'commandes.ref as ref_commande',
                'commandes.mode_payment as mode_payment',
                'commandes.montant as montant_commande',
                'commandes.paie_phone as paie_phone',
                'statut_livraisons.name as statut_livraison',
                'type_livraisons.ref as ref_type_livraison',
                'type_livraisons.name as name_type_livraison',
               /*  'quartiers.ref as ref_quartier',
                'quartiers.name as name_quartier',
                'villes.name as name_ville', */
                'statut_cmds.ref as ref_statut_cmd',
                'statut_cmds.name as name_statut_cmd',
                'surface_partages.ref as ref_surface_partage',
                'surface_partages.name as name_surface_partage',
                'surface_partages.longitude as longitude_surface_partage',
                'surface_partages.latitude as latitude_surface_partage'
                /*,
                'quartier_surfaces.ref as ref_quartier_surface',
                'quartier_surfaces.name as name_quartier_surface',
                'ville_surfaces.id as id_ville_surface',
                'ville_surfaces.name as name_ville_surface'*/)
            ->where('propositions.published', '=', 1)
            ->where('propositions.statut_proposition_id', '=', 2)
            ->where('propositions.user_client_id', '=', $objUser->id)
            ->get();

        }catch (Exception $objException){
            DB::rollback();
            $this->_errorCode               = 4;
            if(in_array($this->_env, ['local', 'development']))
            {
            }
            $this->_response['message']     = $objException->getMessage();
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }
        $returnSms = (object)[];
        foreach($objOrderAuction as $objOrder){

            $objProposition = Proposition::where('ref','=',$objOrder->ref_proposition)->first();
            if($objProposition->statut_proposition_id == 2) {

                $commande = Commande::where('ref','=',$objOrder->ref_commande)->first();
                if($commande->statut_cmd_id == 1) {

                    $objProduit = Produit::where('commande_id','=',$commande->id)->first();

                    date_default_timezone_set('Africa/Douala');
                    $date_now = date('Y-m-d H:i:s', time());

                    $newtimestamp = strtotime($date_now);
                    # Date actuelle
                    $courant_date = date('Y-m-d', $newtimestamp);
                    # heure-minute-seconde actuelle
                    $time_now = date('H:i:s', $newtimestamp);
                    $tab = explode(":", $time_now);
                    $h = $tab[0];
                    $m = $tab[1];
                    $s = $tab[2];
                    $courant_date_en_seconde = $h*3600+$m*60+$s;

                    $endate = $objProduit->end_date;

                    $endtimestamp = strtotime($endate);
                    $endate = date('Y-m-d', $endtimestamp);
                    $endtime = date('H:i:s', $endtimestamp);
                    $tab2 = explode(":", $endtime);
                    $h2 = $tab2[0];
                    $m2 = $tab2[1];
                    $s2 = $tab2[2];

                    /*$endate + 30 minutes = 1800s : DateLine permattant de retirer
                    * un produit à une commande non payer,
                    * faire passer ce produit à un autre type de vente et supprimer la commande.
                    */
                    $end_date_en_seconde = $h2*3600+$m2*60+$s2+1800;

                    /* 10 minutes = 600s : permet d'envoyer une notification au client
                    * lorsque l'heure actuelle est à moins de 10 minutes de $endate + 30minutes.
                    */
                    $diff_seconde = $end_date_en_seconde - $courant_date_en_seconde;

                    if (($endate == $courant_date) && (600 <= $diff_seconde && $diff_seconde < $end_date_en_seconde)) {
                        # Notification du client que le produit gagné va être remis sur le site
                        //Insérer la requête de notification envoyée au client
                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                        try{
                            $msg = "Le produit ".$objProduit->designation.", que vous avez remporté au enchere vous sera enlevé d'ici 20 minutes si vous n'effectuer pas le paiement. Connectez-vous a " .$cooll;
                            $returnSms = $objUser->sms($msg, $objUser->phone);
                            //dd($returnSms);
                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 5;
                            if (in_array($this->_env, ['local', 'development'])) {
                                $this->_response['message'] = $objException->getMessage();
                            }
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }

                    if(($endate <= $courant_date) && ($end_date_en_seconde - $courant_date_en_seconde) <= 0) {

                        # Changer le statut du produit, Enlever la commande dans le produit et supprimer la commande
                        # Le produit passe à la vente directe
                        try {
                            $objProduit->update([
                                'published' => 1,
                                'type_vente'=>'vente directe',
                                'prix_produit'=>$objProduit->prix_min_enchere,
                                'prix_min_enchere'=>NULL,
                                'delai'=>NULL,
                                'begin_date'=>NULL,
                                'end_date'=>NULL,
                                'created_at'=> $date_now,
                                'updated_at'=> $date_now
                            ]);
                        }catch (Exception $objException){
                            DB::rollback();
                            $this->_errorCode = 6;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objProduit->update(['commande_id' => Null]);
                        }catch (Exception $objException){
                            DB::rollback();
                            $this->_errorCode = 7;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }


                        try {
                            DB::table('commandes')->where('id', $commande->id)->delete();
                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 8;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        # Notification du Producteur que son produit est mis en vente directe
                        //Insérer la notification au producteur
                        $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                        //envoie de sms au producteur
                        $cooll = "https://draht.team-solutions.net/#/dashboards/";
                        try{
                            $msg = "Le produit ".$objProduit->designation.", sera mis en vente directe car le client n'a pas payé la commande. Connectez-vous a " .$cooll. "pour le remettre au enchere si vous le voulez.";
                            $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                            //dd($returnSms);
                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 9;
                            if (in_array($this->_env, ['local', 'development'])) {
                                $this->_response['message'] = $objException->getMessage();
                            }
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }

                }
            }

        }

        DB::commit();
        $toReturn = [
            'commande' => $objOrderAuction,
            'sms'=>$returnSms
        ];
        $this->_response['message']    = "Liste des commandes par rapport aux enchères.";
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /* Admin|Gestionnaire-surface : voir les commandes par rapport aux enchères
    * acceptées concernant les clients
    */

    public function allOrdersAuctions(Request $request)
    {
        $this->_fnErrorCode = 1;

        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode               = 2;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = 'utilisateur non connecté';
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        DB::beginTransaction();
        //On vérifie que l'utilisateur est bien admin
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if(in_array($objRole->alias, array("administrateur"))) {
            try
            {
                $objOrderAuction = DB::table('propositions')
                ->join('produits', 'produits.id', '=', 'propositions.produit_id')
                ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
                ->join('users', 'users.id', '=', 'propositions.user_client_id')
                ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                ->join('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
                ->leftJoin('surface_partages', 'surface_partages.id', '=', 'commandes.surface_partage_id')
                ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
                ->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
                ->leftJoin('villes', 'villes.id', '=', 'quartiers.ville_id')
                ->select('propositions.ref as ref_proposition',
                    'propositions.created_at as date_post_proposition',
                    'propositions.prix_proposition as prix_proposition',
                    'statut_propositions.ref as ref_statut_proposition',
                    'statut_propositions.name as name_statut_proposition',
                    'produits.ref as ref_produit',
                    'produits.designation as designation',
                    'produits.qte as qte_produit',
                    'produits.description as description',
                    'produits.published as published_produit',
                    'produits.type_vente as type_vente_produit',
                    'commandes.ref as ref_commande',
                    'commandes.mode_payment as mode_payment',
                    'commandes.montant as montant_commande',
                    'commandes.paie_phone as paie_phone',
                    'surface_partages.ref as ref_surface_partage',
                    'surface_partages.name as name_surface_partage',
                    'type_livraisons.ref as ref_type_livraison',
                    'type_livraisons.name as name_type_livraison',
                    'quartiers.ref as ref_quartier',
                    'quartiers.name as name_quartier',
                    'villes.name as name_ville',
                    'statut_cmds.ref as ref_statut_cmd',
                    'statut_cmds.name as name_statut_cmd')
                ->where('propositions.published', '=', 1)
                ->where('propositions.statut_proposition_id', '=', 2)
                ->get();

            }catch (Exception $objException){
                DB::rollback();
                $this->_errorCode               = 3;
                if(in_array($this->_env, ['local', 'development']))
                {
                }
                $this->_response['message']     = $objException->getMessage();
                $this->_response['error_code']  = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

            foreach($objOrderAuction as $objOrder){

                $objProposition = Proposition::where('ref','=',$objOrder->ref_proposition)->first();
                if($objProposition->statut_proposition_id == 2) {

                    $commande = Commande::where('ref','=',$objOrder->ref_commande)->first();
                    if($commande->statut_cmd_id == 1) {

                        $objProduit = Produit::where('commande_id','=',$commande->id)->first();

                        date_default_timezone_set('Africa/Douala');
                        $date_now = date('Y-m-d H:i:s', time());

                        $newtimestamp = strtotime($date_now);
                        # Date actuelle
                        $courant_date = date('Y-m-d', $newtimestamp);
                        # heure-minute-seconde actuelle
                        $time_now = date('H:i:s', $newtimestamp);
                        $tab = explode(":", $time_now);
                        $h = $tab[0];
                        $m = $tab[1];
                        $s = $tab[2];
                        $courant_date_en_seconde = $h*3600+$m*60+$s;

                        $endate = $objProduit->end_date;

                        $endtimestamp = strtotime($endate);
                        $endate = date('Y-m-d', $endtimestamp);
                        $endtime = date('H:i:s', $endtimestamp);
                        $tab2 = explode(":", $endtime);
                        $h2 = $tab2[0];
                        $m2 = $tab2[1];
                        $s2 = $tab2[2];

                        /*$endate + 30 minutes = 1800s : DateLine permattant de retirer
                        * un produit à une commande non payer,
                        * faire passer ce produit à un autre type de vente et supprimer la commande.
                        */
                        $end_date_en_seconde = $h2*3600+$m2*60+$s2+1800;

                        /* 10 minutes = 600s : permet d'envoyer une notification au client
                        * lorsque l'heure actuelle est à moins de 10minutes de $endate + 30minutes.
                        */
                        $diff_seconde = $end_date_en_seconde - $courant_date_en_seconde;

                        if (($endate == $courant_date) && (600 <= $diff_seconde && $diff_seconde < $end_date_en_seconde)) {
                            # Notification du client que le produit gagné va être remis sur le site
                            //Insérer la requête de notification envoyée au client
                            //envoie de sms au producteur
                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                            try{
                                $msg = "Le produit ".$objProduit->designation.", que vous avez remporté au enchere vous sera enlevé d'ici 20 minutes si vous n'effectuez pas le paiement. Connectez-vous a " .$cooll;
                                $returnSms = $objUser->sms($msg, $objUser->phone);
                                //dd($returnSms);
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 4;
                                if (in_array($this->_env, ['local', 'development'])) {
                                    $this->_response['message'] = $objException->getMessage();
                                }
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }

                        if(($endate <= $courant_date) && ($end_date_en_seconde - $courant_date_en_seconde) < 0) {

                            # Changer le statut du produit, Enlever la commande dans le produit et supprimer la commande
                            # Le produit passe à la vente directe
                            try {
                                $objProduit->update([
                                    'published' => 1,
                                    'type_vente'=>'vente directe',
                                    'prix_produit'=>$objProduit->prix_min_enchere,
                                    'prix_min_enchere'=>NULL,
                                    'delai'=>NULL,
                                    'begin_date'=>NULL,
                                    'end_date'=>NULL,
                                    'created_at'=> $date_now,
                                    'updated_at'=> $date_now
                                ]);
                            }catch (Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 5;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $objProduit->update(['commande_id' => Null]);
                            }catch (Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 6;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }


                            try {
                                DB::table('commandes')->where('id', $commande->id)->delete();
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 7;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            # Notification du Producteur que son produit est mis en vente directe
                            //Insérer la notification au producteur
                            $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                            //envoie de sms au producteur
                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                            try{
                                $msg = "Le produit ".$objProduit->designation.", n'a pas été payé. Par consequent, il est automatiquement passé en vente directe. Connectez-vous a " .$cooll. "pour le remettre au enchére si vous le voulez.";
                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                //dd($returnSms);
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 8;
                                if (in_array($this->_env, ['local', 'development'])) {
                                    $this->_response['message'] = $objException->getMessage();
                                }
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }

                    }
                }

            }

		}elseif (in_array($objRole->alias, array("gestionnaire-surface"))) {
            $objSurface = Surface_partage::where('id','=',$objUser->surface_partage_id)->first();
            try
            {
                $objOrderAuction = DB::table('propositions')
                ->join('produits', 'produits.id', '=', 'propositions.produit_id')
                ->join('statut_propositions', 'statut_propositions.id', '=', 'propositions.statut_proposition_id')
                ->join('users', 'users.id', '=', 'propositions.user_client_id')
                ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                ->leftJoin('surface_partages', 'surface_partages.id', '=', 'commandes.surface_partage_id')
                ->join('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
                ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
                ->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
                ->leftJoin('villes', 'villes.id', '=', 'quartiers.ville_id')
                ->select('propositions.ref as ref_proposition',
                    'propositions.created_at as date_post_proposition',
                    'propositions.prix_proposition as prix_proposition',
                    'statut_propositions.ref as ref_statut_proposition',
                    'statut_propositions.name as name_statut_proposition',
                    'produits.ref as ref_produit',
                    'produits.designation as designation',
                    'produits.qte as qte_produit',
                    'produits.description as description',
                    'produits.published as published_produit',
                    'produits.type_vente as type_vente_produit',
                    'commandes.ref as ref_commande',
                    'commandes.mode_payment as mode_payment',
                    'commandes.montant as montant_commande',
                    'commandes.paie_phone as paie_phone',
                    'type_livraisons.ref as ref_type_livraison',
                    'type_livraisons.name as name_type_livraison',
                    'quartiers.ref as ref_quartier',
                    'quartiers.name as name_quartier',
                    'villes.name as name_ville',
                    'statut_cmds.ref as ref_statut_cmd',
                    'statut_cmds.name as name_statut_cmd',
                    'surface_partages.ref as ref_surface_partage',
                    'surface_partages.name as name_surface_partage')
                ->where('propositions.published', '=', 1)
                ->where('propositions.statut_proposition_id', '=', 2)
                ->where('commandes.surface_partage_id', '=', $objSurface->id)
                ->get();

            }catch (Exception $objException){
                DB::rollback();
                $this->_errorCode               = 9;
                if(in_array($this->_env, ['local', 'development']))
                {
                }
                $this->_response['message']     = $objException->getMessage();
                $this->_response['error_code']  = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

            foreach($objOrderAuction as $objOrder){

                $objProposition = Proposition::where('ref','=',$objOrder->ref_proposition)->first();
                if($objProposition->statut_proposition_id == 2) {

                    $commande = Commande::where('ref','=',$objOrder->ref_commande)->first();
                    if($commande->statut_cmd_id == 1) {

                        $objProduit = Produit::where('commande_id','=',$commande->id)->first();

                        date_default_timezone_set('Africa/Douala');
                        $date_now = date('Y-m-d H:i:s', time());

                        $newtimestamp = strtotime($date_now);
                        # Date actuelle
                        $courant_date = date('Y-m-d', $newtimestamp);
                        # heure-minute-seconde actuelle
                        $time_now = date('H:i:s', $newtimestamp);
                        $tab = explode(":", $time_now);
                        $h = $tab[0];
                        $m = $tab[1];
                        $s = $tab[2];
                        $courant_date_en_seconde = $h*3600+$m*60+$s;

                        $endate = $objProduit->end_date;

                        $endtimestamp = strtotime($endate);
                        $endate = date('Y-m-d', $endtimestamp);
                        $endtime = date('H:i:s', $endtimestamp);
                        $tab2 = explode(":", $endtime);
                        $h2 = $tab2[0];
                        $m2 = $tab2[1];
                        $s2 = $tab2[2];

                        /*$endate + 30 minutes = 1800s : DateLine permattant de retirer
                        * un produit à une commande non payer,
                        * faire passer ce produit à un autre type de vente et supprimer la commande.
                        */
                        $end_date_en_seconde = $h2*3600+$m2*60+$s2+1800;

                        /* 10 minutes = 600s : permet d'envoyer une notification au client
                        * lorsque l'heure actuelle est à moins de 10minutes de $endate + 30minutes.
                        */
                        $diff_seconde = $end_date_en_seconde - $courant_date_en_seconde;

                        if (($endate == $courant_date) && (600 <= $diff_seconde && $diff_seconde < $end_date_en_seconde)) {
                            # Notification du client que le produit gagné va être remis sur le site
                            //Insérer la requête de notification envoyée au client
                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                            try{
                                $msg = "Le produit ".$objProduit->designation.", que vous avez remporté au enchere vous sera enleveé d'ici 20 minutes si vous n'effectuez pas le paiement. Connectez-vous a " .$cooll;
                                $returnSms = $objUser->sms($msg, $objUser->phone);
                                //dd($returnSms);
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 10;
                                if (in_array($this->_env, ['local', 'development'])) {
                                    $this->_response['message'] = $objException->getMessage();
                                }
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }

                        if(($endate <= $courant_date) && ($end_date_en_seconde - $courant_date_en_seconde) < 0) {

                            # Changer le statut du produit, Enlever la commande dans le produit et supprimer la commande
                            # Le produit passe à la vente directe
                            try {
                                $objProduit->update([
                                    'published' => 1,
                                    'type_vente'=>'vente directe',
                                    'prix_produit'=>$objProduit->prix_min_enchere,
                                    'prix_min_enchere'=>NULL,
                                    'delai'=>NULL,
                                    'begin_date'=>NULL,
                                    'end_date'=>NULL,
                                    'created_at'=> $date_now,
                                    'updated_at'=> $date_now
                                ]);
                            }catch (Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 11;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $objProduit->update(['commande_id' => Null]);
                            }catch (Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 12;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }


                            try {
                                DB::table('commandes')->where('id', $commande->id)->delete();
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 13;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            # Notification du Producteur que son produit est mis en vente directe
                            //Insérer la notification au producteur
                            $userProducteur = User::where('id','=',$objProduit->user_prod_id)->first();
                            //envoie de sms au producteur
                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                            try{
                                $msg = "Le produit ".$objProduit->designation.", n'a pas été payé. Par consequent, il est automatiquement passé en vente directe. Connectez-vous a " .$cooll. "pour le remettre au enchere si vous le voulez.";
                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                //dd($returnSms);
                            } catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 14;
                                if (in_array($this->_env, ['local', 'development'])) {
                                    $this->_response['message'] = $objException->getMessage();
                                }
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }

                    }
                }

            }

        }else {
            $this->_errorCode = 15;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'commande'=> $objOrderAuction,
            'sms'=>$returnSms
        ];
        $this->_response['message']    = "Liste des commandes par rapport aux enchères acceptées concernant aux clients.";
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function generateQRCode($ref){
        $image = QrCode::size(100)
        ->generate('https://draht.team-solutions.net/#/detail_commande/'.$ref);

        return $image;
    }

}

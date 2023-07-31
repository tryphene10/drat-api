<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use App\Role;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Type_livraison;
use App\Commande;
use App\Bon_livraison;
use App\Statut_livraison;
use App\Statut_bon_livraison;
use App\Quartier_surface;
use App\Surface_partage;

class BonLivraisonApiController extends Controller
{
    public function create(Request $request)
    {
        $this->_fnErrorCode = 1;
        //On vérifie que la liste des commandes est bien envoyée !
        $objListCommande = collect(json_decode($request->getContent(), true));
        if (empty($objListCommande)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "La liste des commandes est vide!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUser = Auth::user();
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }

            $this->_errorCode = 3;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien gestionnaire du centre
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias, array("gestionnaire-surface"))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objStatut_bon_livraison = Statut_bon_livraison::where('alias', '=', 'waiting')->first();
        if (empty($objStatut_bon_livraison)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le statut bon de livraisons n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        $returnSms = (object)[];
        if($objListCommande->has('type_livraison')) {
            $objType_livraison = Type_livraison::where('ref', '=', $objListCommande["type_livraison"])->first();
            if (empty($objType_livraison)) {
                DB::rollback();
                $this->_errorCode = 6;
                $this->_response['message'][] = "Le livreur demandé n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            //2:point distribution drat
            if ($objType_livraison->id == 2) {

                try{
                    //Création de Bon_livraisons
                    $objBonLivraison = new Bon_livraison();
                    $objBonLivraison->published = 1;
                    $objBonLivraison->generateReference();
                    $objBonLivraison->generateAlias("bon_livraison: ".$objBonLivraison->id);
                    $objBonLivraison->userGsp()->associate($objUser);
                    $objBonLivraison->statutBonLivraison()->associate($objStatut_bon_livraison);
                    $objBonLivraison->save();

                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 7;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                foreach ($objListCommande['commandes'] as $item) {

                    if ($item['commande'] != "") {
                        try {
                            $objCommande = Commande::where('ref','=',$item['commande'])->first();
                            if (empty($objCommande)) {
                                DB::rollback();
                                $this->_errorCode = 8;
                                $this->_response['message'][] = "La commande n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 9;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        if ($objCommande->statut_cmd_id == 2) {

                            if ($objCommande->bon_livraison_id == null) {

                                if ($objCommande->bon_commande_id != null) {

                                    try {
                                        //Update de la commande
                                        $objCommande->update(['bon_livraison_id' => $objBonLivraison->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 10;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    $objCustomer = User::where('id','=',$objCommande->user_client_id)->first();

                                    $objSurface = Surface_partage::where('id','=',$objCommande->surface_partage_id)->first();

                                    //$objQuartier_surface = Quartier_surface::where('id','=',$objSurface->quartier_surface_id)->first();

                                    try{
                                        $msg = "Mr/Mme, ".$objCustomer->name." ".$objCustomer->surname." DRAT vous invite à passer prendre vos commandes au lieudit ".$objSurface->name;
                                        $returnSms = $objCustomer->sms($msg, $objCustomer->phone);
                                    } catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 11;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                            $this->_response['message'] = $objException->getMessage();
                                        }
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }


                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 12;
                                    $this->_response['message'][] = 'Cette commande ne peut faire parti d\'une livraison.';
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }else {
                                DB::rollback();
                                $this->_errorCode = 13;
                                $this->_response['message'][] = 'Cette commande a déjà été utilisée.';
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }else {
                            DB::rollback();
                            $this->_errorCode = 14;
                            $this->_response['message'][] = 'Cette commande n\'est pas payée.';
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }else {
                        DB::rollback();
                        $this->_errorCode = 15;
                        $this->_response['message'][] = 'Ce paramètre n\'a pas de commande.';
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }

            }

            //A domicile
            if($objType_livraison->id == 1) {

                if($objListCommande->has('livreur')) {
                    $objLivreur = User::where('ref', '=', $objListCommande["livreur"])->first();
                    if (empty($objLivreur)) {
                        DB::rollback();
                        $this->_errorCode = 16;
                        $this->_response['message'][] = "Le livreur demandé n'existe pas.";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    try{
                        //Création de Bon_livraisons
                        $objBonLivraison = new Bon_livraison();
                        $objBonLivraison->published = 1;
                        $objBonLivraison->generateReference();
                        $objBonLivraison->generateAlias("bon_livraison: ".$objBonLivraison->id);
                        $objBonLivraison->userLivreur()->associate($objLivreur);
                        $objBonLivraison->userGsp()->associate($objUser);
                        $objBonLivraison->statutBonLivraison()->associate($objStatut_bon_livraison);
                        $objBonLivraison->save();

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 17;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    foreach ($objListCommande['commandes'] as $item) {

                        if ($item['commande'] != "") {
                            try {
                                $objCommande = Commande::where('ref','=',$item['commande'])->first();
                                if (empty($objCommande)) {
                                    DB::rollback();
                                    $this->_errorCode = 18;
                                    $this->_response['message'][] = "La commande n'existe pas.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 19;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            if ($objCommande->statut_cmd_id == 2) {

                                if ($objCommande->bon_livraison_id == null) {

                                    if ($objCommande->bon_commande_id != null) {

                                        try {
                                            //Update de la commande
                                            $objCommande->update(['bon_livraison_id' => $objBonLivraison->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 20;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else {
                                        DB::rollback();
                                        $this->_errorCode = 21;
                                        $this->_response['message'][] = 'Cette commande ne peut faire parti d\'une livraison.';
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 22;
                                    $this->_response['message'][] = 'Cette commande a déjà été utilisée.';
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }else {
                                DB::rollback();
                                $this->_errorCode = 23;
                                $this->_response['message'][] = 'Cette commande n\'est pas payée.';
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }else {
                            DB::rollback();
                            $this->_errorCode = 24;
                            $this->_response['message'][] = 'Ce paramètre n\'a pas de commande.';
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }

                    $cooll = "https://draht.team-solutions.net/#/dashboards/";
                    try{
                        $msg = "Date du ".$objBonLivraison->created_at.", DRAT : ".$objLivreur->name." ".$objLivreur->surname.", as été assigné à des livraisons, clique sur ce lien pour les voir ".$cooll;
                        $returnSms = $objLivreur->sms($msg, $objLivreur->phone);
                    } catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 25;
                        if (in_array($this->_env, ['local', 'development'])) {
                            $this->_response['message'] = $objException->getMessage();
                        }
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }else {
                    DB::rollback();
                    $this->_errorCode = 26;
                    $this->_response['message'][] = "Veuillez choisir un livreur.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }

        }else {
            DB::rollback();
            $this->_errorCode = 27;
            $this->_response['message'][] = "Veuillez entrer un type de livraison.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        // Commit the queries!
        DB::commit();

        $toReturn = [
            'sms'=> $returnSms,
            "objet" => $objBonLivraison
        ];

        $this->_response['message'] = "Enregistrement reussi!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function cancel(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'bon_livraison'=>'string|required'
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
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }

            $this->_errorCode = 3;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien gestionnaire-surface
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if ($objRole->alias != "gestionnaire-surface") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objBon_livraison = Bon_livraison::where('ref', '=', $request->get("bon_livraison"))->first();
        if(empty($objBon_livraison)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le bon de livraison n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        $objCommandes = Commande::where('bon_livraison_id','=',$objBon_livraison->id)->get();
        if($objCommandes->isNotEmpty()) {

            foreach ($objCommandes as $objCommande) {

                try {
                    $objCommande->update(["bon_livraison_id" => Null]);
                }catch(Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 6;
                    if (in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }

            }

            try{

                DB::table('bon_livraisons')
                ->where('bon_livraisons.id', $objBon_livraison->id)
                ->delete();
            }
            catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 7;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }else{
            DB::rollback();
            $this->_errorCode = 8;
            $this->_response['message'][] = "Le bon de livraison n'est pas lié à des commandes.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();

        $toReturn = [];
        $this->_response['message'] = 'Ce bon de livraison a été annulé.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function allLivraisonsOfLivreur()
	{
		$this->_fnErrorCode = "01";
        $objUser = Auth::user();
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }

            $this->_errorCode = 2;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien livreur
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if ($objRole->alias != "livreur") {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

		try {
            //Récupération liste bon_livraisons
			$objBon_livraisons = DB::table('bon_livraisons')
			->join('users','users.id','=','bon_livraisons.user_livreur_id')
			->join('statut_bon_livraisons','statut_bon_livraisons.id','=','bon_livraisons.statut_bon_livraison_id')
            ->select('bon_livraisons.id as id_bon_livraison',
                'bon_livraisons.created_at as date_creation',
                'bon_livraisons.ref as ref_bon_livraison',
                'bon_livraisons.signature_bon_livraison	 as signature_bon_livraison',
                'statut_bon_livraisons.ref as ref_statut_bon_livraison',
                'statut_bon_livraisons.name as name_statut_bon_livraison')
			->where('bon_livraisons.published','=',1)
			->where('bon_livraisons.user_livreur_id','=',$objUser->id)
			->orderBy('bon_livraisons.id','desc')
			->get();

		}catch (Exception $objException) {
			$this->_errorCode = 4;
			if(in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

        $listBonLivraison = collect();
        foreach($objBon_livraisons as $objBon_livraison) {

            try {

                //Récupération de toutes les commandes du bon de livraison
                $objAllCommandes = DB::table('commandes')
                ->join('users', 'users.id', '=', 'commandes.user_client_id')
                ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
                ->join('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
                ->join('bon_livraisons', 'bon_livraisons.id', '=', 'commandes.bon_livraison_id')
                //->join('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
                ->join('statut_livraisons', 'statut_livraisons.id', '=', 'commandes.statut_livraison_id')
                ->select('commandes.ref as ref_commande',
                    'commandes.mode_payment as mode_payment',
                    'commandes.paie_phone as user_phone',
                    'commandes.montant as montant',
                    'commandes.id as id_commande',
                    'commandes.cni as num_cni',
                    'commandes.signature as signature',
                    'commandes.lieu_livraison as lieu_livraison',
                    'users.ref as user_ref',
                    'users.name as user_name',
                    'users.surname as user_surname',
                    'users.phone as user_phone',
                    'users.email as user_email',
                    'statut_cmds.ref as ref_statut_cmd',
                    'statut_cmds.name as name_statut_cmd',
                    'statut_livraisons.ref as ref_statut_livraison',
                    'statut_livraisons.name as name_statut_livraison')
                ->where('commandes.published', 1)
                ->where('commandes.type_livraison_id', 1)
                ->where('commandes.bon_livraison_id', $objBon_livraison->id_bon_livraison)
                ->orderBy('commandes.id', 'desc')
                ->get();

            } catch (Exception $objException) {
                $this->_errorCode = 5;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $listBonLivraison->push(array(
                'bon_livraison' => $objBon_livraison,
                'commandes' => $objAllCommandes
            ));

        }

		$toReturn = [
			'objet' => $listBonLivraison
		];
		$this->_response['message'] = 'Liste des livraisons d\'un livreur';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    //Fonction qui permet d'enregistrer la signature d'un livreur sur un bon de livraison
    public function signOfLivreur(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'bon_livraison'=>'string|required',
            'signature'=>'string|required'
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
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }

            $this->_errorCode = 3;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien habilité
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias, array("livreur"))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objBon_livraison = Bon_livraison::where('ref', '=', $request->get("bon_livraison"))->first();
        if(empty($objBon_livraison)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le Bon_livraison n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();
        if($objBon_livraison->user_livreur_id != Null) {
            //----------------------------------------------------------------------------
            //Signature du livreur sur un bon de livraison
            //----------------------------------------------------------------------------
            $image = $request->get('signature');  // your base64 encoded
            $extension = explode('/', mime_content_type($request->get('signature')))[1];

            $image = str_replace('data:image/'.$extension.';base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = $objBon_livraison->ref.'_'.str_random(10) . '.'.$extension;

            if(Storage::disk('livreur')->put($imageName, base64_decode($image))){

                try{
                    $objBon_livraison->update(["signature_bon_livraison" => 'api/drat-api/storage/app/public/livreur/signature/'.$imageName]);
                }catch(Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 6;
                    if (in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }

                $objAllCommandes = Commande::where('bon_livraison_id','=',$objBon_livraison->id)->get();

                foreach( $objAllCommandes as $commande){
                    $objUserClient = User::where('id', '=', $commande->user_client_id)->first();
                    //envoie de sms au client
                    $cooll = "https://draht.team-solutions.net/#/dashboards/";
                    try{
                        $msg = "Date du ".$commande->created_at.", DRAT : ".$objUserClient->name." ".$objUserClient->surname.", votre commande vous seras livrée dans moins de 24h.";
                        $returnSms = $objUserClient->sms($msg, $objUserClient->phone);
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

            }else{
                DB::rollback();
                $this->_errorCode = 7;
                if (in_array($this->_env, ['local', 'development'])){
                    $this->_response['message'] ="La livraison à échoué...";
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }else {
            DB::rollback();
            $this->_errorCode = 8;
            $this->_response['message'][] = "Aucun livreur n'existe dans ce bon de commande.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();

        $toReturn = [
            'objet'=> $objBon_livraison,
            'sms'=> $objUserClient
        ];
        $this->_response['message'] = 'Acceptation du Livreur pour la livraison.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Fonction qui permet d'enregistrer la signature d'un client sur une reception de sa commande
    public function customerOrderSign(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'commande'=>'string|required',
            'signature'=>'string|required',
            'cni_img'=>'string|required'
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
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }

            $this->_errorCode = 3;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien habilité
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias, array("livreur","gestionnaire-surface"))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objCommande = Commande::where('ref', '=', $request->get("commande"))->first();
        if(empty($objCommande)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "La commande n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objBon_livraison = Bon_livraison::where('id', '=', $objCommande->bon_livraison_id)->first();
        if(empty($objBon_livraison)) {
            $this->_errorCode = 6;
            $this->_response['message'][] = "Le bon de livraison n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        DB::beginTransaction();
        if($objCommande->statut_livraison_id == 1){//1:waiting

            //-----------------------------------------------------------------------------------------------
            //Statut_livraison : delivered
            //-----------------------------------------------------------------------------------------------
            $objStatut_livraison = Statut_livraison::where('alias', '=', 'delivered')->first();
            if(empty($objStatut_livraison)) {
                DB::rollback();
                $this->_errorCode = 7;
                $this->_response['message'][] = "Le statut_livraison de la commande n'existe pas!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            //----------------------------------------------------------------------------
            //image cni du client sur une commande à livrer
            //----------------------------------------------------------------------------
            $img_cni = $request->get('cni_img');  // your base64 encoded
            $extend = explode('/', mime_content_type($request->get('cni_img')))[1];

            $img_cni = str_replace('data:image/'.$extend.';base64,', '', $img_cni);
            $img_cni = str_replace(' ', '+', $img_cni);
            $imgName = $objCommande->ref.'_'.str_random(10) . '.'.$extend;

            if(Storage::disk('cni_client')->put($imgName, base64_decode($img_cni))){

                try{
                    $objCommande->update(["cni" => 'api/drat-api/storage/app/public/client/cni_img/'.$imgName]);
                }catch(Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 8;
                    if (in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }

            }else{
                DB::rollback();
                $this->_errorCode = 9;
                if (in_array($this->_env, ['local', 'development'])){
                    $this->_response['message'] ="La livraison à échoué...";
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

            //----------------------------------------------------------------------------
            //Signature du client sur une livraison de la commande
            //----------------------------------------------------------------------------
            $image = $request->get('signature');  // your base64 encoded
            $extension = explode('/', mime_content_type($request->get('signature')))[1];

            $image = str_replace('data:image/'.$extension.';base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = $objCommande->ref.'_'.str_random(10) . '.'.$extension;

            if(Storage::disk('client')->put($imageName, base64_decode($image))){

                try{
                    $objCommande->update(["signature" => 'api/drat-api/storage/app/public/client/signature/'.$imageName]);
                }catch(Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 12;
                    if (in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }

            }else{
                DB::rollback();
                $this->_errorCode = 10;
                if (in_array($this->_env, ['local', 'development'])){
                    $this->_response['message'] ="La livraison à échoué...";
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

            try{
                $objCommande->update(["statut_livraison_id" => $objStatut_livraison->id]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 11;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }else{
            DB::rollback();
            $this->_errorCode = 12;
            $this->_response['message'][] = "Cette commande a déjà été livrée.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {

            //Récupération nombre partiel de commandes du bon de livraison hors mis celle envoyée
            $nombreCommande = DB::table('commandes')
            ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
            ->join('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
            ->join('bon_livraisons', 'bon_livraisons.id', '=', 'commandes.bon_livraison_id')
            ->join('statut_livraisons', 'statut_livraisons.id', '=', 'commandes.statut_livraison_id')
            ->select(DB::raw('count(commandes.id) as nbre_cmd'))
            ->where('commandes.published', 1)
            //->where('commandes.id','!=',$objCommande->id)
            ->where('commandes.bon_livraison_id', $objBon_livraison->id)
            ->first();

        } catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 13;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {

            $objAllCommandes = DB::table('commandes')
            ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
            ->join('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
            ->join('bon_livraisons', 'bon_livraisons.id', '=', 'commandes.bon_livraison_id')
            ->join('statut_livraisons', 'statut_livraisons.id', '=', 'commandes.statut_livraison_id')
            ->select('commandes.id as commande_id',
                'commandes.ref as ref_commande',
                'commandes.mode_payment as mode_payment',
                'commandes.paie_phone as user_phone',
                'commandes.montant as montant',
                'commandes.cni as num_cni',
                'commandes.signature as signature',
                'commandes.lieu_livraison as lieu_livraison',
                'statut_livraisons.name as name_statut_livraison')
            ->where('commandes.published', 1)
            //->where('commandes.id','!=',$objCommande->id)
            ->where('commandes.bon_livraison_id', $objBon_livraison->id)
            ->get();

        } catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 14;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $i = 0;
        $j = 0;
        foreach($objAllCommandes as $item) {

            if($item->name_statut_livraison == "waiting") {
               $i += 1;
            }

            if($item->name_statut_livraison == "delivered") {
               $j += 1;
            }

        }



        if($nombreCommande->nbre_cmd == $i) {

            $objStatut_bon_livraison = Statut_bon_livraison::where('name', '=', 'In progress')->first();
            if (empty($objStatut_bon_livraison)) {
                DB::rollback();
                $this->_errorCode = 15;
                $this->_response['message'][] = "Le statut bon de livraisons n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            try{
                $objBon_livraison->update(["statut_bon_livraison_id" => $objStatut_bon_livraison->id]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 16;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }
        elseif($nombreCommande->nbre_cmd == $j) {

            $objStatut_bon_livraison = Statut_bon_livraison::where('name', '=', 'Delivered')->first();
            if (empty($objStatut_bon_livraison)) {
                $this->_errorCode = 17;
                $this->_response['message'][] = "Le statut bon de livraisons n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            try{
                $objBon_livraison->update(["statut_bon_livraison_id" => $objStatut_bon_livraison->id]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 18;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }
        }
        else{

            $objStatut_bon_livraison = Statut_bon_livraison::where('name', '=', 'In progress')->first();
            if (empty($objStatut_bon_livraison)) {
                DB::rollback();
                $this->_errorCode = 19;
                $this->_response['message'][] = "Le statut bon de livraisons n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            try{
                $objBon_livraison->update(["statut_bon_livraison_id" => $objStatut_bon_livraison->id]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 20;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }

        DB::commit();

        $toReturn = [
            'objet'=> $objCommande
        ];
        $this->_response['message'] = 'Commande livrée.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Affiche toutes les livraisons
    public function allDeliveries()
    {
        $this->_fnErrorCode = "01";
        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode = 2;
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json($this->_response );
        }

        //On vérifie le rôle de l'utilisateur
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias,array('administrateur','gestionnaire-surface'))) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();


        //Récupération des livraisons
        $objAllDeliveries = Bon_livraison::where('published','=',1)->orderBy('id', 'desc')->get();

        $collDeliveries = collect();
        foreach($objAllDeliveries as $objDelivery){

            try{

                //Récupération de toutes les commandes
                $objAllCommandes = DB::table('commandes')
                ->join('bon_livraisons', 'bon_livraisons.id', '=', 'commandes.bon_livraison_id')
                ->join('users', 'users.id', '=', 'commandes.user_client_id')
                ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
                ->join('statut_livraisons', 'commandes.statut_livraison_id', '=', 'statut_livraisons.id')
                ->join('type_livraisons', 'commandes.type_livraison_id', '=', 'type_livraisons.id')
                //->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
                ->select('commandes.ref as ref_commande',
                    'commandes.mode_payment as mode_payment',
                    'commandes.paie_phone as user_phone',
                    'commandes.montant as montant',
                    'commandes.id as id_commande',
                    'commandes.cni as num_cni',
                    'commandes.signature as signature',
                    'commandes.lieu_livraison as lieu_livraison',
                    'users.ref as ref_customer',
                    'users.name as name_customer',
                    'users.surname as surname_customer',
                    'users.email as email_customer',
                    'type_livraisons.ref as ref_type_livraison',
                    'type_livraisons.name as name_type_livraison',
                    'statut_cmds.ref as ref_statut_cmd',
                    'statut_cmds.name as name_statut_cmd',
                    'statut_livraisons.ref as ref_statut_livraison',
                    'statut_livraisons.name as name_statut_livraison')
                ->where('commandes.published', 1)
                ->where('commandes.bon_livraison_id', $objDelivery->id)
                ->orderBy('commandes.id', 'desc')
                ->get();

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 4;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            try{

                //Récupération du livreur
                $objLivreur = DB::table('users')
                ->leftJoin('bon_livraisons', 'users.id', '=', 'bon_livraisons.user_livreur_id')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->select('users.ref as user_ref',
                    'users.name as user_name',
                    'users.surname as user_surname',
                    'users.phone as user_phone',
                    'users.email as user_email',
                    'roles.name as role',
                    'roles.ref as role_ref'
                )
                ->where('users.published', 1)
                ->where('users.id', $objDelivery->user_livreur_id)
                ->first();

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 5;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            try{

                //Récupération de l'objet statut_bon_livraison
                $objStatut_bon_livraison= Statut_bon_livraison::where('id','=',$objDelivery->statut_bon_livraison_id)->first();

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 6;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $collDeliveries->push(array(
                "bon_livraison" => $objDelivery,
                "statut_bon_livraison" => $objStatut_bon_livraison,
                "livreur" => $objLivreur,
                "commandes" => $objAllCommandes
            ));
        }

        DB::commit();

        $toReturn = [
            'objet' => $collDeliveries
        ];

        $this->_response['message'] = "Liste des livraisons.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }


    //Affiche le detail d'une livraison
    public function deliverieDetail(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'bon_livraison'=>'string|required'
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

        $objBon_livraison = Bon_livraison::where('ref', '=', $request->get("bon_livraison"))->first();
        if(empty($objBon_livraison)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Le bon de livraison n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        try{

            $objDetail_bon_livraison = DB::table('commandes')
            ->join('bon_livraisons', 'bon_livraisons.id', '=', 'commandes.bon_livraison_id')
            ->join('users', 'users.id', '=', 'commandes.user_client_id')
            ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
            ->join('statut_livraisons', 'commandes.statut_livraison_id', '=', 'statut_livraisons.id')
            ->join('type_livraisons', 'commandes.type_livraison_id', '=', 'type_livraisons.id')
            //->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
            ->select('commandes.ref as ref_commande',
                'commandes.mode_payment as mode_payment',
                'commandes.paie_phone as user_phone',
                'commandes.montant as montant',
                'commandes.id as id_commande',
                'commandes.cni as num_cni',
                'commandes.signature as signature',
                'commandes.lieu_livraison as lieu_livraison',
                'users.name as name_client',
                'users.surname as surname',
                'type_livraisons.ref as ref_type_livraison',
                'type_livraisons.name as name_type_livraison',
                'statut_cmds.ref as ref_statut_cmd',
                'statut_cmds.name as name_statut_cmd',
                'statut_livraisons.ref as ref_statut_livraison',
                'statut_livraisons.name as name_statut_livraison')
            ->where('commandes.published', 1)
            ->where('commandes.bon_livraison_id', $objBon_livraison->id)
            ->orderBy('commandes.id', 'desc')
            ->get();

        }catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $productsList = collect();
        foreach ($objDetail_bon_livraison as $item) {
            try {

               //Récupération de tous les produits
                $objAllProducts = DB::table('produits')
                ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                ->join('unites', 'unites.id', '=', 'produits.unite_id')
                ->join('users', 'users.id', '=', 'produits.user_prod_id')
                ->join('cooperatives', 'cooperatives.id', '=', 'users.cooperative_id')
                ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                ->select('produits.ref as ref_produit',
                    'produits.id as id_produit',
                    'produits.designation as designation',
                    'produits.prix_produit as prix_produit',
                    'produits.qte as qte_produit',
                    'produits.description as description',
                    'produits.published as published_produit',
                    'produits.type_vente as type_vente_produit',
                    'produits.prix_min_enchere as prix_min_enchere',
                    'produits.statut as statut',
                    'produits.begin_date as begin_date_enchere',
                    'produits.end_date as end_date_enchere',
                    'statut_produits.ref as ref_statut_produit',
                    'statut_produits.name as name_statut_produit',
                    'unites.ref as ref_unite',
                    'unites.name as name_unite',
                    'categories.ref as ref_categorie',
                    'categories.name as name_categorie',
                    'users.ref as ref_producteur',
                    'users.name as name_producteur',
                    'users.surname as surname_producteur',
                    'cooperatives.ref as ref_cooperative',
                    'cooperatives.name as name_cooperative')
                ->where('produits.published','=',1)
                ->where('produits.commande_id','=',$item->id_commande)
                ->orderBy('produits.id','desc')
                ->get();

                $productsList->push(array(
                    'commande' => $item,
                    'produit' => $objAllProducts
                ));

            } catch (Exception $objException) {
                $this->_errorCode = 5;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }


        DB::commit();

        $toReturn = [
            'bon_livraison' => $objBon_livraison,
            'detail_bon_livraison' => $productsList
        ];

        $this->_response['message'] = "Detail sur un bon de livraison.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

}

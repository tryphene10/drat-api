<?php

namespace App\Http\Controllers\Api;

use App\Bon_commande;
use App\Commande;
use App\Cooperative;
use App\Detail_bon_cmd;
use App\Produit;
use App\Role;
use App\Statut_cmd;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BonCommandeApiController extends Controller
{
    public function createBonCommande(Request $request)
    {
        $this->_fnErrorCode = 1;
        //On vérifie que la liste des commandes est bien envoyée !
        $objListCommandes = collect(json_decode($request->getContent(), true));
        if (empty($objListCommandes)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "La liste des commandes est vide!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $objUser = Auth::user();
        if (empty($objUser)) {
            if (in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }
            $this->_errorCode = 3;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        //On vérifie que l'utilisateur est bien gestionnaire de la surface de partage
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias, array("gestionnaire-surface"))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();
        $boncmd = collect();
        $liste_produit = array();
        $listCooperative = array();
        $resume_cooperative = array();
        $tabProduits = array();

        if($objListCommandes->has('commandes')){

            $cmdCollect = collect();
            foreach ($objListCommandes['commandes'] as $commande) {

                $objCommande = Commande::where('ref', '=', $commande['commande'])->first();
                if(empty($objCommande)){
                    DB::rollBack();
                    $this->_errorCode = 5;
                    $this->_response['message'][] = "La commande n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $objStatut_commande = Statut_cmd::where('id', '=', $objCommande->statut_cmd_id)->first();
                if (empty($objStatut_commande)) {
                    DB::rollBack();
                    $this->_errorCode = 6;
                    $this->_response['message'][] = "Le statut commande n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                if ($objStatut_commande->alias == 'pay') {

                    $produits = DB::table('produits')
                    ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                    ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                    ->join('users', 'users.id', '=', 'produits.user_prod_id')
                    ->leftJoin('cooperatives', 'cooperatives.id', '=', 'users.cooperative_id')
                    ->select('categories.name as categorie',
                        'produits.ref as ref_produit',
                        'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                        'produits.designation as nom_produit',
                        'produits.prix_produit as prix',
                        'produits.qte as quantite',
                        'users.name as producteur',
                        'cooperatives.name as cooperative')
                    ->where('produits.published', 1)
                    ->where('produits.commande_id', '=', $objCommande->id)
                    ->get();

                    $index = 0;
                    foreach($produits as $produit) {

                        if($produit->detail_bon_cmd_id != null) {
                            $index += 1;
                        }
                    }

                    if($index > 0) {
                        DB::rollback();
                        $this->_errorCode = 7;
                        $this->_response['message'][] = 'La commande a déjà été utilisée.';
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);

                    }else{
                        $i = 0;
                        foreach($produits as $produit) {

                            if(!in_array($produit->cooperative, $listCooperative)) {
                                $listCooperative[] = $produit->cooperative;
                                $resume_cooperative[$produit->cooperative] = array(
                                    /*"nom_produit" => $produit->nom_produit,*/
                                    "ref_produit".$i => $produit->ref_produit
                                );
                            }else {
                                $resume_cooperative[$produit->cooperative]["ref_produit".$i] = $produit->ref_produit;
                            }

                            $i++;

                        }

                    }

                }else{
                    DB::rollback();
                    $this->_errorCode = 8;
                    $this->_response['message'][] = 'La commande ' . $objCommande->id . ' est en attente de paiement.';
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }

            try {

                //Création du bon de commande
                $objBonCommande = new Bon_commande();
                $objBonCommande->published = 1;
                $objBonCommande->generateReference();
                $objBonCommande->generateAlias("bon commande: " . $objBonCommande->id);
                $objBonCommande->userGsp()->associate($objUser);
                $objBonCommande->save();

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 9;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollback();
            $this->_errorCode = 10;
            $this->_response['message'][] = 'Aucune commande n\'existe.';
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        foreach ($objListCommandes['commandes'] as $commande){
            $objCommande = Commande::where('ref', '=', $commande['commande'])->first();
            try {
                $objCommande->update(['bon_commande_id'=>$objBonCommande->id]);

            } catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 11;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            /*if (empty($objCommande)) {
                DB::rollBack()();
                $this->_errorCode = 5;
                $this->_response['message'][] = "La commande n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }*/
        }

        $bonCmdCollect = collect();
        $produitByCoop= collect();

        foreach ($listCooperative as $cooperative) {
            $objCooperative = Cooperative::where('name', '=', $cooperative)->first();
            try {
                $objDetailBonCmd = new Detail_bon_cmd();
                $objDetailBonCmd->published = 1;
                $objDetailBonCmd->generateReference();
                $objDetailBonCmd->generateAlias("detail bonCommande: " . $objDetailBonCmd->id);
                $objDetailBonCmd->bonCommande()->associate($objBonCommande);
                $objDetailBonCmd->cooperative()->associate($objCooperative);
                $objDetailBonCmd->save();
            }catch (Exception $exception) {
                DB::rollback();
                $this->_errorCode = 12;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $exception->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $tabProduits = $resume_cooperative[$objCooperative->name];

            foreach ($tabProduits as $produitResume){

                $produitCoop = Produit::where('ref', '=',$produitResume)->first();

                $produitCoop->update(['detail_bon_cmd_id'=>$objDetailBonCmd->id]);

            }

            $detailProduits = Produit::where('detail_bon_cmd_id', '=',$objDetailBonCmd->id)->get();

            $boncmd->push(array(
                'cooperative' => $objCooperative,
                'detail_bon_cmd ' => $objDetailBonCmd,
                'produits' => $detailProduits
            ));
        }

        DB::commit();
        //Format d'affichage de message
        $toReturn = [
         'objet' => $objBonCommande,
         'detail_bon_cmds' => $boncmd
        ];

        $this->_response['message'] = 'Enregistrement reussi!';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //liste des bon de commande
    public function getBonCommandeList(){
        $this->_errorCode  = 1;
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias, array("gestionnaire-surface","administrateur")) ) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $bonCommandeCollect = collect();
        $detailBonCmd = collect();
      if ($objRole->alias == "gestionnaire-surface"){
        $objBonCommandes= Bon_commande::where('published','=', 1)
        ->where('user_gsp_id','=',$objUser->id)
        ->orderBy('id', 'desc')->get();

        foreach ($objBonCommandes as $objBonCommande){

            $objDetailBons = Detail_bon_cmd::where('bon_commande_id','=', $objBonCommande->id)->get();
            if ($objDetailBons->isNotEmpty()) {

                $coopCol = collect();
                foreach ($objDetailBons as $objDetailBon){
                    $objCooperative = Cooperative::where('id','=',$objDetailBon->cooperative_id)->where('published','=',1)->first();
                    $produits = DB::table('produits')
                    ->join('statut_produits','statut_produits.id','=','produits.statut_produit_id')
                    ->join('detail_bon_cmds','detail_bon_cmds.id','=','produits.detail_bon_cmd_id')
                    ->join('cooperatives','cooperatives.id','=','detail_bon_cmds.cooperative_id')
                    ->join('unites','unites.id','=','produits.unite_id')
                    ->join('commandes','commandes.id','=','produits.commande_id')
                    ->select('produits.designation as designation',
                        'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                        'produits.prix_produit as prix',
                        'produits.qte as quantite',
                        'produits.statut as statut_check',
                        'unites.name as name_unite',
                        'unites.ref as ref_unite',
                        'commandes.ref as ref_commande',
                        'commandes.image_qrcode as image_qrcode',
                        'statut_produits.name as statut_produit',
                        'cooperatives.name as cooperative',
                        'cooperatives.longitude as longitude',
                        'cooperatives.latitude as latitude',
                        'cooperatives.ref as ref_cooperative'
                    )
                    ->where('produits.published', '=', 1)
                    ->where('produits.detail_bon_cmd_id', '=', $objDetailBon->id)
                    ->get();

                    $coopCol->push([
                        'cooperative' => $objCooperative,
                        'detail_bon_commande' =>$objDetailBon,
                        'produit' =>$produits
                    ]);

                }

                $objCoursier = User::where('id', '=', $objBonCommande->user_coursier_id)->first();

                $bonCommandeCollect->push([
                   'bon_commande'=>$objBonCommande,
                    'detail'=>$coopCol,
                    'coursier'=>$objCoursier
                ]);

            }

        }
      }elseif ($objRole->alias == "administrateur"){
          $objBonCommandes= Bon_commande::where('published','=', 1)
              //->where('user_gsp_id','=',$objUser->id)
              ->orderBy('id', 'desc')->get();

          foreach ($objBonCommandes as $objBonCommande){

              $objDetailBons = Detail_bon_cmd::where('bon_commande_id','=', $objBonCommande->id)->get();
              if ($objDetailBons->isNotEmpty()) {

                  $coopCol = collect();
                  foreach ($objDetailBons as $objDetailBon){
                      $objCooperative = Cooperative::where('id','=',$objDetailBon->cooperative_id)->where('published','=',1)->first();
                      $produits = DB::table('produits')
                          ->join('statut_produits','statut_produits.id','=','produits.statut_produit_id')
                          ->join('detail_bon_cmds','detail_bon_cmds.id','=','produits.detail_bon_cmd_id')
                          ->join('cooperatives','cooperatives.id','=','detail_bon_cmds.cooperative_id')
                          ->join('unites','unites.id','=','produits.unite_id')
                          ->join('commandes','commandes.id','=','produits.commande_id')
                          ->select('produits.designation as designation',
                              'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                              'produits.prix_produit as prix',
                              'produits.qte as quantite',
                              'produits.statut as statut_check',
                              'unites.name as name_unite',
                              'unites.ref as ref_unite',
                              'commandes.ref as ref_commande',
                              'commandes.image_qrcode as image_qrcode',
                              'statut_produits.name as statut_produit',
                              'cooperatives.name as cooperative',
                              'cooperatives.longitude as longitude',
                              'cooperatives.latitude as latitude',
                              'cooperatives.ref as ref_cooperative'
                          )
                          ->where('produits.published', '=', 1)
                          ->where('produits.detail_bon_cmd_id', '=', $objDetailBon->id)
                          ->get();

                      $coopCol->push([
                          'cooperative' => $objCooperative,
                          'detail_bon_commande' =>$objDetailBon,
                          'produit' =>$produits
                      ]);

                  }

                  $objCoursier = User::where('id', '=', $objBonCommande->user_coursier_id)->first();

                  $bonCommandeCollect->push([
                      'bon_commande'=>$objBonCommande,
                      'detail'=>$coopCol,
                      'coursier'=>$objCoursier
                  ]);

              }

          }
      }


        $toReturn = [
            'liste_bon_commande'=>$bonCommandeCollect,
        ];
        $this->_response['message']    = 'Liste des bon de commandes.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //liste bon de commande assigner au coursier
    public function bonCommandeByCoursier(){
        $this->_errorCode  = 1;
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien coursier
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if ($objRole->alias != "coursier") {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $bonCommandeCollect = collect();
        $objBonCommandes= Bon_commande::where('published','=', 1)
        ->where('user_coursier_id','=',$objUser->id)
        ->orderBy('id', 'desc')->get();

        foreach ($objBonCommandes as $objBonCommande){

            $objDetailBons = Detail_bon_cmd::where('bon_commande_id','=', $objBonCommande->id)->get();
            if ($objDetailBons->isNotEmpty()) {

                $detailBonCmd = collect();
                foreach ($objDetailBons as $objDetailBon){
                    $objCooperative = Cooperative::where('id','=',$objDetailBon->cooperative_id)->where('published','=',1)->first();
                        $produits = DB::table('produits')
                            ->join('statut_produits','statut_produits.id','=','produits.statut_produit_id')
                            ->join('detail_bon_cmds','detail_bon_cmds.id','=','produits.detail_bon_cmd_id')
                            ->join('unites','unites.id','=','produits.unite_id')
                            ->join('volumes','volumes.id','=','produits.volume_id')
                            ->join('cooperatives','cooperatives.id','=','detail_bon_cmds.cooperative_id')
                            ->join('commandes','commandes.id','=','produits.commande_id')
                            ->select('produits.designation as designation',
                                'produits.prix_produit as prix',
                                'produits.qte as quantite',
                                'produits.statut as statut_check',
                                'statut_produits.name as statut_produit',
                                'unites.ref as ref_unite',
                                'unites.name as unite_name',
                                'volumes.ref as ref_volume',
                                'volumes.name as name_volume',
                                'commandes.ref as ref_commande',
                                'commandes.image_qrcode as image_qrcode',
                                'cooperatives.name as cooperative',
                                'cooperatives.longitude as longitude',
                                'cooperatives.latitude as latitude',
                                'cooperatives.ref as ref_cooperative'
                            )
                            ->where('produits.published', '=', 1)
                            ->where('produits.detail_bon_cmd_id', '=', $objDetailBon->id)
                            ->get();
                        $detailBonCmd->push([
                            'cooperative' => $objCooperative,
                            'detail_bon_commande' =>$objDetailBon,
                            'produits' =>$produits
                        ]);
                }

                $bonCommandeCollect->push(array(
                    'bon_commande'=>$objBonCommande,
                    'detail'=>$detailBonCmd
                ));
            }
        }

        $toReturn = [
            'liste_bon_commande'=>$bonCommandeCollect,
        ];

        $this->_response['message'] = "Liste des bon de commande assigner au coursier ";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
    //detail bon de commande
    public function detailBonCommande(Request $request){
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'bon_commande' => 'string|required',
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
            return response()->json($this->_response);
        }
        //On vérife que l'utilisateur est bien connecté
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias, array("coursier","gestionnaire-surface"))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $objBonCommande = Bon_commande::where('ref', '=', $request->get('bon_commande'))->first();
        if (empty($objBonCommande)) {
            DB::rollback();
            $this->_errorCode = 5;
            $this->_response['message'][] = "le bon de commande n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $detailBonCommandes = DB::table('detail_bon_cmds')
        ->join('bon_commandes','bon_commandes.id','=','detail_bon_cmds.bon_commande_id')
        ->join('cooperatives','cooperatives.id','=','detail_bon_cmds.cooperative_id')
        ->select('detail_bon_cmds.signature_detail_bon_commande as signature_gest_coop',
            'detail_bon_cmds.id as id_detail_bon_commande',
            'detail_bon_cmds.ref as ref_detail_bon_commande',
            'bon_commandes.ref as ref_bon_commande',
            'cooperatives.name as cooperative',
            'cooperatives.longitude as longitude',
            'cooperatives.latitude as latitude',
            'cooperatives.ref as ref_cooperative'
        )
        ->where('detail_bon_cmds.published', '=', 1)
        ->where('detail_bon_cmds.bon_commande_id', '=', $objBonCommande->id)
        ->get();
        $detailBonCmd = collect();
        foreach ($detailBonCommandes as $detailBonCommande) {
            $produits = DB::table('produits')
            ->join('statut_produits','statut_produits.id','=','produits.statut_produit_id')
            ->join('detail_bon_cmds','detail_bon_cmds.id','=','produits.detail_bon_cmd_id')
            ->join('unites','unites.id','=','produits.unite_id')
            ->join('commandes','commandes.id','=','produits.commande_id')
            ->select(
                'produits.ref as ref',
                'produits.designation as designation',
                'produits.prix_produit as prix',
                'produits.qte as quantite',
                'produits.statut as statut_check',
                'statut_produits.name as statut_produit',
                'commandes.ref as ref_commande',
                'commandes.image_qrcode as image_qrcode',
                'unites.ref as ref_unite',
                'unites.name as name_unite'
            )
            ->where('produits.published', '=', 1)
            ->where('produits.detail_bon_cmd_id', '=', $detailBonCommande->id_detail_bon_commande)
            ->get();
            $detailBonCmd->push([
                'detail_bon_commande' =>$detailBonCommande,
                'produits' =>$produits
            ]);
        }

        $toReturn = [
            'liste_detail_bon_commande'=>$detailBonCmd,
        ];
        $this->_response['message']    = 'Liste des details du bon de commande.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

   //Gestionnaire cooperative
    public function cooperativeBonCommande(){
        $this->_errorCode  = 1;
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien gestionnaire cooperative
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if ($objRole->alias != "gestionnaire-cooperative") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $bonCommandeCollect = collect();
        $detailBonCmd = collect();

        $detailBonCommandes = DB::table('detail_bon_cmds')
        ->join('bon_commandes','bon_commandes.id','=','detail_bon_cmds.bon_commande_id')
        ->join('users','users.id','=','bon_commandes.user_coursier_id')
        ->join('cooperatives','cooperatives.id','=','detail_bon_cmds.cooperative_id')
        ->select('detail_bon_cmds.signature_detail_bon_commande as signature_gest_coop',
            'detail_bon_cmds.id as id_detail_bon_commande',
            'detail_bon_cmds.ref as ref_detail_bon_commande',
            'detail_bon_cmds.created_at as created_at',
            'bon_commandes.ref as ref_bon_commande',
            'cooperatives.name as cooperative',
            'cooperatives.longitude as longitude',
            'cooperatives.latitude as latitude',
            'cooperatives.ref as ref_cooperative',
            'users.ref as ref_coursier',
            'users.phone as phone_coursier',
            'users.name as name_coursier'
        )
        ->where('detail_bon_cmds.published', '=', 1)
        ->where('detail_bon_cmds.cooperative_id', '=', $objUser->cooperative_id)
        ->orderBy('detail_bon_cmds.id','desc')
        ->get();

        foreach ($detailBonCommandes as $detailBonCommande) {
            $produits = DB::table('produits')
            ->join('statut_produits','statut_produits.id','=','produits.statut_produit_id')
            ->join('detail_bon_cmds','detail_bon_cmds.id','=','produits.detail_bon_cmd_id')
            ->join('unites','unites.id','=','produits.unite_id')
            ->join('commandes','commandes.id','=','produits.commande_id')
            ->select(
                'produits.ref as ref',
                'produits.designation as designation',
                'produits.prix_produit as prix',
                'produits.qte as quantite',
                'produits.statut as statut_check',
                'commandes.ref as ref_commande',
                'commandes.image_qrcode as image_qrcode',
                'unites.name as name_unite',
                'unites.ref as ref_unite',
                'statut_produits.name as statut_produit'
            )
            ->where('produits.published', '=', 1)
            ->where('produits.detail_bon_cmd_id', '=', $detailBonCommande->id_detail_bon_commande)
            ->get();

            $detailBonCmd->push([
                /*'cooperative' => $cooperative,*/
                'detail_bon_commande' =>$detailBonCommande,
                'produits' =>$produits
            ]);
        }

        $toReturn = [
            'liste_bon_commande'=>$detailBonCmd,
        ];
        $this->_response['message']    = 'Liste des bon de commandes.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function assignToCoursier(Request $request){

        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'bon_commande' => 'string|required',
            'coursier' => 'string|required'
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
            return response()->json($this->_response);
        }

        //On vérife que l'utilisateur est bien connecté
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if ($objRole->alias != "gestionnaire-surface") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        $objBonCommande = Bon_commande::where('ref', '=', $request->get('bon_commande'))->first();
        if (empty($objBonCommande)) {
            DB::rollback();
            $this->_errorCode = 5;
            $this->_response['message'][] = "le bon de commande n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objCoursier = User::where('ref', '=',$request->get('coursier'))->first();
        if (empty($objCoursier)) {
            DB::rollback();
            $this->_errorCode = 6;
            $this->_response['message'][] = "Le coursier n'existe pas !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien coursier
        $objRoleCoursier = Role::where('id', '=', $objCoursier->role_id)->first();
        if ($objRoleCoursier->alias != "coursier") {
            DB::rollback();
            $this->_errorCode = 7;
            $this->_response['message'][] = "Utilisateur non coursier !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $detailcollect = collect();
        $objBonCollect = collect();

        try{

            $objBonCommande->update(["user_coursier_id" => $objCoursier->id]);

        }catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 8;
            if (in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'] = $objException->getMessage();
            }
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $cooll = "https://draht.team-solutions.net/#/dashboards/";
        try{
            $msg = "Date du ".$objBonCommande->created_at.", DRAT : ".$objCoursier->name." ".$objCoursier->surname.", un bon de commande vous a été assigner, clique sur ce lien pour les voir ".$cooll;
            $returnSms = $objCoursier->sms($msg, $objCoursier->phone);
        } catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 14;
            if (in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'] = $objException->getMessage();
            }
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {

            $objdetails = DB::table('detail_bon_cmds')
            ->Join('bon_commandes', 'bon_commandes.id', '=', 'detail_bon_cmds.bon_commande_id')
            ->Join('cooperatives', 'cooperatives.id', '=', 'detail_bon_cmds.cooperative_id')
            ->select('bon_commandes.ref as ref_bon_commande',
                'detail_bon_cmds.id as id_detail_bon_cmd',
                'cooperatives.name as cooperative_name',
                'cooperatives.longitude as longitude',
                'cooperatives.latitude as latitude',
                )
            ->where("detail_bon_cmds.bon_commande_id", "=", $objBonCommande->id)
            ->get();
        }catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 9;
            if (in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'] = $objException->getMessage();
            }
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $detailBonCmd = collect();
        foreach ($objdetails as $objdetail){
            try{


                $produits = DB::table('produits')
                ->join('statut_produits','statut_produits.id','=','produits.statut_produit_id')
                ->join('detail_bon_cmds','detail_bon_cmds.id','=','produits.detail_bon_cmd_id')
                ->join('commandes','commandes.id','=','produits.commande_id')
                ->join('cooperatives','cooperatives.id','=','detail_bon_cmds.cooperative_id')
                ->select('produits.designation as designation',
                    'produits.prix_produit as prix',
                    'produits.qte as quantite',
                    'produits.statut as statut_check ',
                    'statut_produits.name as statut_produit ',
                    'commandes.ref as ref_commande',
                    'commandes.image_qrcode as image_qrcode',
                    'cooperatives.name as cooperative',
                    'cooperatives.longitude as longitude',
                    'cooperatives.latitude as latitude',
                    'cooperatives.ref as ref_cooperative'
                )
                ->where('produits.published', '=', 1)
                ->where('produits.detail_bon_cmd_id', '=', $objdetail->id_detail_bon_cmd)
                ->get();

                $detailcollect->push(array(
                'detail_bon_commande' => $objdetail,
                'produits' => $produits
                ));
            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 10;
                if (in_array($this->_env, ['local', 'development'])) {
                    $this->_response['message'] = $objException->getMessage();
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }


        DB::commit();

       /* $toReturn = [
            'bon_commande' => $objBonCommande,
            'coursier' => $objCoursier,
            'detail' => $detailBonCmd
            */
        $toReturn = [
            'bon_commande'=>$objBonCommande,
            'coursier' => $objCoursier,
            'detail' => $detailcollect,
            'sms'=>$returnSms
        ];

        $this->_response['message'] = "le bon de commande a ete assigner avec succes ! ";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //coursier checked product list
    public function checkDetailBonCommande(Request $request){

        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'ref_produit' => 'string|required'
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
        if ($objRole->alias != "coursier") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        $objProduit = Produit::where('ref', '=', $request->get('ref_produit'))->first();
        if(empty($objProduit)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "le produit n'existe pas ";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        if($objProduit->statut == 0){
            if($objProduit->detail_bon_cmd_id <> null){
                try{
                    $objProduit->update(["statut" => 1]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 6;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }else {
                DB::rollback();
                $this->_errorCode = 7;
                $this->_response['message'][] = "le produit selectionner n'appartient a aucun detail de la commande";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }else {
            DB::rollback();
            $this->_errorCode = 8;
            $this->_response['message'][] = "le produit a déja été checked";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        // Commit the queries!
        DB::commit();

        $toReturn = [
            "objet" => $objProduit
        ];

        $this->_response['message'] = " le produit a été checked.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //signature du detail bon de commande par le gestionnaire de la cooperative
    public function signDetailBonCmd(Request $request){

        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'detail_bon_commande' => 'string|required',
            'signature' => 'string|required'
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

        if ($objRole->alias != "gestionnaire-cooperative") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objDetailBonCmd = Detail_bon_cmd::where('ref', '=', $request->get('detail_bon_commande'))->first();
        if(empty($objDetailBonCmd)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "le detail bon de commande n'existe pas !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objProduitCheck = Produit::where('detail_bon_cmd_id','=',$objDetailBonCmd->id)
        ->where('statut','!=', 0)
        ->count();

        $objProduitNotCheck = Produit::where('detail_bon_cmd_id','=',$objDetailBonCmd->id)
        ->where('statut','=', 0)
        ->count();

        if ($objProduitCheck !== 0 && $objProduitNotCheck == 0) {
            if ($objDetailBonCmd->signature_detail_bon_commande == null) {
                try {
                    $image = $request->get('signature');  // your base64 encoded
                    $extension = explode('/', mime_content_type($request->get('signature')))[1];
                    $image = str_replace('data:image/' . $extension . ';base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = "signature_" . date('D_M_Y_mhs') . '.' . $extension;
                    if (Storage::disk('signature_cooperative')->put($imageName, base64_decode($image))) {
                        try {
                            //Mise à jour de la propriété statut_commande_id de la commande
                            $objDetailBonCmd->update(["signature_detail_bon_commande" => 'api/drat-api/storage/app/public/images/signature_cooperative/' . $imageName]);
                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 6;
                            if (in_array($this->_env, ['local', 'development'])) {
                                $this->_response['message'] = $objException->getMessage();
                            }
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    } else {
                        DB::rollback();
                        $this->_errorCode = 7;
                        $this->_response['message'][] = "Echec enregistrement de l'image !";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                } catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 8;
                    if (in_array($this->_env, ['local', 'development'])) {
                        $this->_response['message'] = $objException->getMessage();
                    }
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            } else {
                DB::rollback();
                $this->_errorCode = 9;
                $this->_response['message'][] = "le detail a déja été signer!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }else{
            DB::rollback();
            $this->_errorCode = 10;
            $this->_response['message'][] = "Tous les produits n'ont pas été checked !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        // Commit the queries!
        DB::commit();
        $toReturn = [
            "objet" => $objDetailBonCmd
        ];

        $this->_response['message'] = "Le handcheck est correctement enregistré !";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //signer un bon de commande
    public function signBonCommande(Request $request){
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'bon_commande' => 'string|required',
            'signature' => 'string|required'
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
        if ($objRole->alias != "gestionnaire-surface") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objBonCommande = Bon_commande::where('ref', '=', $request->get('bon_commande'))->first();
        if(empty($objBonCommande)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "le bon de commande n'existe pas !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $objDetailBonsign = Detail_bon_cmd::where('bon_commande_id','=',$objBonCommande->id)
            ->where('signature_detail_bon_commande','!=', null)
            ->count();
        $objDetailBonNotsign = Detail_bon_cmd::where('bon_commande_id','=',$objBonCommande->id)
            ->where('signature_detail_bon_commande','=', null)
            ->count();
        if ($objDetailBonsign !== 0 && $objDetailBonNotsign == 0){
            if ($objBonCommande->signature_bon_cmd == null){
                try{
                    $image = $request->get('signature');  // your base64 encoded
                    $extension = explode('/', mime_content_type($request->get('signature')))[1];
                    $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                    $image = str_replace(' ', '+', $image);
                    $imageName = "signature_". date('D_M_Y_mhs') . '.'.$extension;

                    if(Storage::disk('signature_surface')->put($imageName, base64_decode($image))) {

                        try{
                            //Mise à jour de la propriété statut_commande_id de la commande
                            $objBonCommande->update(["signature_bon_cmd" =>'api/drat-api/storage/app/public/images/signature_surface/'.$imageName]);
                        }catch(Exception $objException){
                            DB::rollback();
                            $this->_errorCode = 6;
                            if (in_array($this->_env, ['local', 'development'])) {
                                $this->_response['message'] = $objException->getMessage();
                            }
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }
                    else {
                        DB::rollback();
                        $this->_errorCode = 7;
                        $this->_response['message'][] = "Echec enregistrement de l'image !";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                } catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 8;
                    if (in_array($this->_env, ['local', 'development'])) {
                        $this->_response['message'] = $objException->getMessage();
                    }
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }else {
                DB::rollback();
                $this->_errorCode = 9;
                $this->_response['message'][] = "le bon de commande a déja été signés !";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }else{
            DB::rollback();
            $this->_errorCode = 10;
            $this->_response['message'][] = "Tous les détails n'ont pas été signer !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        // Commit the queries!
        DB::commit();
        $toReturn = [
            "objet" => $objBonCommande
        ];
        $this->_response['message'] = "bon de commande validé !";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
}

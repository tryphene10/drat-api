<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\User;
use App\Statut_cmd;
use App\Type_livraison;
use App\Role;
use App\Produit;
use App\Statut_produit;
use App\Commande;
use App\Statut_livraison;
use App\Quartier;
use App\Quartier_surface;
use App\Surface_partage;
use App\Ville_surface;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/*
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
 */
class CommandeApiController extends Controller
{
    public function create(Request $request)
    {
        $this->_fnErrorCode = 1;

        //On vérifie que la commande est bien envoyé !
        $objListProduitsCommande = collect(json_decode($request->getContent(), true));
        if (empty($objListProduitsCommande)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "La liste des produits de la commande est vide!";
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

        $objPrixTotal = 0;
        $objStatutCmde = (object)[];
        $objCmde = (object)[];
        $resultOperateur = (object)[];
        $resultTransaction = (object)[];
        $objStatut_produit = (object)[];
        $returnSms = "";
        if($objListProduitsCommande->has("paiement_methode")){

            //--------------------------------------------------------------------
            // CURL
            //--------------------------------------------------------------------

            if($objListProduitsCommande->has("produits")) {

                foreach($objListProduitsCommande["produits"] as $itemProduit) {
                    //-----------------------------------------------------------------------------------------------
                    //Récupération de l'objet produit
                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();
                    if(empty($produit)) {
                        DB::rollback();
                        $this->_errorCode = 5;
                        $this->_response['message'][] = "Le produit n'existe pas!";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    if($produit->commande_id == null) {
                        $objPrixTotal += intval($produit->prix_produit);
                    }else {
                        DB::rollback();
                        $this->_errorCode = 6;
                        $this->_response['message'][] = "Le produit a déjà été commandé !";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }

                if($objListProduitsCommande->has("paie_phone")) {

                    $phone = $objListProduitsCommande["paie_phone"];

                    if($objListProduitsCommande->has("type_livraison")) {
                        //-----------------------------------------------------------------------------------------------
                        //Recupère l'objet Type_livraison
                        //-----------------------------------------------------------------------------------------------
                        $objType_livraison = Type_livraison::where('ref', '=', $objListProduitsCommande["type_livraison"])->first();
                        if(empty($objType_livraison)) {
                            DB::rollback();
                            $this->_errorCode = 7;
                            $this->_response['message'][] = "Le type_livraison n'existe pas!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        if($objType_livraison->id == 1) {//1:A domicile

                            if($objListProduitsCommande->has("ville")) {

                                $objVille_surface = Ville_surface::where('id', '=', $objListProduitsCommande["ville"])->first();
                                if(empty($objVille_surface)) {
                                    DB::rollback();
                                    $this->_errorCode = 8;
                                    $this->_response['message'][] = "La ville n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                $objSurface = Surface_partage::where('id','=',$objVille_surface->surface_partage_id)->first();
                                if(empty($objSurface)) {
                                    DB::rollback();
                                    $this->_errorCode = 9;
                                    $this->_response['message'][] = "La surface de partage n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                if($objListProduitsCommande->has("lieu_livraison")) {

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

                                    //-----------------------------------------------------------------------------------------------
                                    //Statut_livraison : waiting
                                    //-----------------------------------------------------------------------------------------------
                                    $objStatut_livraison = Statut_livraison::where('alias', '=', 'waiting')->first();
                                    if(empty($objStatut_livraison)) {
                                        DB::rollback();
                                        $this->_errorCode = 11;
                                        $this->_response['message'][] = "Le statut_livraison de la commande n'existe pas!";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    if(strlen($phone) == 12) {

                                        if(preg_match('/^(237)(\d{3})(\d{3})(\d{3})$/', $phone, $matches)) {

                                            if($objListProduitsCommande['paiement_methode'] == 'mtn') {

                                                $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                            }elseif ($objListProduitsCommande['paiement_methode'] == 'orange') {

                                                $phone = $matches[2].$matches[3].$matches[4];

                                            }
                                        }

                                    }elseif(strlen($phone) == 9) {

                                        if(preg_match('/^(6)(\d{2})(\d{3})(\d{3})$/', $phone, $matches)) {

                                            if($objListProduitsCommande['paiement_methode'] == 'mtn') {

                                                $phone = "237".$matches[1].$matches[2].$matches[3].$matches[4];

                                            }elseif ($objListProduitsCommande['paiement_methode'] == 'orange') {

                                                $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                            }
                                        }

                                    }else {
                                        DB::rollback();
                                        $this->_errorCode = 12;
                                        $this->_response['message'][] = "Phone incorrect!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    //-----------------------------------------------------------------------------------------------
                                    //Création de la commande
                                    //-----------------------------------------------------------------------------------------------
                                    try {
                                        $objCommande = new Commande();
                                        $objCommande->mode_payment = $objListProduitsCommande["paiement_methode"];
                                        $objCommande->montant = $objPrixTotal;
                                        $objCommande->paie_phone = $phone;
                                        $objCommande->lieu_livraison = $objListProduitsCommande["lieu_livraison"];
                                        $objCommande->published = 1;
                                        $objCommande->generateReference();
                                        $objCommande->generateAlias("Commande".$objCommande->id);
                                        $objCommande->UserClient()->associate($objUser);
                                        $objCommande->typeLivraison()->associate($objType_livraison);
                                        $objCommande->statutCmd()->associate($objStatutCmde);
                                        $objCommande->statutLivraison()->associate($objStatut_livraison);
                                        $objCommande->surfacePartage()->associate($objSurface);
                                        $objCommande->save();
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 13;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                    foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                        //Récupération de l'objet produit
                                        $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                        try{
                                            //Mettre à jour l'id commande dans la liste des produits
                                            $produit->update(['commande_id' => $objCommande->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 14;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }

                                    //-----------------------------------------------------------------------------------------------
                                    //Initiation d'un paiement
                                    //-----------------------------------------------------------------------------------------------
                                    $postfields = array(
                                        'phone' => $objCommande->paie_phone,
                                        'montant' => $objCommande->montant,
                                        'transactionkey' => $objCommande->ref,
                                        'apiKey' => $objListProduitsCommande["apiKey"],
                                        'secretKey' => $objListProduitsCommande["secretKey"],
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

                                        //return response()->json($result);

                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 15;
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
                                        //$resultTransaction['ref']

                                        try {
                                            $objCmde->update(['transaction' => $resultTransaction['ref']]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 16;
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
                                                $this->_errorCode = 17;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 18;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 19;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                            foreach($listProduits as $eltProduit) {
                                                try {
                                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 20;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                                $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                                //envoie de sms au producteur
                                                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                                try{
                                                    $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu, rapproche toi de ton gestionnaire avec le produit. connecter vous a " .$cooll;
                                                    $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
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

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 22;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 23;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 24;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

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
                                                $this->_errorCode = 25;
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
                                                    $this->_errorCode = 26;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 27;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                //on récupère l'objet statut_produit
                                                $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                                if(empty($objStatut_produit)) {
                                                    $this->_errorCode = 28;
                                                    $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                                foreach($listProduits as $eltProduit) {
                                                    try {
                                                        $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 29;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }
                                                }

                                            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                                //-----------------------------------------------------------------------------------------------
                                                //Statut cancel
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
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

                                                foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                    //Récupération de l'objet produit
                                                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                    try{
                                                        //Mettre à jour l'id commande à null dans la liste des produits
                                                        $produit->update(['commande_id' => Null]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 32;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }

                                                }

                                            }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                                //-----------------------------------------------------------------------------------------------
                                                //Statut waiting
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                                if(empty($objStatutCmde)) {
                                                    DB::rollback();
                                                    $this->_errorCode = 33;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 34;
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
                                                    $this->_errorCode = 35;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 36;
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
                                                    $this->_errorCode = 37;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 38;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                    //Récupération de l'objet produit
                                                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                    try{
                                                        //Mettre à jour l'id commande à null dans la liste des produits
                                                        $produit->update(['commande_id' => Null]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 39;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }

                                                }

                                            }else{
                                                DB::rollback();
                                                $this->_errorCode = 40;
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
                                                $this->_errorCode = 41;
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
                                                    $this->_errorCode = 42;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 43;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                //on récupère l'objet statut_produit
                                                $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                                if(empty($objStatut_produit)) {
                                                    $this->_errorCode = 44;
                                                    $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                                foreach($listProduits as $eltProduit) {
                                                    try {
                                                        $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 45;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }
                                                }

                                            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                                //-----------------------------------------------------------------------------------------------
                                                //Statut cancel
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                                if(empty($objStatutCmde)) {
                                                    DB::rollback();
                                                    $this->_errorCode = 46;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 47;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                    //Récupération de l'objet produit
                                                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                    try{
                                                        //Mettre à jour l'id commande à null dans la liste des produits
                                                        $produit->update(['commande_id' => Null]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 48;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }

                                                }

                                            }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                                //-----------------------------------------------------------------------------------------------
                                                //Statut waiting
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
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

                                            }elseif($resultTransaction['transaction_status'] == 'INITIATED') {

                                                //-----------------------------------------------------------------------------------------------
                                                //Statut waiting
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                                if(empty($objStatutCmde)) {
                                                    DB::rollback();
                                                    $this->_errorCode = 51;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 52;
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
                                                    $this->_errorCode = 53;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 54;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                    //Récupération de l'objet produit
                                                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                    try{
                                                        //Mettre à jour l'id commande à null dans la liste des produits
                                                        $produit->update(['commande_id' => Null]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 55;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }

                                                }

                                            }else{
                                                DB::rollback();
                                                $this->_errorCode = 56;
                                                $this->_response['message'][] = "Statut inexistant!.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }elseif ($resultTransaction['transaction_status'] == 'EXPIRED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 57;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 58;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 59;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 60;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif($resultOperateur['name'] == "mtn") {

                                        $resultTransaction = $result['data']['transaction'];
                                        try {
                                            $objCmde->update(['transaction' => $resultTransaction['ref']]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 61;
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
                                                $this->_errorCode = 62;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 63;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 64;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                            foreach($listProduits as $eltProduit) {
                                                try {
                                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 65;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                                $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                                //envoie de sms au producteur
                                                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                                try{
                                                    $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu. connecter vous a " .$cooll;
                                                    $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                                } catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 66;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                        $this->_response['message'] = $objException->getMessage();
                                                    }
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 67;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 68;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 69;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

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
                                                $this->_errorCode = 70;
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
                                                    $this->_errorCode = 71;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 72;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                //on récupère l'objet statut_produit
                                                $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                                if(empty($objStatut_produit)) {
                                                    $this->_errorCode = 73;
                                                    $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                                foreach($listProduits as $eltProduit) {
                                                    try {
                                                        $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 74;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }
                                                }

                                            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                                //-----------------------------------------------------------------------------------------------
                                                //Statut cancel
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                                if(empty($objStatutCmde)) {
                                                    DB::rollback();
                                                    $this->_errorCode = 75;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 76;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                    //Récupération de l'objet produit
                                                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                    try{
                                                        //Mettre à jour l'id commande à null dans la liste des produits
                                                        $produit->update(['commande_id' => Null]);
                                                    }catch (Exception $objException) {
                                                        DB::rollback();
                                                        $this->_errorCode = 77;
                                                        if (in_array($this->_env, ['local', 'development'])) {
                                                        }
                                                        $this->_response['message'] = $objException->getMessage();
                                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                                        return response()->json($this->_response);
                                                    }

                                                }

                                            }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                                //-----------------------------------------------------------------------------------------------
                                                //Statut waiting
                                                //-----------------------------------------------------------------------------------------------
                                                $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                                if(empty($objStatutCmde)) {
                                                    DB::rollback();
                                                    $this->_errorCode = 78;
                                                    $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                                try {
                                                    $objCommande->update(['statut_cmd_id' => $objStatutCmde->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 79;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }else{
                                                DB::rollback();
                                                $this->_errorCode = 80;
                                                $this->_response['message'][] = "Statut inexistant!.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 81;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else {
                                        DB::rollback();
                                        $this->_errorCode = 82;
                                        $this->_response['message'][] = "Paramètre de payment manquant.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 83;
                                    $this->_response['message'][] = "Veuillez saisir un lieu de livraison!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }else {
                                DB::rollback();
                                $this->_errorCode = 84;
                                $this->_response['message'][] = "Veuillez choisir un quartier!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }elseif($objType_livraison->id == 2) {//2:Point distribution drat
                            
                            if($objListProduitsCommande->has("ville")) {

                                $objVille_surface = Ville_surface::where('id', '=', $objListProduitsCommande["ville"])->first();
                                if(empty($objVille_surface)) {
                                    DB::rollback();
                                    $this->_errorCode = 85;
                                    $this->_response['message'][] = "La ville n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                $objSurface = Surface_partage::where('id','=',$objVille_surface->surface_partage_id)->first();
                                if(empty($objSurface)) {
                                    DB::rollback();
                                    $this->_errorCode = 86;
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
                                    $this->_errorCode = 87;
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
                                    $this->_errorCode = 88;
                                    $this->_response['message'][] = "Le statut_livraison de la commande n'existe pas!";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }


                                if(strlen($phone) == 12) {

                                    if(preg_match('/^(237)(\d{3})(\d{3})(\d{3})$/', $phone, $matches)) {

                                        if($objListProduitsCommande['paiement_methode'] == 'mtn') {

                                            $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                        }elseif ($objListProduitsCommande['paiement_methode'] == 'orange') {

                                            $phone = $matches[2].$matches[3].$matches[4];

                                        }
                                    }

                                }elseif(strlen($phone) == 9) {

                                    if(preg_match('/^(6)(\d{2})(\d{3})(\d{3})$/', $phone, $matches)) {

                                        if($objListProduitsCommande['paiement_methode'] == 'mtn') {

                                            $phone = "237".$matches[1].$matches[2].$matches[3].$matches[4];

                                        }elseif ($objListProduitsCommande['paiement_methode'] == 'orange') {

                                            $phone = $matches[1].$matches[2].$matches[3].$matches[4];

                                        }
                                    }

                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 89;
                                    $this->_response['message'][] = "Phone incorrect!.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                //-----------------------------------------------------------------------------------------------
                                //Création de la commande
                                //-----------------------------------------------------------------------------------------------
                                try {
                                    $objCommande = new Commande();
                                    $objCommande->mode_payment = $objListProduitsCommande["paiement_methode"];
                                    $objCommande->montant = $objPrixTotal;
                                    $objCommande->paie_phone = $phone;
                                    $objCommande->published = 1;
                                    $objCommande->generateReference();
                                    $objCommande->generateAlias("Commande".$objCommande->id);
                                    $objCommande->UserClient()->associate($objUser);
                                    $objCommande->typeLivraison()->associate($objType_livraison);
                                    $objCommande->statutCmd()->associate($objStatutCmde);
                                    $objCommande->statutLivraison()->associate($objStatut_livraison);
                                    $objCommande->surfacePartage()->associate($objSurface);
                                    $objCommande->image_qrcode=$this->generateQRCode($objCommande->ref);
                                    $objCommande->save();

                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 90;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                                foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                    //Récupération de l'objet produit
                                    $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                    try{
                                        //Mettre à jour l'id commande dans la liste des produits
                                        $produit->update(['commande_id' => $objCommande->id]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 91;
                                        if (in_array($this->_env, ['local', 'development'])) {
                                        }
                                        $this->_response['message'] = $objException->getMessage();
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }


                                //-----------------------------------------------------------------------------------------------
                                //Initiation d'un paiement
                                //-----------------------------------------------------------------------------------------------
                                $postfields = array(
                                    'phone' => $objCommande->paie_phone,
                                    'montant' => $objCommande->montant,
                                    'transactionkey' => $objCommande->ref,
                                    'apiKey' => $objListProduitsCommande["apiKey"],
                                    'secretKey' => $objListProduitsCommande["secretKey"],
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
                                    $this->_errorCode = 92;
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

                                    try {
                                        $objCmde->update(['transaction' => $resultTransaction['ref']]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 93;
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
                                            $this->_errorCode = 94;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 95;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 96;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                        foreach($listProduits as $eltProduit) {
                                            try {
                                                $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 97;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }
                                            $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                            //envoie de sms au producteur
                                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                            try{
                                                $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu. connecter vous a " .$cooll;
                                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                            } catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 98;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                    $this->_response['message'] = $objException->getMessage();
                                                }
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut cancel
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 99;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 100;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                            //Récupération de l'objet produit
                                            $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                            try{
                                                //Mettre à jour l'id commande à null dans la liste des produits
                                                $produit->update(['commande_id' => Null]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 101;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

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
                                            $this->_errorCode = 102;
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
                                                $this->_errorCode = 103;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 104;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 105;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                            foreach($listProduits as $eltProduit) {
                                                try {
                                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 106;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                                $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                                //envoie de sms au producteur
                                                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                                try{
                                                    $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu. connecter vous a " .$cooll;
                                                    $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                                } catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 107;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                        $this->_response['message'] = $objException->getMessage();
                                                    }
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
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

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 110;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 111;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 112;
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

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 115;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 116;
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
                                            $this->_errorCode = 117;
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
                                                $this->_errorCode = 118;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 119;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 120;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                            foreach($listProduits as $eltProduit) {
                                                try {
                                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 121;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                                $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                                //envoie de sms au producteur
                                                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                                try{
                                                    $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu. connecter vous a " .$cooll;
                                                    $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                                } catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 122;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                        $this->_response['message'] = $objException->getMessage();
                                                    }
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 123;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 124;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 125;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
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

                                        }elseif($resultTransaction['transaction_status'] == 'INITIATED') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 128;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 129;
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
                                                $this->_errorCode = 130;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 131;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 132;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 133;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }elseif ($resultTransaction['transaction_status'] == 'EXPIRED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut cancel
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
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

                                        foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                            //Récupération de l'objet produit
                                            $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                            try{
                                                //Mettre à jour l'id commande à null dans la liste des produits
                                                $produit->update(['commande_id' => Null]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 136;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 137;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }elseif($resultOperateur['name'] == "mtn") {

                                    $resultTransaction = $result['data']['transaction'];

                                    try {
                                        $objCmde->update(['transaction' => $resultTransaction['ref']]);
                                    }catch (Exception $objException) {
                                        DB::rollback();
                                        $this->_errorCode = 138;
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

                                        //on récupère l'objet statut_produit
                                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                        if(empty($objStatut_produit)) {
                                            $this->_errorCode = 141;
                                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                        foreach($listProduits as $eltProduit) {
                                            try {
                                                $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 142;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }
                                            $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                            //envoie de sms au producteur
                                            $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                            try{
                                                $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu. connecter vous a " .$cooll;
                                                $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                            } catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 143;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                    $this->_response['message'] = $objException->getMessage();
                                                }
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }
                                        }

                                    }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                        //-----------------------------------------------------------------------------------------------
                                        //Statut cancel
                                        //-----------------------------------------------------------------------------------------------
                                        $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                        if(empty($objStatutCmde)) {
                                            DB::rollback();
                                            $this->_errorCode = 144;
                                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        try {
                                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 145;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                        foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                            //Récupération de l'objet produit
                                            $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                            try{
                                                //Mettre à jour l'id commande à null dans la liste des produits
                                                $produit->update(['commande_id' => Null]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 146;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

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
                                            $this->_errorCode = 147;
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
                                                $this->_errorCode = 148;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 149;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            //on récupère l'objet statut_produit
                                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                                            if(empty($objStatut_produit)) {
                                                $this->_errorCode = 150;
                                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                                            foreach($listProduits as $eltProduit) {
                                                try {
                                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 151;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                                $userProducteur = User::where('id','=',$eltProduit->user_prod_id)->first();
                                                //envoie de sms au producteur
                                                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                                                try{
                                                    $msg = "Date du ".$objCmde->created_at.", DRAT : ".$userProducteur->name." ".$userProducteur->surname.", le produit ".$eltProduit->designation. " a été vendu. connecter vous a " .$cooll;
                                                    $returnSms = $userProducteur->sms($msg, $userProducteur->phone);
                                                } catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 152;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                        $this->_response['message'] = $objException->getMessage();
                                                    }
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }
                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                                            //-----------------------------------------------------------------------------------------------
                                            //Statut cancel
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 153;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 154;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            foreach($objListProduitsCommande["produits"] as $itemProduit) {

                                                //Récupération de l'objet produit
                                                $produit = Produit::where('ref', '=', $itemProduit['produit'])->first();

                                                try{
                                                    //Mettre à jour l'id commande à null dans la liste des produits
                                                    $produit->update(['commande_id' => Null]);
                                                }catch (Exception $objException) {
                                                    DB::rollback();
                                                    $this->_errorCode = 155;
                                                    if (in_array($this->_env, ['local', 'development'])) {
                                                    }
                                                    $this->_response['message'] = $objException->getMessage();
                                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                                    return response()->json($this->_response);
                                                }

                                            }

                                        }elseif($resultTransaction['transaction_status'] == 'PENDING') {

                                            //-----------------------------------------------------------------------------------------------
                                            //Statut waiting
                                            //-----------------------------------------------------------------------------------------------
                                            $objStatutCmde = Statut_cmd::where('alias', '=', 'waiting')->first();
                                            if(empty($objStatutCmde)) {
                                                DB::rollback();
                                                $this->_errorCode = 156;
                                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                            try {
                                                $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                                            }catch (Exception $objException) {
                                                DB::rollback();
                                                $this->_errorCode = 157;
                                                if (in_array($this->_env, ['local', 'development'])) {
                                                }
                                                $this->_response['message'] = $objException->getMessage();
                                                $this->_response['error_code'] = $this->prepareErrorCode();
                                                return response()->json($this->_response);
                                            }

                                        }else{
                                            DB::rollback();
                                            $this->_errorCode = 158;
                                            $this->_response['message'][] = "Statut inexistant!.";
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }

                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 159;
                                        $this->_response['message'][] = "Statut inexistant!.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json($this->_response);
                                    }

                                }else {
                                    DB::rollback();
                                    $this->_errorCode = 160;
                                    $this->_response['message'][] = "Paramètre de payment manquant.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }


                            }else {
                                DB::rollback();
                                $this->_errorCode = 161;
                                $this->_response['message'][] = "Veuillez choisir une ville!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }else {
                            DB::rollback();
                            $this->_errorCode = 162;
                            $this->_response['message'][] = "Type de livraison inexistant!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }else {
                        DB::rollback();
                        $this->_errorCode = 163;
                        $this->_response['message'][] = "Veuillez choisir un type de livraison!";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }else {
                    DB::rollback();
                    $this->_errorCode = 164;
                    $this->_response['message'][] = "Veuillez saisir un numéro de téléphone par lequel le paiement sera effectué!";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

            }else {
                DB::rollback();
                $this->_errorCode = 165;
                $this->_response['message'][] = "Veuillez entrer les produits!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollback();
            $this->_errorCode = 166;
            $this->_response['message'][] = "Veuillez choisir un mode de paiement!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        // Commit the queries!
        DB::commit();
        $toReturn = [
            /*'operateur' => $resultOperateur,
            'objet' => $objCmde,
            'statut_cmde' => $objStatutCmde,
            'transaction' => $resultTransaction,
            'sms' => $returnSms*/
        ];

        $this->_response['message'] = "Commande créée avec succès.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Fonction qui permet de checker le statut de paiement d'une commande à Orange Money
    public function checkCommandePayOrange(Request $request)
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

                $listProduits = Produit::where('commande_id','=',$objCommande->id)->get();
                foreach($listProduits as $eltProduit) {
                    try {
                        $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 9;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }

                $message = "Succès de paiement!";

            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                //-----------------------------------------------------------------------------------------------
                //Statut cancel
                //-----------------------------------------------------------------------------------------------
                $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
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

                $objListProduits = Produit::where('commande_id','=',$objCommande->id)->get();

                foreach($objListProduits as $item) {

                    //Récupération de l'objet produit
                    $produit = Produit::where('ref', '=', $item->ref)->first();

                    try{
                        //Mettre à jour l'id commande à null dans la liste des produits
                        $produit->update(['commande_id' => Null]);
                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 12;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }

                $message = "Echec de paiement!";

            }elseif($resultTransaction['transaction_status'] == 'EXPIRED') {

                $message = "La validité de paiement est expirée!";

            }else{
                DB::rollback();
                $this->_errorCode = 13;
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
            $this->_errorCode = 14;
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

    //Fonction qui permet de checker le statut de paiement d'une commande à Mtn Money
    public function checkCommandePayMtn(Request $request)
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

            }elseif($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
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

                $listProduits = Produit::where('commande_id','=',$objCommande->id)->get();
                foreach($listProduits as $eltProduit) {
                    try {
                        $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 9;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }

                $message = "Succès de paiement!";

            }elseif($resultTransaction['transaction_status'] == 'FAILED') {
                //-----------------------------------------------------------------------------------------------
                //Statut cancel
                //-----------------------------------------------------------------------------------------------
                $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
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

                $objListProduits = Produit::where('commande_id','=',$objCommande->id)->get();

                foreach($objListProduits as $item) {

                    //Récupération de l'objet produit
                    $produit = Produit::where('ref', '=', $item->ref)->first();

                    try{
                        //Mettre à jour l'id commande à null dans la liste des produits
                        $produit->update(['commande_id' => Null]);
                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 12;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }

                $message = "Echec de paiement!";

            }else{
                DB::rollback();
                $this->_errorCode = 13;
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
            $this->_errorCode = 14;
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

    //Fonction qui permet de recupérer la liste des commandes d'un customer
    public function OrdersOfCustomer()
    {
        $this->_fnErrorCode = 1;

        $objUser = Auth::user();
        if(empty($objUser)){
        if(in_array($this->_env, ['local', 'development'])){
            $this->_response['message'] = 'Cette action nécéssite une connexion.';
        }

        $this->_errorCode = 2;
        $this->_response['error_code']  = $this->prepareErrorCode();
        return response()->json( $this->_response );
        }

        //On vérifie le rôle client
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if($objRole->alias != "client") {
			$this->_errorCode = 3;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        DB::beginTransaction();

        try {

            //Récupération de toutes les commandes
            $objAllCommandes = DB::table('commandes')
            ->join('users', 'users.id', '=', 'commandes.user_client_id')
            ->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
            ->join('statut_livraisons', 'commandes.statut_livraison_id', '=', 'statut_livraisons.id')
            /* ->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id') */
            ->leftJoin('surface_partages', 'surface_partages.id', '=', 'commandes.surface_partage_id')
            ->select('commandes.ref as ref_commande',
                'commandes.mode_payment as mode_payment',
                'commandes.paie_phone as user_phone',
                'commandes.montant as montant',
                'commandes.id as id_commande',
                'commandes.cni as num_cni',
                'commandes.signature as signature',
                'commandes.lieu_livraison as lieu_livraison',
                'commandes.image_qrcode as image_qrcode',
                'statut_cmds.ref as ref_statut_cmd',
                'statut_cmds.name as name_statut_cmd',
                'statut_livraisons.ref as ref_statut_livraison',
                'statut_livraisons.name as name_statut_livraison',
                /* 'quartiers.ref as ref_quartier',
                'quartiers.name as name_quartier', */
                'surface_partages.name as name_surface',
                'surface_partages.longitude as longitude_surface',
                'surface_partages.latitude as latitude_surface',
                'surface_partages.ref as ref_surface')
            ->where('commandes.published', 1)
            ->where('commandes.user_client_id', $objUser->id)
            ->orderBy('commandes.id', 'desc')
            ->get();

        } catch (Exception $objException) {
            DB::rollback();
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $collCommandes = collect();
        foreach($objAllCommandes as $commande){

            $objCmde = Commande::where('id','=',$commande->id_commande)->first();

            if($objCmde->statut_cmd_id == 1) {//1 correspond à Waiting

                if ($objCmde->mode_payment == 'orange') {
                    //------------------------------------------------------------------------------------------------------
                    //Check de paiement Orange
                    //------------------------------------------------------------------------------------------------------
                    $postfields = array(
                        'ref_transaction' => $objCmde->transaction
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

                    if($resultTransaction['transaction_status'] == 'FAILED') {
                        //-----------------------------------------------------------------------------------------------
                        //Statut cancel
                        //-----------------------------------------------------------------------------------------------
                        $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                        if(empty($objStatutCmde)) {
                            DB::rollback();
                            $this->_errorCode = 6;
                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 7;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $objProduits = Produit::where('commande_id','=',$objCmde->id)->get();

                        foreach($objProduits as $item) {

                            //Récupération de l'objet produit
                            $produit = Produit::where('ref', '=', $item->ref)->first();

                            try{
                                //Mettre à jour l'id commande à null dans la liste des produits
                                $produit->update(['commande_id' => Null]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 8;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }


                    }


                    if($resultTransaction['transaction_status'] == 'SUCCESS') {
                        //-----------------------------------------------------------------------------------------------
                        //Statut pay
                        //-----------------------------------------------------------------------------------------------
                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                        if(empty($objStatutCmde)) {
                            DB::rollback();
                            $this->_errorCode = 9;
                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 10;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        //on récupère l'objet statut_produit
                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                        if(empty($objStatut_produit)) {
                            DB::rollback();
                            $this->_errorCode = 11;
                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                        foreach($listProduits as $eltProduit) {
                            try {
                                $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 12;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }

                    }

                }

                if ($objCmde->mode_payment == 'mtn') {
                    //------------------------------------------------------------------------------------------------------
                    //Check de paiement Mtn
                    //------------------------------------------------------------------------------------------------------
                    $postfields = array(
                        'ref_transaction' => $objCmde->transaction
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
                        $this->_errorCode = 13;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    $resultTransaction = $result['data']['objet'];

                    if($resultTransaction['transaction_status'] == 'FAILED') {
                        //-----------------------------------------------------------------------------------------------
                        //Statut cancel
                        //-----------------------------------------------------------------------------------------------
                        $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                        if(empty($objStatutCmde)) {
                            DB::rollback();
                            $this->_errorCode = 14;
                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 15;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $objProduits = Produit::where('commande_id','=',$objCmde->id)->get();

                        foreach($objProduits as $item) {

                            //Récupération de l'objet produit
                            $produit = Produit::where('ref', '=', $item->ref)->first();

                            try{
                                //Mettre à jour l'id commande à null dans la liste des produits
                                $produit->update(['commande_id' => Null]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 16;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }

                    }


                    if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                        //-----------------------------------------------------------------------------------------------
                        //Statut pay
                        //-----------------------------------------------------------------------------------------------
                        $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                        if(empty($objStatutCmde)) {
                            DB::rollback();
                            $this->_errorCode = 17;
                            $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objCmde->update(['statut_cmd_id' => $objStatutCmde->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 18;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        //on récupère l'objet statut_produit
                        $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                        if(empty($objStatut_produit)) {
                            DB::rollback();
                            $this->_errorCode = 19;
                            $this->_response['message'][] = "Le statut_produit n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $listProduits = Produit::where('commande_id','=',$objCmde->id)->get();
                        foreach($listProduits as $eltProduit) {
                            try {
                                $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 20;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                        }

                    }

                }
            }

            if ($objCmde->statut_cmd_id == 3) {//4 correspond à Cancel

                try{
                    $objListProduits = DB::table('produits')
                    ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                    ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                    ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                    ->join('unites', 'unites.id', '=', 'produits.unite_id')
                    ->select('produits.ref as ref_produit',
                        'produits.designation as designation',
                        'produits.description as description',
                        'produits.prix_produit as prix_produit',
                        'produits.type_vente as type_vente',
                        'produits.prix_min_enchere as prix_min_enchere',
                        'produits.id as id_produit',
                        'produits.qte as qte',
                        'categories.name as name_categorie',
                        'categories.ref as ref_categorie',
                        'statut_produits.ref as ref_statut_produit',
                        'statut_produits.name as name_statut_produit',
                        'unites.ref as ref_unite',
                        'unites.ref as name_unite')
                    ->where('produits.published', 1)
                    ->where('produits.commande_id', $objCmde->id)
                    ->get();

                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 21;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                if($objListProduits->isNotEmpty()) {
                    foreach ($objListProduits as $item) {
                        $myProduit = Produit::where('id','=',$item->id_produit)->first();
                        try{
                            $myProduit->update(['commande_id' => Null]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 22;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }
                }else {
                    try {
                        DB::table('commandes')->where('id', $objCmde->id)->delete();
                    } catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 23;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }

            }

            try{
                $objListProduits = DB::table('produits')
                ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                ->join('unites', 'unites.id', '=', 'produits.unite_id')
                ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
                ->select('produits.ref as ref_produit',
                    'produits.designation as designation',
                    'produits.description as description',
                    'produits.prix_produit as prix_produit',
                    'produits.type_vente as type_vente',
                    'produits.prix_min_enchere as prix_min_enchere',
                    'produits.id as id_produit',
                    'produits.qte as qte',
                    'categories.name as name_categorie',
                    'categories.ref as ref_categorie',
                    'statut_produits.ref as ref_statut_produit',
                    'statut_produits.name as name_statut_produit',
                    'unites.ref as ref_unite',
                    'unites.ref as name_unite',
                    'volumes.ref as ref_volume',
                    'volumes.name as name_volume')
                ->where('produits.published', 1)
                ->where('produits.commande_id', $objCmde->id)
                ->get();

            }catch (Exception $objException) {
                DB::rollback();
                $this->_errorCode = 24;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }


            $collProduits = collect();
            foreach ($objListProduits as $produit) {
                try {
                    $objListImgProduit = DB::table('produit_imgs')
                    ->join('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                    ->select('produit_imgs.name as image',
                        'produit_imgs.ref as image_ref')
                    ->where('produit_imgs.published', 1)
                    ->where('produit_imgs.produit_id', $produit->id_produit)
                    ->get();
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 25;
                    if (in_array($this->_env, ['local', 'development'])) {
                        $this->_response['message'] = $objException->getMessage();
                    }
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $collProduits->push(array(
                    "produit" => $produit,
                    "image" => $objListImgProduit
                ));
            }

            $collCommandes->push(array(
                "commande" => $commande,
                "list_produits" => $collProduits
            ));

        }

        DB::commit();
        $toReturn = [
            'objet' => $collCommandes
        ];
        $this->_response['message'] = "Liste de toutes les Commandes d'un client.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Affiche toutes les commandes
    public function allOrders()
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

        DB::beginTransaction();
        $collCommandes = collect();

        //On vérifie le rôle de l'utilisateur
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (in_array($objRole->alias,array('administrateur'))) {

            //Récupération des commandes
            $objAllCommandes = Commande::where('published','=',1)->orderBy('id', 'desc')->get();
            
            foreach($objAllCommandes as $commande){

                try{
                    $objCustomer = User::where('id','=',$commande->user_client_id)->first();
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 3;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try{

                    $objTypeLivraison = Type_livraison::where('id','=',$commande->type_livraison_id)->first();
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 4;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                if($commande->statut_cmd_id == 1) {//1 correspond à Waiting

                    if ($commande->mode_payment == 'orange') {
                        //------------------------------------------------------------------------------------------------------
                        //Check de paiement Orange
                        //------------------------------------------------------------------------------------------------------
                        $postfields = array(
                            'ref_transaction' => $commande->transaction
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

                        if($resultTransaction['transaction_status'] == 'FAILED') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut cancel
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 6;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 7;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objProduits = Produit::where('commande_id','=',$commande->id)->get();

                            foreach($objProduits as $item) {

                                //Récupération de l'objet produit
                                $produit = Produit::where('ref', '=', $item->ref)->first();

                                try{
                                    //Mettre à jour l'id commande à null dans la liste des produits
                                    $produit->update(['commande_id' => Null]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 8;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }

                        }


                        if($resultTransaction['transaction_status'] == 'SUCCESS') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut pay
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 9;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 10;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //on récupère l'objet statut_produit
                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                            if(empty($objStatut_produit)) {
                                DB::rollback();
                                $this->_errorCode = 11;
                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $listProduits = Produit::where('commande_id','=',$commande->id)->get();
                            foreach($listProduits as $eltProduit) {
                                try {
                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 12;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
                            }

                        }

                    }

                    if ($commande->mode_payment == 'mtn') {
                        //------------------------------------------------------------------------------------------------------
                        //Check de paiement Mtn
                        //------------------------------------------------------------------------------------------------------
                        $postfields = array(
                            'ref_transaction' => $commande->transaction
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
                            $this->_errorCode = 13;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $resultTransaction = $result['data']['objet'];

                        if($resultTransaction['transaction_status'] == 'FAILED') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut cancel
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 14;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 15;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objProduits = Produit::where('commande_id','=',$commande->id)->get();

                            foreach($objProduits as $item) {

                                //Récupération de l'objet produit
                                $produit = Produit::where('ref', '=', $item->ref)->first();

                                try{
                                    //Mettre à jour l'id commande à null dans la liste des produits
                                    $produit->update(['commande_id' => Null]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 16;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }

                        }


                        if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut pay
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 17;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 18;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //on récupère l'objet statut_produit
                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                            if(empty($objStatut_produit)) {
                                DB::rollback();
                                $this->_errorCode = 19;
                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $listProduits = Produit::where('commande_id','=',$commande->id)->get();
                            foreach($listProduits as $eltProduit) {
                                try {
                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 20;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
                            }

                        }

                    }
                }

                if ($commande->statut_cmd_id == 3) {//4 correspond à Cancel

                    try{
                        $objListProduits = DB::table('produits')
                        ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                        ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                        ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                        ->join('unites', 'unites.id', '=', 'produits.unite_id')
                        ->select('produits.ref as ref_produit',
                            'produits.designation as designation',
                            'produits.description as description',
                            'produits.prix_produit as prix_produit',
                            'produits.type_vente as type_vente',
                            'produits.prix_min_enchere as prix_min_enchere',
                            'produits.id as id_produit',
                            'produits.qte as qte',
                            'categories.name as name_categorie',
                            'categories.ref as ref_categorie',
                            'statut_produits.ref as ref_statut_produit',
                            'statut_produits.name as name_statut_produit',
                            'unites.ref as ref_unite',
                            'unites.ref as name_unite')
                        ->where('produits.published', 1)
                        ->where('produits.commande_id', $commande->id)
                        ->get();

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 21;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    if($objListProduits->isNotEmpty()) {
                        foreach ($objListProduits as $item) {
                            $myProduit = Produit::where('id','=',$item->id_produit)->first();
                            try{
                                $myProduit->update(['commande_id' => Null]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 22;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }
                    }

                    //On vérifie si une commande donc le paiement est échoué possède encore des produits
                    $allProduits = Produit::where('commande_id','=',$commande->id)->get();
                    if ($allProduits->isEmpty()) {
                        try {
                            DB::table('commandes')->where('id', $commande->id)->delete();
                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 23;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }

                }


                try{

                    $objListProduits = DB::table('produits')
                    ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                    ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                    ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                    ->join('unites', 'unites.id', '=', 'produits.unite_id')
                    ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
                    ->select('produits.ref as ref_produit',
                        'produits.designation as designation',
                        'produits.description as description',
                        'produits.prix_produit as prix_produit',
                        'produits.type_vente as type_vente',
                        'produits.prix_min_enchere as prix_min_enchere',
                        'produits.id as id_produit',
                        'produits.qte as qte',
                        'categories.name as name_categorie',
                        'categories.ref as ref_categorie',
                        'statut_produits.ref as ref_statut_produit',
                        'statut_produits.name as name_statut_produit',
                        'unites.ref as ref_unite',
                        'unites.ref as name_unite',
                        'volumes.ref as ref_volume',
                        'volumes.name as name_volume')
                    ->where('produits.published', 1)
                    ->where('produits.commande_id', $commande->id)
                    ->orderBy('produits.id','desc')
                    ->get();

                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 24;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $collProduits = collect();
                foreach ($objListProduits as $produit) {
                    try {

                        $objListImgProduit = DB::table('produit_imgs')
                        ->join('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                        ->select('produit_imgs.name as image',
                            'produit_imgs.ref as image_ref')
                        ->where('produit_imgs.published', 1)
                        ->where('produit_imgs.produit_id', $produit->id_produit)
                        ->orderBy('produit_imgs.id','desc')
                        ->get();

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 25;
                        if (in_array($this->_env, ['local', 'development'])) {
                            $this->_response['message'] = $objException->getMessage();
                        }
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    $collProduits->push(array(
                        "produit" => $produit,
                        "image" => $objListImgProduit
                    ));
                }

                $collCommandes->push(array(
                    "commande" => $commande,
                    "type_livraison" => $objTypeLivraison,
                    "user_client" => $objCustomer,
                    "list_produits" => $collProduits
                ));
            }

        }elseif (in_array($objRole->alias,array('gestionnaire-surface'))) {

            $objSurface = Surface_partage::where('id','=',$objUser->surface_partage_id)->first();

            //Récupération des commandes
            $objAllCommandes = Commande::where('surface_partage_id','=',$objSurface->id)->where('published','=',1)->orderBy('id', 'desc')->get();
            //$collCommandes = collect();
            foreach($objAllCommandes as $commande){

                try{
                    $objCustomer = User::where('id','=',$commande->user_client_id)->first();
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 26;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try{

                    $objTypeLivraison = Type_livraison::where('id','=',$commande->type_livraison_id)->first();
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 27;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                if($commande->statut_cmd_id == 1) {//1 correspond à Waiting

                    if ($commande->mode_payment == 'orange') {
                        //------------------------------------------------------------------------------------------------------
                        //Check de paiement Orange
                        //------------------------------------------------------------------------------------------------------
                        $postfields = array(
                            'ref_transaction' => $commande->transaction
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
                            $this->_errorCode = 28;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $resultTransaction = $result['data']['objet'];

                        if($resultTransaction['transaction_status'] == 'FAILED') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut cancel
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 29;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 30;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objProduits = Produit::where('commande_id','=',$commande->id)->get();

                            foreach($objProduits as $item) {

                                //Récupération de l'objet produit
                                $produit = Produit::where('ref', '=', $item->ref)->first();

                                try{
                                    //Mettre à jour l'id commande à null dans la liste des produits
                                    $produit->update(['commande_id' => Null]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 31;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }

                        }


                        if($resultTransaction['transaction_status'] == 'SUCCESS') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut pay
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 32;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 33;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //on récupère l'objet statut_produit
                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                            if(empty($objStatut_produit)) {
                                DB::rollback();
                                $this->_errorCode = 34;
                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $listProduits = Produit::where('commande_id','=',$commande->id)->get();
                            foreach($listProduits as $eltProduit) {
                                try {
                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 35;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
                            }

                        }

                    }

                    if ($commande->mode_payment == 'mtn') {
                        //------------------------------------------------------------------------------------------------------
                        //Check de paiement Mtn
                        //------------------------------------------------------------------------------------------------------
                        $postfields = array(
                            'ref_transaction' => $commande->transaction
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
                            $this->_errorCode = 36;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        $resultTransaction = $result['data']['objet'];

                        if($resultTransaction['transaction_status'] == 'FAILED') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut cancel
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'cancel')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 37;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 38;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $objProduits = Produit::where('commande_id','=',$commande->id)->get();

                            foreach($objProduits as $item) {

                                //Récupération de l'objet produit
                                $produit = Produit::where('ref', '=', $item->ref)->first();

                                try{
                                    //Mettre à jour l'id commande à null dans la liste des produits
                                    $produit->update(['commande_id' => Null]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 39;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }

                            }

                        }


                        if($resultTransaction['transaction_status'] == 'SUCCESSFUL') {
                            //-----------------------------------------------------------------------------------------------
                            //Statut pay
                            //-----------------------------------------------------------------------------------------------
                            $objStatutCmde = Statut_cmd::where('alias', '=', 'pay')->first();
                            if(empty($objStatutCmde)) {
                                DB::rollback();
                                $this->_errorCode = 40;
                                $this->_response['message'][] = "Le statut de la commande n'existe pas!";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            try {
                                $commande->update(['statut_cmd_id' => $objStatutCmde->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 41;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            //on récupère l'objet statut_produit
                            $objStatut_produit = Statut_produit::where('id', '=', 2)->first();//1:vendu
                            if(empty($objStatut_produit)) {
                                DB::rollback();
                                $this->_errorCode = 42;
                                $this->_response['message'][] = "Le statut_produit n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            $listProduits = Produit::where('commande_id','=',$commande->id)->get();
                            foreach($listProduits as $eltProduit) {
                                try {
                                    $eltProduit->update(['statut_produit_id' => $objStatut_produit->id]);
                                }catch (Exception $objException) {
                                    DB::rollback();
                                    $this->_errorCode = 43;
                                    if (in_array($this->_env, ['local', 'development'])) {
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
                            }

                        }

                    }
                }

                if ($commande->statut_cmd_id == 3) {//4 correspond à Cancel

                    try{
                        $objListProduits = DB::table('produits')
                        ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                        ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                        ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                        ->join('unites', 'unites.id', '=', 'produits.unite_id')
                        ->select('produits.ref as ref_produit',
                            'produits.designation as designation',
                            'produits.description as description',
                            'produits.prix_produit as prix_produit',
                            'produits.type_vente as type_vente',
                            'produits.prix_min_enchere as prix_min_enchere',
                            'produits.id as id_produit',
                            'produits.qte as qte',
                            'categories.name as name_categorie',
                            'categories.ref as ref_categorie',
                            'statut_produits.ref as ref_statut_produit',
                            'statut_produits.name as name_statut_produit',
                            'unites.ref as ref_unite',
                            'unites.ref as name_unite')
                        ->where('produits.published', 1)
                        ->where('produits.commande_id', $commande->id)
                        ->get();

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 44;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    if($objListProduits->isNotEmpty()) {
                        foreach ($objListProduits as $item) {
                            $myProduit = Produit::where('id','=',$item->id_produit)->first();
                            try{
                                $myProduit->update(['commande_id' => Null]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 45;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                        }
                    }

                    //On vérifie si une commande donc le paiement est échoué possède encore des produits
                    $allProduits = Produit::where('commande_id','=',$commande->id)->get();
                    if ($allProduits->isEmpty()) {
                        try {
                            DB::table('commandes')->where('id', $commande->id)->delete();
                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 46;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }

                }

                try{

                    $objListProduits = DB::table('produits')
                    ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                    ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
                    ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                    ->join('unites', 'unites.id', '=', 'produits.unite_id')
                    ->select('produits.ref as ref_produit',
                        'produits.designation as designation',
                        'produits.description as description',
                        'produits.prix_produit as prix_produit',
                        'produits.type_vente as type_vente',
                        'produits.prix_min_enchere as prix_min_enchere',
                        'produits.id as id_produit',
                        'produits.qte as qte',
                        'categories.name as name_categorie',
                        'categories.ref as ref_categorie',
                        'statut_produits.ref as ref_statut_produit',
                        'statut_produits.name as name_statut_produit',
                        'unites.ref as ref_unite',
                        'unites.ref as name_unite')
                    ->where('produits.published', 1)
                    ->where('produits.commande_id', $commande->id)
                    ->orderBy('produits.id','desc')
                    ->get();


                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 47;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                $collProduits = collect();
                foreach ($objListProduits as $produit) {
                    try {

                        $objListImgProduit = DB::table('produit_imgs')
                        ->join('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                        ->select('produit_imgs.name as image',
                            'produit_imgs.ref as image_ref')
                        ->where('produit_imgs.published', 1)
                        ->where('produit_imgs.produit_id', $produit->id_produit)
                        ->orderBy('produit_imgs.id','desc')
                        ->get();

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 48;
                        if (in_array($this->_env, ['local', 'development'])) {
                            $this->_response['message'] = $objException->getMessage();
                        }
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    $collProduits->push(array(
                        "produit" => $produit,
                        "image" => $objListImgProduit
                    ));
                }

                $collCommandes->push(array(
                    "commande" => $commande,
                    "type_livraison" => $objTypeLivraison,
                    "user_client" => $objCustomer,
                    "list_produits" => $collProduits
                ));
            }

        }else {
            DB::rollback();
            $this->_errorCode = 49;
            $this->_response['message'][] = "Vous n'êtes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();

        $toReturn = [
            'objet' => $collCommandes
        ];

        $this->_response['message'] = "Liste de toutes les Commandes.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Afficher le détail d'une commande
    public function detailOrder(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'commande' => 'string|required'
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

        $objCommande = Commande::where('ref', '=', $request->get("commande"))->first();
        if (empty($objCommande)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "La commande n'existe pas!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try{

            $objListProduits = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->select('produits.ref as ref_produit',
                'produits.designation as designation',
                'produits.description as description',
                'produits.prix_produit as prix_produit',
                'produits.type_vente as type_vente',
                'produits.prix_min_enchere as prix_min_enchere',
                'produits.id as id_produit',
                'produits.qte as qte',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'statut_produits.ref as ref_statut_produit',
                'statut_produits.name as name_statut_produit',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'volumes.ref as ref_volume',
                'volumes.name as name_volume')
            ->where('produits.published', 1)
            ->where('produits.commande_id', $objCommande->id)
            ->orderBy('produits.id','desc')
            ->get();

        }catch (Exception $objException) {
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $collProduits = collect();
        foreach ($objListProduits as $produit) {
            try {

                $objListImgProduit = DB::table('produit_imgs')
                ->join('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                ->select('produit_imgs.name as image',
                    'produit_imgs.ref as image_ref')
                ->where('produit_imgs.published', 1)
                ->where('produit_imgs.produit_id', $produit->id_produit)
                ->orderBy('produit_imgs.id','desc')
                ->get();

            }catch (Exception $objException) {
                $this->_errorCode = 5;
                if (in_array($this->_env, ['local', 'development'])) {
                    $this->_response['message'] = $objException->getMessage();
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $collProduits->push(array(
                "produit" => $produit,
                "image" => $objListImgProduit
            ));
        }

        $toReturn = [
            'objet' => $objCommande,
            'list_produit' => $collProduits
        ];
        $this->_response['message'] = "Détail de la commande.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function getStatutLivraisons()
	{
		$this->_fnErrorCode = "01";

		try {
            //Récupération liste statut_livraison
			$objStatut_livraison = DB::table('statut_livraisons')
			->select(DB::raw('statut_livraisons.id as id_statut_livraison,statut_livraisons.name as name_statut_livraison,statut_livraisons.ref as ref_statut_livraison'))
			->where('statut_livraisons.published','=',1)
			->orderBy('statut_livraisons.id','desc')
			->get();

		}catch (Exception $objException) {
			$this->_errorCode = 2;
			if(in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

		$toReturn = [
			'objet' => $objStatut_livraison
		];
		$this->_response['message'] = 'Liste des statut_livraisons.';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    public function generateQRCode($ref){

        $image = QrCode::size(300)
        ->generate('https://draht.team-solutions.net/#/detail_commande/'.$ref);

        return $image;
    }

}

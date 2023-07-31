<?php

namespace App\Http\Controllers\Api;
use App\Categorie;
use App\Commande;
use App\Statut_cmd;
use App\Volume;
use Exception;
use App\Produit;
use App\Proposition;
use App\Role;
use App\Unite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Produit_img;
use App\Statut_produit;
use App\User;
use DateTime;

class ProductApiController extends Controller
{
    public function create(Request $request)
    {
        $this->_fnErrorCode = 1;

        //On vérifie que le produit et ces images sont bien envoyés !
        $objListProduit_imgs = collect(json_decode($request->getContent(), true));
        if (empty($objListProduit_imgs)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "La liste est vide!";
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

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias,array('producteur','gestionnaire-cooperative'))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        if($request->has("categorie")) {
            $objCategorie = Categorie::where('ref', '=', $objListProduit_imgs['categorie'])->first();
            if (empty($objCategorie)) {
                $this->_errorCode = 5;
                $this->_response['message'][] = "La Catégorie n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            $this->_errorCode = 6;
            $this->_response['message'][] = "Aucune catégorie n'est fournie.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        if($request->has("unite")) {
           //on vérifie l'existance de l'objet unite
            $objUnite = Unite::where('ref', '=', $objListProduit_imgs['unite'])->first();
            if(empty($objUnite)) {
                $this->_errorCode = 7;
                $this->_response['message'][] = "L'unité n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            $this->_errorCode = 8;
            $this->_response['message'][] = "Aucune unité n'est fournie.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        if($request->has("volume")) {
            //on vérifie l'existance de l'objet unite
            $objVolume = Volume::where('ref', '=', $objListProduit_imgs['volume'])->first();
            if(empty($objVolume)) {
                $this->_errorCode = 7;
                $this->_response['message'][] = "le volume n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            $this->_errorCode = 8;
            $this->_response['message'][] = "Aucune volume n'est fournie.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //on récupère l'objet statut_produit
        $objStatut_produit = Statut_produit::where('id', '=', 1)->first();//1:vente en cours
        if(empty($objStatut_produit)) {
            $this->_errorCode = 9;
            $this->_response['message'][] = "Le statut_produit n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        // Start transaction!
        DB::beginTransaction();

        if ($request->has('type_vente')) {
            $typeVente = $objListProduit_imgs['type_vente'];
            switch ($typeVente) {
                case 'vente directe':

                    try{

                        $objProduit = new Produit();
                        $objProduit->designation = $objListProduit_imgs['designation'];
                        $objProduit->qte = $objListProduit_imgs['quantite'];
                        $objProduit->prix_produit = $objListProduit_imgs['prix_produit'];
                        $objProduit->description = $objListProduit_imgs['description'];
                        $objProduit->type_vente = $typeVente;
                        $objProduit->published = 1;
                        $objProduit->generateReference();
                        $objProduit->generateAlias($objProduit->designation);
                        $objProduit->unite()->associate($objUnite);
                        $objProduit->volume()->associate($objVolume);
                        $objProduit->statutProduit()->associate($objStatut_produit);
                        $objProduit->categorie()->associate($objCategorie);
                        if($objRole->alias == 'producteur'){$objProduit->userProducteur()->associate($objUser);}
                        if($objRole->alias == 'gestionnaire-cooperative'){$objProduit->userGestionnaireCoop()->associate($objUser);}
                        $objProduit->save();

                    }catch(Exception $objException){
                        DB::rollback();
                        $this->_errorCode = 7;
                        if (in_array($this->_env, ['local', 'development'])){
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json( $this->_response );
                    }

                    //----------------------------------------------------------------------------
                    //Création de l'image de produit
                    if($request->has('images')){

                        foreach ($objListProduit_imgs['images'] as $objImg) {
                            $image = $objImg['image'];  // your base64 encoded

                            $extension = explode('/', mime_content_type($image))[1];

                            $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                            $image = str_replace(' ', '+', $image);
                            $imageName = $objProduit->ref.'_'.str_random(10) . '.'.$extension;

                            if (Storage::disk('produit')->put($imageName, base64_decode($image))){
                                try{

                                    $objProduitImg = new Produit_img();
                                    $objProduitImg->name = 'api/drat-api/storage/app/public/images/produit/'.$imageName;
                                    $objProduitImg->produit()->associate($objProduit);
                                    $objProduitImg->published = 1;
                                    $objProduitImg->generateAlias($objProduit->designation);
                                    $objProduitImg->generateReference();
                                    $objProduitImg->save();

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
                                }
                                $this->_response['message'] ="Echec dans l'enregistrement de image.";
                                $this->_response['error_code']  = $this->prepareErrorCode();
                                return response()->json( $this->_response );
                            }
                        }

                    }else{
                        DB::rollback();
                        $this->_errorCode = 10;
                        $this->_response['message'][] = "Il manque l'image deu produit!";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    break;
                case 'vente aux encheres':
                    $typeVente = $objListProduit_imgs['type_vente'];
                    try{

                        $objProduit = new Produit();
                        $objProduit->designation = $objListProduit_imgs['designation'];
                        $objProduit->qte = $objListProduit_imgs['quantite'];
                        //$objProduit->prix_produit = $objListProduit_imgs['prix_produit'];
                        $objProduit->description = $objListProduit_imgs['description'];
                        $objProduit->type_vente = $typeVente;
                        $objProduit->published = 1;
                        $objProduit->delai = '5 min';
                        if($request->has('prix_min_enchere')){
                            $objProduit->prix_min_enchere = $objListProduit_imgs['prix_min_enchere'];
                        }else{
                            DB::rollback();
                            $this->_errorCode = 11;
                            $this->_response['message'][] = "Le prix minimal d'enchère n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                        if($request->has('begin_date')){
                            $objProduit->begin_date = new DateTime($objListProduit_imgs['begin_date']);
                        }else{
                            DB::rollback();
                            $this->_errorCode = 12;
                            $this->_response['message'][] = "La date de debut d'enchère n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                        if($request->has('end_date')){
                            $objProduit->end_date = new DateTime($objListProduit_imgs['end_date']);
                        }else{
                            DB::rollback();
                            $this->_errorCode = 13;
                            $this->_response['message'][] = "La date de fin d'enchère n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                        $objProduit->generateReference();
                        $objProduit->generateAlias($objProduit->designation);
                        $objProduit->unite()->associate($objUnite);
                        $objProduit->volume()->associate($objVolume);
                        $objProduit->categorie()->associate($objCategorie);
                        $objProduit->statutProduit()->associate($objStatut_produit);
                        if($objRole->alias == 'producteur'){$objProduit->userProducteur()->associate($objUser);}
                        if($objRole->alias == 'gestionnaire-cooperative'){$objProduit->userGestionnaireCoop()->associate($objUser);}
                        $objProduit->save();

                    }catch(Exception $objException){
                        DB::rollback();
                        $this->_errorCode = 14;
                        if (in_array($this->_env, ['local', 'development'])){
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json( $this->_response );
                    }

                    //----------------------------------------------------------------------------
                    //Création de l'image de produit
                    if($request->has('images')){

                       /*$image = $request->get('image');  // your base64 encoded

                        $extension = explode('/', mime_content_type($request->get('image')))[1];

                        $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                        $image = str_replace(' ', '+', $image);
                        $imageName = $objProduit->ref.'_'.str_random(10) . '.'.$extension;

                        if (Storage::disk('produit')->put($imageName, base64_decode($image))){
                            try{

                                $objProduitImg = new Produit_img();
                                $objProduitImg->name = 'drat-api/storage/app/public/images/produit/'.$imageName;
                                $objProduitImg->produit()->associate($objProduit);
                                $objProduitImg->published = 1;
                                $objProduitImg->generateAlias($objProduit->designation);
                                $objProduitImg->generateReference();
                                $objProduitImg->save();

                            }catch(Exception $objException){
                                DB::rollback();
                                $this->_errorCode = 15;
                                if (in_array($this->_env, ['local', 'development'])){
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json( $this->_response );
                            }

                        }else{
                            DB::rollback();
                            $this->_errorCode = 16;
                            if (in_array($this->_env, ['local', 'development'])){
                                $this->_response['message'] ="Echec dans l'enregistrement de image.";
                            }
                            $this->_response['error_code']  = $this->prepareErrorCode();
                            return response()->json( $this->_response );
                        }*/

                        foreach ($objListProduit_imgs['images'] as $objImg) {

                            $image = $objImg['image'];  // your base64 encoded

                            $extension = explode('/', mime_content_type($image))[1];

                            $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                            $image = str_replace(' ', '+', $image);
                            $imageName = $objProduit->ref.'_'.str_random(10) . '.'.$extension;

                            if (Storage::disk('produit')->put($imageName, base64_decode($image))){
                                try{

                                    $objProduitImg = new Produit_img();
                                    $objProduitImg->name = 'api/drat-api/storage/app/public/images/produit/'.$imageName;
                                    $objProduitImg->produit()->associate($objProduit);
                                    $objProduitImg->published = 1;
                                    $objProduitImg->generateAlias($objProduit->designation);
                                    $objProduitImg->generateReference();
                                    $objProduitImg->save();

                                }catch(Exception $objException){
                                    DB::rollback();
                                    $this->_errorCode = 15;
                                    if (in_array($this->_env, ['local', 'development'])){
                                    }
                                    $this->_response['message'] = $objException->getMessage();
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json( $this->_response );
                                }

                            }else{
                                DB::rollback();
                                $this->_errorCode = 16;
                                if (in_array($this->_env, ['local', 'development'])){
                                }
                                $this->_response['message'] ="Echec dans l'enregistrement de image.";
                                $this->_response['error_code']  = $this->prepareErrorCode();
                                return response()->json( $this->_response );
                            }
                        }

                    }else{
                        DB::rollback();
                        $this->_errorCode = 17;
                        $this->_response['message'][] = "Il manque l'image de produit!";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    break;
                default:
                    DB::rollback();
                    $this->_errorCode = 18;
                    $this->_response['message'][] = "Type de vente inexistant.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                    break;
            }
        }else {
            DB::rollback();
            $this->_errorCode = 19;
            $this->_response['message'][] = "Veuillez entrer un type de vente.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();

        $toReturn = [
            'objet'=> $objProduit
        ];

        $this->_response['message'] = 'Enregistrement reussi.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /**********************************************************
     * Récupère les produits en vente directe
     * ********************************************************/
    public function viewAllProductsForDirectSale()
    {
        $this->_fnErrorCode = "01";
        try {

            //Récupération de tous les produits
            $objAllProducts = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->leftJoin('users', 'users.id', '=', 'produits.user_prod_id')
            ->leftJoin('users as gestionnaire_coop', 'gestionnaire_coop.id', '=', 'produits.user_coop_id')
            ->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')
            ->select('produits.ref as ref_produit',
                'produits.id as id_produit',
                'produits.designation as designation',
                'produits.prix_produit as prix_produit',
                'produits.qte as qte_produit',
                'produits.description as description',
                'produits.published as published_produit',
                'produits.type_vente as type_vente_produit',
                'produits.commande_id as commande_id',
                'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                'statut_produits.ref as ref_statut_produit',
                'statut_produits.name as name_statut_produit',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'volumes.ref as ref_volume',
                'volumes.name as name_volume',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'users.ref as ref_producteur',
                'users.name as name_producteur',
                'users.surname as surname_producteur',
                'gestionnaire_coop.ref as ref_gestionnaire_coop',
                'gestionnaire_coop.name as name_gestionnaire_coop',
                'gestionnaire_coop.surname as surname_gestionnaire_coop')
            ->where('produits.type_vente','=','vente directe')
            ->where('produits.published','=',1)
            ->orderBy('produits.id','desc')
            ->get();

        } catch (Exception $objException) {
            $this->_errorCode = 2;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $productsList = collect();
        $productsInCmd = array();
        foreach ($objAllProducts as $objProduct) {
              $objCommande = Commande::where('id', '=', $objProduct->commande_id)->first();
              $statutCmd = Statut_cmd::where('alias','=','waiting')->first();
            if($objProduct->commande_id == null || $objCommande->statut_cmd_id == $statutCmd->id){

              try {
                  //Récupération de tous les images d'un produit
                  $objImageProduit = DB::table('produit_imgs')
                      ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                      ->select('produit_imgs.ref as ref_produit_img',
                          'produit_imgs.name as image',
                          'produit_imgs.published as published_produit_img')
                      ->where('produit_imgs.produit_id','=',$objProduct->id_produit)
                      ->orderBy('produit_imgs.id','desc')
                      ->get();

                  $productsList->push(array(
                      'produit'=>$objProduct,
                      'image'=>$objImageProduit
                  ));

              } catch (Exception $objException) {
                  $this->_errorCode = 3;
                  if (in_array($this->_env, ['local', 'development'])) {
                  }
                  $this->_response['message'] = $objException->getMessage();
                  $this->_response['error_code'] = $this->prepareErrorCode();
                  return response()->json($this->_response);
              }

            }

        }

        $toReturn = [
            'objet' => $productsList
        ];

        $this->_response['message'] = "Liste des produits d'une vente directe.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /***********************************************************
     * Récupère les produits au enchère
     * **********************************************************/
    public function viewAllProductsForAuction()
    {
        $this->_fnErrorCode = "01";
        try {

            //Récupération de tous les produits
            $objAllProducts = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->leftJoin('users', 'users.id', '=', 'produits.user_prod_id')
            ->leftJoin('users as gestionnaire_coop', 'gestionnaire_coop.id', '=', 'produits.user_coop_id')
            ->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')
            ->select('produits.ref as ref_produit',
                'produits.id as id_produit',
                'produits.designation as designation',
                'produits.prix_produit as prix_produit',
                'produits.prix_min_enchere as prix_min_enchere',
                'produits.qte as qte_produit',
                'produits.description as description',
                'produits.published as published_produit',
                'produits.type_vente as type_vente_produit',
                'produits.commande_id as commande_id',
                'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                'produits.begin_date as begin_date',
                'produits.end_date as end_date',
                'statut_produits.ref as ref_statut_produit',
                'statut_produits.name as name_statut_produit',
                'volumes.ref as ref_volume',
                'volumes.name as name_volume',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'users.ref as ref_producteur',
                'users.name as name_producteur',
                'users.surname as surname_producteur',
                'gestionnaire_coop.ref as ref_gestionnaire_coop',
                'gestionnaire_coop.name as name_gestionnaire_coop',
                'gestionnaire_coop.surname as surname_gestionnaire_coop')
            ->where('produits.type_vente','=','vente aux encheres')
            ->where('produits.published','=',1)
            ->where('produits.commande_id','=',Null)
            ->orderBy('produits.id','desc')
            ->paginate(10);

        } catch (Exception $objException) {
            $this->_errorCode = 2;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $productsList = collect();
        foreach ($objAllProducts as $objProduct) {
            $objProposition = Proposition::where('produit_id','=',$objProduct->id_produit)->max('prix_proposition');
            try {
                //Récupération de tous les images d'un produit
                $objImageProduit = DB::table('produit_imgs')
                ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                ->select('produit_imgs.ref as ref_produit_img',
                    'produit_imgs.name as image',
                    'produit_imgs.published as published_produit_img')
                ->where('produit_imgs.produit_id','=',$objProduct->id_produit)
                ->orderBy('produit_imgs.id','desc')
                ->get();

                $productsList->push(array(
                    'produit'=>$objProduct,
                    'image'=>$objImageProduit,
                    'max_proposition'=>$objProposition
                ));

            } catch (Exception $objException) {
                $this->_errorCode = 3;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }


        $toReturn = [
            'objet' => $productsList
        ];

        $this->_response['message'] = "Liste des produits à l'enchère.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /*************************************************************
     * Afficher les détails d'un produit
     * ************************************************************/
	public function viewDetailProduct(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'produit' => 'string|required'
		]);

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

		$objProduit = Produit::where('ref', '=', $request->get('produit'))->where('published','=',1)->first();
        if (empty($objProduit)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "Le produit n'existe pas!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		try
		{
            //Récupération de tous les produits
            $objDetailProduct = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->leftJoin('users', 'users.id', '=', 'produits.user_prod_id')
            ->leftJoin('users as gestionnaire_coop', 'gestionnaire_coop.id', '=', 'produits.user_coop_id')
            ->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')
            ->select('produits.ref as ref_produit',
                'produits.id as id_produit',
                'produits.designation as designation',
                'produits.prix_produit as prix_produit',
                'produits.qte as qte_produit',
                'produits.description as description',
                'produits.published as published_produit',
                'produits.type_vente as type_vente_produit',
                'produits.commande_id as commande_id',
                'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                'produits.prix_min_enchere as prix_min_enchere',
                'produits.statut as statut',
                'produits.begin_date as begin_date_enchere',
                'produits.end_date as end_date_enchere',
                'statut_produits.ref as ref_statut_produit',
                'statut_produits.name as name_statut_produit',
                'volumes.ref as ref_volume',
                'volumes.name as name_volume',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'users.ref as ref_producteur',
                'users.name as name_producteur',
                'users.surname as surname_producteur',
                'gestionnaire_coop.ref as ref_gestionnaire_coop',
                'gestionnaire_coop.name as name_gestionnaire_coop',
                'gestionnaire_coop.surname as surname_gestionnaire_coop')
            ->where('produits.id','=',$objProduit->id)
            ->first();

		}catch (Exception $objException) {
			$this->_errorCode = 4;
			if(in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

        try {

            //Récupération de tous les images d'un produit
            $objImageProduit = DB::table('produit_imgs')
            ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
            ->select('produit_imgs.ref as ref_produit_img',
                'produit_imgs.name as image',
                'produit_imgs.published as published_produit_img')
            ->where('produit_imgs.produit_id','=',$objDetailProduct->id_produit)
            ->orderBy('produit_imgs.id','desc')
            ->get();

        } catch (Exception $objException) {
            $this->_errorCode = 5;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


		$toReturn = [
			'produit'=>$objDetailProduct,
            'image'=>$objImageProduit
		];

		$this->_response['message'] = "Détail d'un produit.";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    /**************************************************************
     * Afficher la liste des produits d'un producteur
     * ************************************************************/
    public function viewAllProductsOfProducer()
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

        //On vérifie que l'utilisateur est bien producteur
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias,array('producteur'))) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {

            //Récupération de tous les produits
            $objAllProducts = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->join('users', 'users.id', '=', 'produits.user_prod_id')
            ->join('cooperatives', 'cooperatives.id', '=', 'users.cooperative_id')
            ->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')
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
                'volumes.ref as ref_volume',
                'volumes.name as name_volume',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'users.ref as ref_producteur',
                'users.name as name_producteur',
                'users.surname as surname_producteur',
                'cooperatives.ref as ref_cooperative',
                'cooperatives.name as name_cooperative')
            ->where('produits.user_prod_id','=',$objUser->id)
            ->where('produits.published','=',1)
            ->orderBy('produits.id','desc')
            ->paginate(10);

        } catch (Exception $objException) {
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $productsList = collect();
        foreach ($objAllProducts as $objProduct) {
            try {

                //Récupération de tous les images d'un produit
                $objImageProduit = DB::table('produit_imgs')
                ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                ->select('produit_imgs.ref as ref_produit_img',
                    'produit_imgs.name as image',
                    'produit_imgs.published as published_produit_img')
                ->where('produit_imgs.produit_id','=',$objProduct->id_produit)
                ->orderBy('produit_imgs.id','desc')
                ->get();

                $productsList->push(array(
                    'produit'=>$objProduct,
                    'image'=>$objImageProduit
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


        $toReturn = [
            'objet' => $productsList
        ];

        $this->_response['message'] = "Liste des produits d'un producteur.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /****************************************************************
     * Afficher la liste des produits d'une cooperative
     * **************************************************************/
    public function viewAllProductsOfCooperative()
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

        //On vérifie que l'utilisateur est bien producteur
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if (!in_array($objRole->alias,array('gestionnaire-cooperative'))) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objCooperative = $objUser->cooperative;
        if(empty($objCooperative)) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "La cooperative n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUsersCooperative = User::where('cooperative_id','=',$objCooperative->id)->where('role_id','=',7)->get();
        if(empty($objUsersCooperative)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Les producteurs n'existent pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $producteursList = collect();
        foreach ($objUsersCooperative as $objUserCooperative) {
            try {

                //Récupération de tous les produits
                $objAllProducts = DB::table('produits')
                ->join('categories', 'categories.id', '=', 'produits.categorie_id')
                ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
                ->join('unites', 'unites.id', '=', 'produits.unite_id')
                ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
                ->join('users', 'users.id', '=', 'produits.user_prod_id')
                ->join('cooperatives', 'cooperatives.id', '=', 'users.cooperative_id')
                /*->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
                ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')*/
                ->select('produits.ref as ref_produit',
                    'produits.id as id_produit',
                    'produits.designation as designation',
                    'produits.prix_produit as prix_produit',
                    'produits.qte as qte_produit',
                    'produits.description as description',
                    'produits.published as published_produit',
                    'produits.type_vente as type_vente_produit',
                    'produits.commande_id as commande_id',
                    'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                    'produits.prix_min_enchere as prix_min_enchere',
                    'produits.statut as statut',
                    'produits.begin_date as begin_date_enchere',
                    'produits.end_date as end_date_enchere',
                    'statut_produits.ref as ref_statut_produit',
                    'statut_produits.name as name_statut_produit',
                    'volumes.ref as ref_volume',
                    'volumes.name as name_volume',
                    'unites.ref as ref_unite',
                    'unites.name as name_unite',
                    'categories.ref as ref_categorie',
                    'categories.name as name_categorie',
                    'users.ref as ref_producteur',
                    'users.name as name_producteur',
                    'users.surname as surname_producteur',
                    'cooperatives.ref as ref_cooperative',
                    'cooperatives.name as name_cooperative')
                ->where('produits.user_prod_id','=',$objUserCooperative->id)
                ->where('produits.published','=',1)
                ->orderBy('produits.id','desc')
                ->paginate(10);

            } catch (Exception $objException) {
                $this->_errorCode = 6;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $productsList = collect();
            foreach ($objAllProducts as $objProduct) {
                try {

                    //Récupération de tous les images d'un produit
                    $objImageProduit = DB::table('produit_imgs')
                    ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                    ->select('produit_imgs.ref as ref_produit_img',
                        'produit_imgs.name as image',
                        'produit_imgs.published as published_produit_img')
                    ->where('produit_imgs.produit_id','=',$objProduct->id_produit)
                    ->orderBy('produit_imgs.id','desc')
                    ->get();

                    $productsList->push(array(
                        'produit'=>$objProduct,
                        'image'=>$objImageProduit
                    ));

                } catch (Exception $objException) {
                    $this->_errorCode = 7;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }

            $producteursList->push(array(
                'producteur' => $objUserCooperative,
                'list_produit' => $productsList
            ));
        }

        $toReturn = [
            'objet' => $producteursList
        ];

        $this->_response['message'] = "Liste des producteurs d'une cooperative et leur produit.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /***************************************************************
     * Afficher la liste des produits en vente directe par categorie
     * ***************************************************************/
    public function viewAllProductsForDirectSaleByCategory(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'categorie'=>'string|required'
        ]);

        if($validator->fails()){
            if(!empty($validator->errors()->all())){
                foreach($validator->errors()->all() as $error){
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objCategorie = Categorie::where('ref', '=', $request->get("categorie"))->first();
        if (empty($objCategorie)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "La catégorie n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {

            //Récupération de tous les produits
            $objAllProducts = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->leftJoin('users', 'users.id', '=', 'produits.user_prod_id')
            ->leftJoin('users as gestionnaire_coop', 'gestionnaire_coop.id', '=', 'produits.user_coop_id')
            ->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')
            ->select('produits.ref as ref_produit',
                'produits.id as id_produit',
                'produits.designation as designation',
                'produits.prix_produit as prix_produit',
                'produits.qte as qte_produit',
                'produits.description as description',
                'produits.published as published_produit',
                'produits.type_vente as type_vente_produit',
                'produits.commande_id as commande_id',
                'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                'statut_produits.ref as ref_statut_produit',
                'statut_produits.name as name_statut_produit',
                'volumes.ref as ref_volume',
                'volumes.name as name_volume',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'users.ref as ref_producteur',
                'users.name as name_producteur',
                'users.surname as surname_producteur',
                'gestionnaire_coop.ref as ref_gestionnaire_coop',
                'gestionnaire_coop.name as name_gestionnaire_coop',
                'gestionnaire_coop.surname as surname_gestionnaire_coop')
            ->where('produits.type_vente','=','vente directe')
            ->where('produits.categorie_id', $objCategorie->id)
            ->where('produits.published','=',1)
            ->orderBy('produits.id','desc')
            ->paginate(10);

        } catch (Exception $objException) {
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $productsList = collect();
        foreach ($objAllProducts as $objProduct) {
            try {

                //Récupération de tous les images d'un produit
                $objImageProduit = DB::table('produit_imgs')
                ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                ->select('produit_imgs.ref as ref_produit_img',
                    'produit_imgs.name as image',
                    'produit_imgs.published as published_produit_img')
                ->where('produit_imgs.produit_id','=',$objProduct->id_produit)
                ->orderBy('produit_imgs.id','desc')
                ->get();

                $productsList->push(array(
                    'produit'=>$objProduct,
                    'image'=>$objImageProduit
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

        $toReturn = [
            'objet' => $productsList
        ];

        $this->_response['message'] = "Liste des produits en vente directe pour une catégorie.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    /*********************************************************************
     * Afficher la liste des produits en vente à l’enchère par categorie
     * *******************************************************************/
    public function viewAllProductsForAuctionByCategory(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'categorie'=>'string|required'
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

        $objCategorie = Categorie::where('ref', '=', $request->get("categorie"))->first();
        if (empty($objCategorie)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "La catégorie n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {

            //Récupération de tous les produits
            $objAllProducts = DB::table('produits')
            ->join('categories', 'categories.id', '=', 'produits.categorie_id')
            ->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
            ->join('volumes', 'volumes.id', '=', 'produits.volume_id')
            ->join('unites', 'unites.id', '=', 'produits.unite_id')
            ->leftJoin('users', 'users.id', '=', 'produits.user_prod_id')
            ->leftJoin('users as gestionnaire_coop', 'gestionnaire_coop.id', '=', 'produits.user_coop_id')
            ->leftJoin('commandes', 'commandes.id', '=', 'produits.commande_id')
            ->leftJoin('detail_bon_cmds', 'detail_bon_cmds.id', '=', 'produits.detail_bon_cmd_id')
            ->select('produits.ref as ref_produit',
                'produits.id as id_produit',
                'produits.designation as designation',
                'produits.prix_produit as prix_produit',
                'produits.qte as qte_produit',
                'produits.description as description',
                'produits.prix_min_enchere as prix_min_enchere',
                'produits.begin_date as begin_date',
                'produits.end_date as end_date',
                'produits.published as published_produit',
                'produits.type_vente as type_vente_produit',
                'produits.commande_id as commande_id',
                'produits.detail_bon_cmd_id as detail_bon_cmd_id',
                'statut_produits.ref as ref_statut_produit',
                'statut_produits.name as name_statut_produit',
                'volumes.ref as ref_volume',
                'volumes.name as name_volume',
                'unites.ref as ref_unite',
                'unites.name as name_unite',
                'categories.ref as ref_categorie',
                'categories.name as name_categorie',
                'users.ref as ref_producteur',
                'users.name as name_producteur',
                'users.surname as surname_producteur',
                'gestionnaire_coop.ref as ref_gestionnaire_coop',
                'gestionnaire_coop.name as name_gestionnaire_coop',
                'gestionnaire_coop.surname as surname_gestionnaire_coop')
            ->where('produits.type_vente','=','vente aux encheres')
            ->where('produits.categorie_id', $objCategorie->id)
            ->where('produits.published','=',1)
            ->orderBy('produits.id','desc')
            ->paginate(10);

        } catch (Exception $objException) {
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $productsList = collect();
        foreach ($objAllProducts as $objProduct) {

            try {

                //Récupération de tous les images d'un produit
                $objImageProduit = DB::table('produit_imgs')
                ->leftJoin('produits', 'produits.id', '=', 'produit_imgs.produit_id')
                ->select('produit_imgs.ref as ref_produit_img',
                    'produit_imgs.name as image',
                    'produit_imgs.published as published_produit_img')
                ->where('produit_imgs.produit_id','=',$objProduct->id_produit)
                ->orderBy('produit_imgs.id','desc')
                ->get();

                $productsList->push(array(
                    'produit'=>$objProduct,
                    'image'=>$objImageProduit
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

        $toReturn = [
            'objet' => $productsList
        ];

        $this->_response['message'] = "Liste des produits à l'enchère pour une catégorie.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function update(Request $request)
    {
        $this->_fnErrorCode = 1;

        $objListProduit = collect(json_decode($request->getContent(), true));
        if (empty($objListProduit)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "il y'a aucun produit a modifier  dans la liste!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUser = Auth::user();
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
            }

            $this->_response['message'] = 'Cette action nécéssite une connexion.';
            $this->_errorCode = 3;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien admin|gestionnaire
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if(!in_array($objRole->alias,array('producteur','gestionnaire-cooperative'))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objProduct = Produit::where('ref', '=', $objListProduit['produit'])->first();
        if (empty($objProduct)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le produit n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();
        
        if($objProduct->commande_id == null) {

            if($objListProduit->has('type_vente')) {

                if ($objListProduit['type_vente'] == 'vente directe') {

                    if($objListProduit->has("designation")){
                        try {
                            $objProduct->update([
                                "designation" => $objListProduit["designation"]
                            ]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 6;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }

                    if($objListProduit->has("description")){

                        try {
                            $objProduct->update(["description" => $objListProduit["description"]]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 7;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }

                    if($objListProduit->has("prix_produit")){

                        try {
                            $objProduct->update(["prix_produit" => $objListProduit["prix_produit"]]);
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

                    if($request->has("quantite")){

                        try {
                            $objProduct->update(["qte" => $objListProduit["quantite"]]);
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

                    if($objListProduit->has("categorie")){

                        $objCategorie = Categorie::where('ref', '=', $objListProduit["categorie"])->first();
                        if (empty($objCategorie)) {
                            DB::rollback();
                            $this->_errorCode = 10;
                            $this->_response['message'][] = "La Categorie n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objProduct->update(["categorie_id" => $objCategorie->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 11;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }

                    if($objListProduit->has("unite")){

                        $objUnite = Unite::where('ref', '=', $objListProduit["unite"])->first();
                        if (empty($objUnite)) {
                            $this->_errorCode = 12;
                            $this->_response['message'][] = "L'unité n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objProduct->update(["unite_id" => $objUnite->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 13;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }
                    if($objListProduit->has("volume")){

                        $objVolume = Volume::where('ref', '=', $objListProduit["volume"])->first();
                        if (empty($objVolume)) {
                            $this->_errorCode = 14;
                            $this->_response['message'][] = "L'unité n'existe pas.";
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try {
                            $objProduct->update(["volume_id" => $objVolume->id]);
                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 15;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }

                    //----------------------------------------------------------------------------
                    //Modification de l'image d'un produit'
                    //----------------------------------------------------------------------------
                    if($objListProduit->has('ref_image')){
                        foreach ($objListProduit['ref_image'] as $img){
                            $objProduitImg = Produit_img::where('ref', '=', $img["ref_image"])->first();
                            if (empty($objProduitImg)) {
                                $this->_errorCode = 16;
                                $this->_response['message'][] = "L'image n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }

                            if($objListProduit->has('name_image')){
                                $image = $img['name_image'];  // your base64 encoded
                                $extension = explode('/', mime_content_type($img['name_image']))[1];

                                $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                                $image = str_replace(' ', '+', $image);
                                $imageName = $objProduct->ref.'_'.str_random(10) . '.'.$extension;

                                if (Storage::disk('produit')->put($imageName, base64_decode($image))){
                                    try {
                                        $objProduitImg->update(["name" => 'api/drat-api/storage/app/public/images/produit/'.$imageName]);
                                    }catch (Exception $objException) {
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
                                    if (in_array($this->_env, ['local', 'development'])){
                                    }
                                    $this->_response['message'] = "Echec de la modificaton de l'image.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json( $this->_response );
                                }
                            }
                        }
                    }

                }

                if($objListProduit['type_vente'] == 'vente au enchere') {
                    
                    date_default_timezone_set('Africa/Douala');
                    $date_now = date('Y-m-d H:i:s', time());
                    $timestamp_courant_date = strtotime($date_now);

                    $begin_date = $objListProduit["begin_date"];
                    $timestamp_begin_date = strtotime($begin_date);

                    $end_date = $objListProduit["end_date"];

                    if ($timestamp_begin_date >= $timestamp_courant_date) {

                        if($objListProduit->has("designation")){

                            try {
                                $objProduct->update(["designation" => $objListProduit["designation"]]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 19;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                        }
    
                        if($objListProduit->has("description")){
    
                            try {
                                $objProduct->update(["description" => $objListProduit["description"]]);
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
    
                        if($objListProduit->has("prix_produit")){
    
                            try {
                                $objProduct->update(["prix_produit" => $objListProduit["prix_produit"]]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 21;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                        }
    
                        if($objListProduit->has("quantite")){
    
                            try {
                                $objProduct->update(["qte" => $objListProduit["quantite"]]);
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
    
                        if($objListProduit->has("categorie")){
    
                            $objCategorie = Categorie::where('ref', '=', $objListProduit["categorie"])->first();
                            if (empty($objCategorie)) {
                                DB::rollback();
                                $this->_errorCode = 23;
                                $this->_response['message'][] = "La Categorie n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
                            try {
                                $objProduct->update(["categorie_id" => $objCategorie->id]);
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
    
                        if($objListProduit->has("unite")){
    
                            $objUnite = Unite::where('ref', '=', $objListProduit["unite"])->first();
                            if (empty($objUnite)) {
                                $this->_errorCode = 25;
                                $this->_response['message'][] = "L'unité n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                            try {
                                $objProduct->update(["unite_id" => $objUnite->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 26;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                        }

                        if($objListProduit->has("volume")){
    
                            $objVolume = Volume::where('ref', '=', $objListProduit["volume"])->first();
                            if (empty($objVolume)) {
                                $this->_errorCode = 27;
                                $this->_response['message'][] = "L'unité n'existe pas.";
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                            try {
                                $objProduct->update(["volume_id" => $objVolume->id]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 28;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                        }
    
                        //----------------------------------------------------------------------------
                        //Modification de l'image d'un produit'
                        //----------------------------------------------------------------------------
                        if($objListProduit->has('ref_image')){
                            foreach ($objListProduit['ref_image'] as $img){
                                $objProduitImg = Produit_img::where('ref', '=', $img["ref_image"])->first();
                                if (empty($objProduitImg)) {
                                    $this->_errorCode = 29;
                                    $this->_response['message'][] = "L'image n'existe pas.";
                                    $this->_response['error_code'] = $this->prepareErrorCode();
                                    return response()->json($this->_response);
                                }
    
                                if($img->has('name_image')){
                                    $image = $img['name_image'];  // your base64 encoded
                                    $extension = explode('/', mime_content_type($img['name_image']))[1];
    
                                    $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                                    $image = str_replace(' ', '+', $image);
                                    $imageName = $objProduct->ref.'_'.str_random(10) . '.'.$extension;
    
                                    if (Storage::disk('produit')->put($imageName, base64_decode($image))){
                                        try {
                                            $objProduitImg->update(["name" => 'api/drat-api/storage/app/public/images/produit/'.$imageName]);
                                        }catch (Exception $objException) {
                                            DB::rollback();
                                            $this->_errorCode = 30;
                                            if (in_array($this->_env, ['local', 'development'])) {
                                            }
                                            $this->_response['message'] = $objException->getMessage();
                                            $this->_response['error_code'] = $this->prepareErrorCode();
                                            return response()->json($this->_response);
                                        }
    
                                    }else{
                                        DB::rollback();
                                        $this->_errorCode = 31;
                                        if (in_array($this->_env, ['local', 'development'])){
                                        }
                                        $this->_response['message'] = "Echec de la modificaton de l'image.";
                                        $this->_response['error_code'] = $this->prepareErrorCode();
                                        return response()->json( $this->_response );
                                    }
                                }
                            }
                        }
    
                        if($objListProduit->has("prix_min_enchere")){
    
                            try {
                                $objProduct->update(["prix_min_enchere" => $objListProduit["prix_min_enchere"]]);
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
    
                        if($objListProduit->has("begin_date")){
    
                            try {
                                $objProduct->update(["begin_date" => $begin_date]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 33;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                        }
    
                        if($objListProduit->has("end_date")){
    
                            try {
                                $objProduct->update(["end_date" => $end_date]);
                            }catch (Exception $objException) {
                                DB::rollback();
                                $this->_errorCode = 34;
                                if (in_array($this->_env, ['local', 'development'])) {
                                }
                                $this->_response['message'] = $objException->getMessage();
                                $this->_response['error_code'] = $this->prepareErrorCode();
                                return response()->json($this->_response);
                            }
    
                        }

                    }else {
                        DB::rollback();
                        $this->_errorCode = 35;
                        $this->_response['message'][] = "Le produit ne peut être modifié car il est déjà au enchère ou a déjà été au enchère.";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }

            }else {
                DB::rollback();
                $this->_errorCode = 36;
                $this->_response['message'][] = "Type de vente inexistant.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollback();
            $this->_errorCode = 37;
            $this->_response['message'][] = "Impossible de modifier le produit.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        

        DB::commit();

        $toReturn = [
            'objet'=> $objProduct
        ];

        $this->_response['message'] = 'Modification reussi.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Gestionnaire cooperative confirme la reception d'un produit dans sa cooperative
    public function confirmRecieptProduct(Request $request)
    {
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
        if(empty($objUser)){
            if(in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = 'Cette action nécéssite une connexion.';
            }

            $this->_errorCode = 3;
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        //On vérifie que l'utilisateur est bien admin|gestionnaire
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if(!in_array($objRole->alias,array('gestionnaire-cooperative'))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objProduct = Produit::where('ref', '=', $request->get("ref_produit"))->where('published','=',1)->first();
        if (empty($objProduct)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le produit n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        if($objProduct->commande_id != null) {

            if ($objProduct->statut_produit_id == 2) {//2:vendu
                //on récupère l'objet statut_produit
                $objStatut_produit = Statut_produit::where('id', '=', 3)->first();//3:depose
                if(empty($objStatut_produit)) {
                    $this->_errorCode = 6;
                    $this->_response['message'][] = "Le statut_produit n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try {
                    $objProduct->update(["statut_produit_id" => $objStatut_produit->id]);
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 7;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
                $objGestSurface = User::where('role_id','=',3)->first();
                $cooll = "https://draht.team-solutions.net/#/dashboards/";
                try{
                    $msg = "le produit : ".$objProduct->designation." a bien été deposé chez le gestionnaire de la cooperative, clique sur ce lien pour les voir ".$cooll;
                    $returnSms = $objGestSurface->sms($msg, $objGestSurface->phone);
                } catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 14;
                    if (in_array($this->_env, ['local', 'development'])) {
                        $this->_response['message'] = $objException->getMessage();
                    }
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

            }else {
                DB::rollback();
                $this->_errorCode = 8;
                $this->_response['message'][] = "Ce produit n'est pas vendu.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollback();
            $this->_errorCode = 9;
            $this->_response['message'][] = "Ce produit ne peut être déposé.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();

        $toReturn = [
            'objet'=> $objProduct,
            'sms'=> $returnSms
        ];

        $this->_response['message'] = 'Confirmation de la reception du produit.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function delete(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'produit'=>'string|required'
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

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if(!in_array($objRole->alias,array('producteur','gestionnaire-cooperative'))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objProduit = Produit::where('ref', '=', $request->get('produit'))->first();
        if(empty($objProduit)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le produit n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();
        $objImage = (object)[];
        //Date du jour actuel
        date_default_timezone_set('Africa/Douala');
        $date_now = date('Y/m/d H:i:s', time());
        $dt = strtotime($date_now);//strtotime transforme $date_now en timestamp (nbre de seconde)

        switch ($objProduit->type_vente) {
            case 'vente directe':

                if($objProduit->commande_id == null) {

                    try {

                        $objImages = Produit_img::where('produit_id', '=', $objProduit->id)->get();
                        foreach ($objImages as $itemImg) {
                            DB::table('produit_imgs')
                            ->where('produit_imgs.id', $itemImg->id)
                            ->delete();
                        }

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 6;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    try{

                        DB::table('produits')
                        ->where('produits.id', $objProduit->id)
                        ->delete();

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 7;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }


                }else{

                    try{

                        $objProduit->update(['published' => 0]);

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

                break;

            case 'vente aux encheres':

                $begin_date = $objProduit->begin_date;//Date de debut d'enchère
                $dt1 = strtotime($begin_date);//strtotime transforme $begin_date en timestamp (nbre de seconde)

                $end_date = $objProduit->end_date;//Date de fin d'enchère
                $dt2 = strtotime($end_date);//strtotime transforme $end_date en timestamp (nbre de seconde)

                if($dt < $dt1) {

                    if($objProduit->commande_id == null) {

                        $allPropositions = Proposition::where('produit_id', '=', $objProduit->id)->get();

                        if($allPropositions->isNotEmpty()) {

                            try{

                                foreach ($allPropositions as $objProposition) {
                                    DB::table('propositions')
                                    ->where('propositions.id', $objProposition->id)
                                    ->delete();
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

                        }

                        try {

                            $objImages = Produit_img::where('produit_id', '=', $objProduit->id)->get();
                            foreach ($objImages as $itemImg) {
                                DB::table('produit_imgs')
                                ->where('produit_imgs.id', $itemImg->id)
                                ->delete();
                            }

                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 10;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                        try{

                            DB::table('produits')
                            ->where('produits.id', $objProduit->id)
                            ->delete();

                        }catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 11;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();
                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }

                    }else{

                        try{

                            $objProduit->update(['published' => 0]);

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
                }elseif($dt2 < $dt) {

                    try{

                        $objProduit->update(['published' => 0]);

                    }catch (Exception $objException) {
                        DB::rollback();
                        $this->_errorCode = 13;
                        if (in_array($this->_env, ['local', 'development'])) {
                        }
                        $this->_response['message'] = $objException->getMessage();
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                }else {

                    DB::rollback();
                    $this->_errorCode = 14;
                    $this->_response['message'][] = "Impossible de supprimer ce produit.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                    break;
                }

                break;

            default:

                DB::rollback();
                $this->_errorCode = 15;
                $this->_response['message'][] = "Le produit n'a pas de type de vente.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
                break;
        }


        DB::commit();
        $toReturn = [];

        $this->_response['message'] = "Le produit a bien été supprimé!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

}

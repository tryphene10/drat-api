<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Role;
use App\Type_livraison;
use DateTime;

class TypeLivraisonApiController extends Controller
{
    public function create(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'name'=>'String|required'
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
		if ($objRole->alias != "administrateur") {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		//On vérifie si l'unité à enregistrer n'existe pas !
		$type_livraison = Type_livraison::where('name','=', $request->get('name'))->first();
		if (!empty($type_livraison)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Ce type de livraison existe déjà!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		
		try {

			$objType_livraison = new Type_livraison();
			$objType_livraison->name = $request->get('name');
			$objType_livraison->published = 1;
			$objType_livraison->generateReference();
			$objType_livraison->generateAlias($objType_livraison->name);
			$objType_livraison->save();
		}
		catch(Exception $objException){
			DB::rollback();
			$this->_errorCode = 6;
			if (in_array($this->_env, ['local', 'development'])){
				$this->_response['message'] = $objException->getMessage();
			}
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

		DB::commit();

		$toReturn = [
			'objet'=> $objType_livraison
		];
		$this->_response['message'] = 'Enregistrement reussi.';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    public function update(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'ref_type_livraison'=>'string|required',
			'name'=>'string|required'
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
		if($objRole->alias != "administrateur") {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objType_livraison = Type_livraison::where('ref', '=', $request->get("ref_type_livraison"))->first();
		if(empty($objType_livraison)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le type de livraison n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		if($request->has("name")){

            try {
                $objType_livraison->update(["name" => $request->get('name')]);
            }catch (Exception $objException) {
                $this->_errorCode = 6;
                if(in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }
            
		}

		DB::commit();

		$toReturn = [
			'objet'=> $objType_livraison
		];
		$this->_response['message'] = 'Modification reussi.';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    public function delete(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'ref_type_livraison'=>'string|required'
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

        //On vérifie que l'utilisateur est habilité
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if ($objRole->alias != "administrateur") {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objType_livraison = Type_livraison::where('ref', '=', $request->get('ref_type_livraison'))->first();
		if (empty($objType_livraison)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le Type_livraison n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		try{
			DB::table('type_livraisons')
            ->where('type_livraisons.id', $objType_livraison->id)
            ->delete();
		}
		catch(Exception $objException){
			DB::rollback();
			$this->_errorCode = 6;
			if (in_array($this->_env, ['local', 'development'])){
				$this->_response['message'] = $objException->getMessage();
			}
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

		DB::commit();

		$toReturn = [
			
		];
		$this->_response['message'] = "L'occurrence type de livraison a été supprimée!";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    public function viewAll()
	{
		$this->_fnErrorCode = "01";

		try {
            //Récupération liste type_livraison
			$objtype_livraison = DB::table('type_livraisons')
			->select(DB::raw('type_livraisons.id as id_type_livraison,
				type_livraisons.name as name_type_livraison,
				type_livraisons.ref as ref_type_livraison,
				type_livraisons.created_at as date'))
			->where('type_livraisons.published','=',1)
			->orderBy('type_livraisons.id','desc')
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
			'type_livraison' => $objtype_livraison
		];
		$this->_response['message'] = 'Liste des types de livraison.';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

	 //Fonction qui permet de recupérer la liste des commandes d'un type de livraison
	 public function OrdersOfTypeLivraison(Request $request)
	 {
		  $this->_fnErrorCode = 1;
		  $validator = Validator::make($request->all(), [
			'ref_type_livraison'=>'string|required'
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

		$objType_livraison = Type_livraison::where('ref','=',$request->get('ref_type_livraison'))->first();
		if (empty($objType_livraison)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "Le Type_livraison n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}
		
		try {

			//Récupération de toutes les commandes
			$objAllCommandes = DB::table('commandes')
			->join('users', 'users.id', '=', 'commandes.user_client_id')
			->join('statut_cmds', 'statut_cmds.id', '=', 'commandes.statut_cmd_id')
			->join('type_livraisons', 'type_livraisons.id', '=', 'commandes.type_livraison_id')
			->join('statut_livraisons', 'commandes.statut_livraison_id', '=', 'statut_livraisons.id')
			//->leftJoin('quartiers', 'quartiers.id', '=', 'commandes.quartier_id')
			->join('bon_commandes', 'bon_commandes.id', '=', 'commandes.bon_commande_id')
			->leftJoin('bon_livraisons', 'bon_livraisons.id', '=', 'commandes.bon_livraison_id')
			->select('commandes.ref as ref_commande',
				'commandes.mode_payment as mode_payment',
				'commandes.paie_phone as user_phone', 
				'commandes.montant as montant',
				'commandes.id as id_commande',
				'commandes.cni as num_cni',
				'commandes.signature as signature',
				'commandes.lieu_livraison as lieu_livraison',
				'commandes.bon_livraison_id as bon_livraison_id',
				'type_livraisons.ref as ref_type_livraison',
				'type_livraisons.name as name_type_livraison', 
				'statut_cmds.ref as ref_statut_cmd',
				'statut_cmds.name as name_statut_cmd',
				'statut_livraisons.ref as ref_statut_livraison',
                'statut_livraisons.name as name_statut_livraison',
				'users.name as name_customer',
				'users.surname as surname_customer',
				'users.email as email_customer')
			->where('commandes.published', 1)
			->where('commandes.type_livraison_id', '=',$objType_livraison->id)
			->where('commandes.statut_cmd_id', 2)
			->where('commandes.bon_commande_id','!=',Null)
			->where('commandes.bon_livraison_id','=',Null)
			->orderBy('commandes.id', 'desc')
			->get();

		} catch (Exception $objException) {
			$this->_errorCode = 4;
			if (in_array($this->_env, ['local', 'development'])) {
			}
			$this->_response['message'] = $objException->getMessage();
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		$collCommandes = collect();
		foreach($objAllCommandes as $commande){
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
				->where('produits.commande_id', $commande->id_commande)
				->get();
			}catch (Exception $objException) {
				$this->_errorCode = 5;
				if (in_array($this->_env, ['local', 'development'])) {
				}
				$this->_response['message'] = $objException->getMessage();
				$this->_response['error_code'] = $this->prepareErrorCode();
				return response()->json($this->_response);
			}

			if($objListProduits->isNotEmpty()) {
				$collCommandes->push($commande);
			}

		}

		$listCommandes = collect();
		foreach ($collCommandes as $itemCommande) {

			try{
				$objAllProduits = DB::table('produits')
				->join('categories', 'categories.id', '=', 'produits.categorie_id')
				->join('commandes', 'commandes.id', '=', 'produits.commande_id')
				->join('statut_produits', 'statut_produits.id', '=', 'produits.statut_produit_id')
				->join('volumes', 'volumes.id', '=', 'produits.volume_id')
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
					'volumes.ref as ref_volume',
					'volumes.name as name_volume',
					'unites.ref as ref_unite',
					'unites.ref as name_unite')
				->where('produits.published', 1)
				->where('produits.commande_id', $itemCommande->id_commande)
				->get();

			}catch (Exception $objException) {
				$this->_errorCode = 6;
				if (in_array($this->_env, ['local', 'development'])) {
				}
				$this->_response['message'] = $objException->getMessage();
				$this->_response['error_code'] = $this->prepareErrorCode();
				return response()->json($this->_response);
			}

			$collProduits = collect();
			foreach ($objAllProduits as $itemproduit) {
				try {
					$objListImgProduit = DB::table('produit_imgs')
					->join('produits', 'produits.id', '=', 'produit_imgs.produit_id')
					->select('produit_imgs.name as image',
						'produit_imgs.ref as image_ref')
					->where('produit_imgs.published', 1)
					->where('produit_imgs.produit_id', $itemproduit->id_produit)
					->get();
				}catch (Exception $objException) {
					$this->_errorCode = 7;
					if (in_array($this->_env, ['local', 'development'])) {
						$this->_response['message'] = $objException->getMessage();
					}
					$this->_response['error_code'] = $this->prepareErrorCode();
					return response()->json($this->_response);
				}

				$collProduits->push(array(
					"produit" => $itemproduit,
					"image" => $objListImgProduit
				));
			}
			$listCommandes->push(array(
				"commande" => $itemCommande,
				"list_produits" => $collProduits
			)); 
		}
 
		 
 
		 $toReturn = [
			 'objet' => $listCommandes
		 ];
		 $this->_response['message'] = "Liste de toutes les Commandes payées.";
		 $this->_response['data'] = $toReturn;
		 $this->_response['success'] = true;
		 return response()->json($this->_response);
	 }

}

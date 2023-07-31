<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Quartier;
use App\Role;
use App\Ville;

class QuartierApiController extends Controller
{
    public function create(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'id_ville'=>'integer|required',
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

        //On vérifie la ville !
		$objVille = Ville::where('id','=', $request->get('id_ville'))->first();
		if (empty($objVille)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "la ville n'existe pas!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		//On vérifie si le quartier à enregistrer n'existe pas !
		$quartier = Quartier::where('name','=', $request->get('name'))->first();
		if (!empty($quartier)) {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Ce quartier existe déjà!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objUser = Auth::user();
		if(empty($objUser)){
			if(in_array($this->_env, ['local', 'development'])){
				$this->_response['message'] = 'Cette action nécéssite une connexion.';
			}

			$this->_errorCode = 5;
			$this->_response['error_code']  = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

        //On vérifie que l'utilisateur est bien admin
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if ($objRole->alias != "administrateur") {
			$this->_errorCode = 6;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		 
		try {

			$objQuartier = new Quartier();
			$objQuartier->name = $request->get('name');
			$objQuartier->published = 1;
			$objQuartier->generateReference();
			$objQuartier->generateAlias($objQuartier->name);
			$objQuartier->ville()->associate($objVille);
			$objQuartier->save();
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

		DB::commit();

		$toReturn = [
			'objet'=> $objQuartier
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
			'quartier'=>'string|required',
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
		if ($objRole->alias != "administrateur") {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objQuartier = Quartier::where('ref', '=', $request->get("quartier"))->first();
		if (empty($objQuartier)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le quartier n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		if($request->has("name")){
            try{
                $objQuartier->update(["name" => ucwords(strtolower($request->get("name")))]);
            }
            catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 6;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }
			
		}

		DB::commit();

		$toReturn = [
			'objet'=> $objQuartier
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
			'ref'=>'string|required'
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
		if ($objRole->alias!="administrateur") {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objQuartier = Quartier::where('ref', '=', $request->get('ref'))->first();
		if (empty($objQuartier)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le quartier n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		try{
			$objQuartier->update(["published" => 0]);
		}
		catch(Exception $objException){
			DB::rollback();
			$this->_errorCode = 6;
			if (in_array($this->_env, ['local', 'development'])){
            }
            $this->_response['message'] = $objException->getMessage();
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

		DB::commit();

		$toReturn = [
			'objet' => $objQuartier
		];
		$this->_response['message'] = "Ce quartier a été supprimé!";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    //Afficher les quartiers
	public function viewAllDistricts()
	{
		$this->_fnErrorCode = "01";

		try {
            //Récupération liste quartier
			$allQuartiers = DB::table('quartiers')
			->join('villes', 'villes.id', '=', 'quartiers.ville_id')
			->select(DB::raw('quartiers.ref as ref_quartier,quartiers.id as id_quartier,quartiers.name as name_quartier,villes.id as id_ville,villes.name as name_ville'))
			->where('quartiers.published','=',1)
			->orderBy('quartiers.id','desc')
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
			'quartier' => $allQuartiers
		];
		$this->_response['message'] = "Liste des quartiers";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

	//Afficher les quartiers d'une ville
	public function districtsByCity(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'id_ville'=>'integer|required'
		]);

		if ($validator->fails())
		{
			if (!empty($validator->errors()->all())) {
				foreach ($validator->errors()->all() as $error) {
					$this->_response['message'][] = $error;
				}
			}
			$this->_errorCode = 2;
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        //Récupération de l'objet Ville
		$objVille = Ville::where('id', '=', $request->get('id_ville'))->first();
		if(empty($objVille)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "La ville n'existe pas";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		try {
			$objQuartier = DB::table('quartiers')
			->join('villes','villes.id','=','quartiers.ville_id')
			->select(DB::raw('quartiers.ref as ref_quartier, quartiers.id as id_quartier, quartiers.name as name_quartier'))
			->where('quartiers.ville_id', '=', $objVille->id)
			->where('quartiers.published','=',1)
			->orderBy('quartiers.id','desc')
			->get();
		}catch (Exception $objException){
			$this->_errorCode = 4;
			if(in_array($this->_env, ['local', 'development'])) {
				$this->_response['message'] = $objException->getMessage();
			}
			$this->_response['error_code']  = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

		$toReturn = [
			'quartier'=> $objQuartier
		];
		$this->_response['message'] = "Liste des quatiers d'une ville.";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    //Afficher les villes
    public function viewAllCities()
	{
		$this->_fnErrorCode = "01";

		try {
            //Récupération liste villes
			$allVilles = Ville::all();

		}catch (Exception $objException) {
			$this->_errorCode = 2;
			if(in_array($this->_env, ['local', 'development'])) {
				$this->_response['message'] = $objException->getMessage();
			}
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json( $this->_response );
		}

		$toReturn = [
			'ville' => $allVilles
		];
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}
}

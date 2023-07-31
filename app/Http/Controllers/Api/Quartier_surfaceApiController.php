<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Quartier_surface;
use App\Role;
use App\Ville_surface;

class Quartier_surfaceApiController extends Controller
{
    public function create(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'id_ville_surface'=>'integer|required',
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

        //On vérifie la ville_surface !
		$objVille_surface = Ville_surface::where('id','=', $request->get('id_ville_surface'))->first();
		if (empty($objVille_surface)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "La ville n'existe pas!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		//On vérifie si le quartier à enregistrer n'existe pas !
		$quartier_surface = Quartier_surface::where('name','=', $request->get('name'))->first();
		if (!empty($quartier_surface)) {
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

			$objQuartier_surface = new Quartier_surface();
			$objQuartier_surface->name = $request->get('name');
			$objQuartier_surface->published = 1;
			$objQuartier_surface->generateReference();
			$objQuartier_surface->generateAlias($objQuartier_surface->name);
			$objQuartier_surface->villeSurface()->associate($objVille_surface);
			$objQuartier_surface->save();
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
			'objet'=> $objQuartier_surface
		];
		$this->_response['message'] = 'Enregistrement reussi.';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    //Afficher les quartier_surfaces d'une ville_surface
	public function districtsByCity(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'id_ville_surface'=>'integer|required'
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
		$objVille_surface = Ville_surface::where('id', '=', $request->get('id_ville_surface'))->first();
		if(empty($objVille_surface)) {
			$this->_errorCode = 3;
			$this->_response['message'][] = "La ville n'existe pas";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		try {

			$objQuartier_surface = DB::table('quartier_surfaces')
			->join('ville_surfaces','ville_surfaces.id','=','quartier_surfaces.ville_surface_id')
			->select(DB::raw('quartier_surfaces.ref as ref_quartier, quartier_surfaces.id as id_quartier, quartier_surfaces.name as name_quartier'))
			->where('quartier_surfaces.ville_surface_id', '=', $objVille_surface->id)
			->where('quartier_surfaces.published','=',1)
			->orderBy('quartier_surfaces.id','desc')
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
			'quartier_surface'=> $objQuartier_surface
		];
		$this->_response['message'] = "Liste des quatiers d'une ville.";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

    public function update(Request $request)
	{
		$this->_fnErrorCode = 1;
		$validator = Validator::make($request->all(), [
			'quartier_surface'=>'string|required',
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

		$objQuartier_surface = Quartier_surface::where('ref', '=', $request->get("quartier_surface"))->first();
		if (empty($objQuartier_surface)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le quartier n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		if($request->has("name")){
            try{
                $objQuartier_surface->update(["name" => $request->get("name")]);
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
			'objet'=> $objQuartier_surface
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

		$objQuartier_surface = Quartier_surface::where('ref', '=', $request->get('ref'))->first();
		if (empty($objQuartier_surface)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le quartier n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		try{
			$objQuartier_surface->update(["published" => 0]);
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
			'objet' => $objQuartier_surface
		];
		$this->_response['message'] = "Ce quartier a été supprimé!";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

}

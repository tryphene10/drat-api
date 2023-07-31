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
use App\Unite;

class UniteApiController extends Controller
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
		$unite = Unite::where('name','=', $request->get('name'))->first();
		if (!empty($unite)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Cette unité existe déjà!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		
		try {

			$objUnite = new Unite();
			$objUnite->name = $request->get('name');
			$objUnite->published = 1;
			$objUnite->generateReference();
			$objUnite->generateAlias($objUnite->name);
			$objUnite->save();
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
			'objet'=> $objUnite
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
			'unite'=>'string|required',
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

		$objUnite = Unite::where('ref', '=', $request->get("unite"))->first();
		if (empty($objUnite)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "L'unité n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		if($request->has("name")){

            try {
                $objUnite->update(["name" => $request->get('name')]);
            }catch (Exception $objException) {
                $this->_errorCode = 6;
                if(in_array($this->_env, ['local', 'development'])) {
                    $this->_response['message'] = $objException->getMessage();
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }
            
		}

		DB::commit();

		$toReturn = [
			'objet'=> $objUnite
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
			'unite'=>'string|required'
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

		$objUnite = Unite::where('ref', '=', $request->get('unite'))->first();
		if (empty($objUnite)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "L'unité n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		try{
			$objUnite->update(["published" => 0]);
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
			'objet' => $objUnite
		];
		$this->_response['message'] = "Cette unité a été supprimée!";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}

	public function viewAll()
	{
		$this->_fnErrorCode = "01";

		try {
            //Récupération liste unités
			$objUnite = DB::table('unites')
			->select(DB::raw('unites.id as id_unite,unites.name as name,unites.ref as ref_unite'))
			->where('unites.published','=',1)
			->orderBy('unites.id','desc')
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
			'unite' => $objUnite
		];
		$this->_response['message'] = 'Liste des calibrages des produits';
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}
}

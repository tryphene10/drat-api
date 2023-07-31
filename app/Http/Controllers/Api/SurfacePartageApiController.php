<?php

namespace App\Http\Controllers\Api;

use App\Surface_partage;
use App\Quartier_surface;
use App\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SurfacePartageApiController extends Controller
{
    public function create(Request $request)
	{
		$this->_fnErrorCode = 1;

        $validator = Validator::make($request->all(), [
			'ref_quartier_surafce'=>'string|required',
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

		//On vérifie si la surface de partage à enregistrer n'existe pas !
		$objSurface_partage = Surface_partage::where('name', '=', $request->get('name'))->first();
		if (!empty($objSurface_partage)) {
			$this->_errorCode = 4;
			$this->_response['message'][] = "la Surface existe déjà!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		//Récupération de l'objet quartier !
		$objQuartier_surface = Quartier_surface::where('ref','=', $request->get('ref_quartier_surafce'))->first();
		if (empty($objQuartier_surface)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "Le quartier n'existe pas!";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

        //On vérifie que l'utilisateur est bien admin
		$objRole = Role::where('id', '=', $objUser->role_id)->first();
		if ($objRole->alias != "administrateur") {
			$this->_errorCode = 6;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		DB::beginTransaction();
		try {
			$objSurface = new Surface_partage();
			$objSurface->name = $request->get('name');
			$objSurface->published = 1;
			$objSurface->generateReference();
			$objSurface->generateAlias($objSurface->name);
			$objSurface->quartierSurface()->associate($objQuartier_surface);
			$objSurface->save();

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
			'objet'=> $objSurface
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
			'ref_surface'=>'string|required',
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

		$objSurface = Surface_partage::where('ref', '=', $request->get("ref_surface"))->first();
		if (empty($objSurface)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "La surface n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		if($request->has("name")){
			if(!$objSurface->update(["name" => $request->get("name")])){
				DB::rollback();
				$this->_errorCode = 6;
				$this->_response['message'][] = "La modification a échoué.";
				$this->_response['error_code'] = $this->prepareErrorCode();
				return response()->json($this->_response);
			}
		}

		DB::commit();

		$toReturn = [
			'objet'=> $objSurface
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
		if (!in_array($objRole->alias, array("administrateur"))) {
			$this->_errorCode = 4;
			$this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}

		$objSurface = Surface_partage::where('ref', '=', $request->get('ref'))->first();
		if (empty($objSurface)) {
			$this->_errorCode = 5;
			$this->_response['message'][] = "La surface n'existe pas.";
			$this->_response['error_code'] = $this->prepareErrorCode();
			return response()->json($this->_response);
		}


		//Récupération de la liste des commandes d'une surface de partage
		$allCommandes = Commande::where('surface_partage_id','=',$objSurface->id)->get();

		//Récupération de la liste des users d'une surface de partage
		$allUsers = User::where('surface_partage_id','=',$objSurface->id)->get();

		if($allCommandes->isEmpty() && $allUsers->isEmpty()) {
			DB::table('surface_partages')->where('id','=',$objSurface->id)->delete();
		}else{
			try{
				$objSurface->update(["published" => 0]);
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

		DB::commit();

		$toReturn = [
			'objet' => $objSurface
		];
		$this->_response['message'] = "Cette valeur a été supprimée!";
		$this->_response['data'] = $toReturn;
		$this->_response['success'] = true;
		return response()->json($this->_response);
	}


    public function allSurfacePartage()
    {
        $this->_errorCode  = 1;

        try {
            //Récupération surface de partage
			$objsurface = DB::table('surface_partages')
           /*  ->join('quartier_surfaces','quartier_surfaces.id','=','surface_partages.quartier_surface_id') */
            /* ->join('ville_surfaces','ville_surfaces.id','=','quartier_surfaces.ville_surface_id') */
			->select('surface_partages.id as id',
                'surface_partages.name as name',
				'surface_partages.ref as ref',
                'surface_partages.longitude as longitude',
                'surface_partages.latitude as latitude',
				/* 'quartier_surfaces.ref as ref_quartier',
				'quartier_surfaces.name as name_quartier',
                'ville_surfaces.name as name_ville'*/)
			->where('surface_partages.published','=',1)
			->orderBy('surface_partages.id','desc')
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
            'surface' => $objsurface
        ];
        $this->_response['message']    = 'Liste des surfaces partages.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }


}

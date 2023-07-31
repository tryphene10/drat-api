<?php

namespace App\Http\Controllers\Api;

use App\Cooperative;
use App\Role;
use App\User;
use App\Detail_bon_cmd;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CooperativeApiController extends Controller
{
    public function allCooperative()
    {
        $this->_errorCode  = 1;

        try {
            //Récupération liste cooperatives
			$allCooperatives = DB::table('cooperatives')
			->select('cooperatives.ref as ref',
                'cooperatives.id as id',
                'cooperatives.name as name',
                'cooperatives.latitude as latitude',
                'cooperatives.longitude as longitude',
                'cooperatives.name_quartier as name_quartier',
                'cooperatives.name_ville as name_ville')
			->where('cooperatives.published','=',1)
			->orderBy('cooperatives.id','desc')
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
            'Cooperative' => $allCooperatives
        ];
        $this->_response['message']    = 'Liste des cooperatives.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function create(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'name_cooperative'=>'String|required',
            'name_quartier'=>'String|required',
            'name_ville'=>'String|required'
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

        //On vérifie si la cooperative à enregistrer n'existe pas !
        $cooperative = Cooperative::where('name', '=', $request->get('name_cooperative'))->first();
        if (!empty($cooperative)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "la cooperative existe déjà!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        // Start transaction!
        DB::beginTransaction();

        try {

            $objCooperative = new Cooperative();
            $objCooperative->name = $request->get('name_cooperative');
            $objCooperative->name_quartier = $request->get('name_quartier');
            $objCooperative->name_ville = $request->get('name_ville');
            $objCooperative->generateReference();
            $objCooperative->generateAlias($objCooperative->name);
            $objCooperative->save();

        }catch(Exception $objException){
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
            'objet'=> $objCooperative
        ];
        $this->_response['message'] = 'La cooperative a été enregistrée.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function update(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'ref_cooperative'=>'string|required',
            'name_cooperative'=>'string|nullable',
            'name_quartier'=>'string|nullable',
            'name_ville'=>'string|nullable'
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

        $objCooperative = Cooperative::where('ref', '=', $request->get("ref_cooperative"))->first();
        if (empty($objCooperative)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "La Cooperative n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        if($request->has("name_quartier")){

            try {
                $objCooperative->update(["name_quartier" => $request->get("name_quartier")]);
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

        if($request->has("name_cooperative")){

            try {
                $objCooperative->update(["name" => $request->get("name_cooperative")]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 7;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }

        if($request->has("name_ville")){

            try {
                $objCooperative->update(["name_ville" => $request->get("name_ville")]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 8;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }

        DB::commit();

        $toReturn = [
            'objet'=> $objCooperative
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
            'ref_cooperative'=>'string|required'
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

        $objCooperative = Cooperative::where('ref', '=', $request->get("ref_cooperative"))->first();
        if (empty($objCooperative)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "La Cooperative n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();


        $usersCooperative = User::where('cooperative_id','=',$objCooperative->id)->get();
        $detailBonCmdes = Detail_bon_cmd::where('cooperative_id','=',$objCooperative->id)->get();

        if($usersCooperative->isEmpty() && $detailBonCmdes->isEmpty()){

            try{
                DB::table('cooperatives')
                ->where('cooperatives.id', $objCooperative->id)
                ->delete();
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

        }else {

            try {
                $objCooperative->update(["published" => 0]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 7;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }

        DB::commit();

        $toReturn = [
        ];

        $this->_response['message'] = "La cooperative a été supprimée!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }


}

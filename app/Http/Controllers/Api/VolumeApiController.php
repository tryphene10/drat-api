<?php

namespace App\Http\Controllers\Api;

use App\Role;
use App\Volume;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;




class VolumeApiController extends Controller
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
        $volume = Volume::where('name','=', $request->get('name'))->first();
        if (!empty($volume)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Cette unité existe déjà!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        try {
            $objVolume = new Volume();
            $objVolume->name = $request->get('name');
            $objVolume->published = 1;
            $objVolume->generateReference();
            $objVolume->generateAlias($objVolume->name);
            $objVolume->save();
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
            'objet'=> $objVolume
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
            'volume'=>'string|required',
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

        $objVolume = Volume::where('ref', '=', $request->get("volume"))->first();
        if (empty($objVolume)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "L'unité n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        if($request->has("name")){

            try {
                $objVolume->update(["name" => $request->get('name')]);
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
            'objet'=> $objVolume
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
            'volume'=>'string|required'
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

        $objVolume = Volume::where('ref', '=', $request->get('unite'))->first();
        if (empty($objVolume)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "L'unité n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        try{
            $objVolume->update(["published" => 0]);
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
            'objet' => $objVolume
        ];
        $this->_response['message'] = "le volume a été supprimée!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function viewAll()
    {
        $this->_fnErrorCode = "01";

        try {
            //Récupération liste volumes
            $objVolume = DB::table('volumes')
                ->select(DB::raw('volumes.id as id_volume,volumes.name as name,volumes.ref as ref_volume'))
                ->where('volumes.published','=',1)
                ->orderBy('volumes.id','desc')
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
            'volume' => $objVolume
        ];
        $this->_response['message'] = 'Liste des volumes d\'un produit';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
}

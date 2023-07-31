<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Option;
use App\Role;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OptionApiController extends Controller
{
    public function update(Request $request)
    {
        $this->_fnErrorCode = 1;

        $objListOptions = collect(json_decode($request->getContent(), true));
        if (empty($objListOptions)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "La liste des options est vide.";
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
        if ($objRole->alias != "admin") {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();
        foreach($objListOptions as $option){
            $objOption = Option::where('ref', '=', $option["ref"])->first();
            if (empty($objOption)) {
                $this->_errorCode = 5;
                $this->_response['message'][] = "L'Option n'existe pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

            $myLogo = array();
            if ($option["key"] == "LOGO") {
                $myLogo[]=$objOption->value;
                $image="";
                if(in_array($option["value"], $myLogo)){
                    $image = $myLogo[0];
                }
                else{
                    $image = $option["value"];  // your base64 encoded
                }
                $extension = explode('/', mime_content_type($image))[1];

                $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = $objOption->ref.'_'.str_random(10) . '.'.$extension;

                if (!Storage::disk('option')->put($imageName, base64_decode($image))){
                    DB::rollback();
                    $this->_errorCode = 6;
                    if (in_array($this->_env, ['local', 'development'])){
                        $this->_response['message'] ="Echec de la modificaton de l'image.";
                    }
                    $this->_response['error_code']  = $this->prepareErrorCode();
                    return response()->json( $this->_response );

                }

                if (!$objOption->update([$option["key"] => 'simkaah-api/storage/app/public/option/images/'. $imageName])){
                    DB::rollback();
                    $this->_errorCode = 7;
                    $this->_response['message'][] = "La modification a échoué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }
            elseif (!$objOption->update([$option["key"] => $option["value"]])) {
                DB::rollback();
                $this->_errorCode = 7;
                $this->_response['message'][] = "La modification a échoué.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }
        DB::commit();

        $toReturn = [
            'message'=>'Modification reussi.',
            'objet'=> $objOption
        ];
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function delete(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'option'=>'string|required'
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
        if (!in_array($objRole->alias, array("admin"))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objOption = Option::where('ref', '=', $request->get("heure"))->first();
        if (empty($objOption)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "L'Option n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        try{
            if(!$objOption->update(["published" => 0])){
                DB::rollback();
                $this->_errorCode = 6;
                $this->_response['message'][] = "La suppression a échoué.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }
        catch(Exception $objException){
            DB::rollback();
            $this->_errorCode = 7;
            if (in_array($this->_env, ['local', 'development'])){
                $this->_response['message'] = $objException->getMessage();
            }
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        DB::commit();

        $toReturn = [
            'message' => "L'Option a été supprimée!",
            'objet' => $objOption
        ];
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function all()
    {
        $this->_fnErrorCode = "01";
        try {
            //Récupération liste horaire
            $objOptions = DB::table('options')
                ->select("key","value", "ref")
                ->where("published", 1)
                ->get();

        }catch (Exception $objException) {
            $this->_errorCode = 2;
            if(in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'] = $objException->getMessage();
            }
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        $toReturn = [
            'message'=>'Informations du site.',
            'objet'=> $objOptions
        ];
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
}

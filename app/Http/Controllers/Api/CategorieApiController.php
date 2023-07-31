<?php

namespace App\Http\Controllers\Api;

use App\Categorie;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Role;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategorieApiController extends Controller
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

        //On vérifie si la catégorie à enregistrer n'existe pas !
        $categorie = Categorie::where('name', '=', $request->get('name'))->first();
        if (!empty($categorie)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "la catégorie existe déjà!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        // Start transaction!
        DB::beginTransaction();

        try {
            $objCategorie = new Categorie();
            $objCategorie->name = $request->get('name');
            $objCategorie->generateReference();
            $objCategorie->generateAlias($objCategorie->name);
            $objCategorie->save();

            //----------------------------------------------------------------------------
            //Création de l'image de la Catégorie
            //----------------------------------------------------------------------------
            /*if ($request->has('image')) {
                $image = $request->get('image');  // your base64 encoded
                $extension = explode('/', mime_content_type($request->get('image')))[1];

                $image = str_replace('data:image/'.$extension.';base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = $objCategorie->ref.'_'.str_random(10) . '.'.$extension;

                if (Storage::disk('categorie')->put($imageName, base64_decode($image))) {
                    $objCategorieImage = new Categorie_img();
                    $objCategorieImage->name = 'api/edrugs-api2/storage/app/public/categorie/images/'.$imageName ;
                    $objCategorieImage->generateAlias($objCategorie->name);
                    $objCategorieImage->generateReference();
                    $objCategorieImage->categorie()->associate($objCategorie);
                    $objCategorieImage->save();
                } else {
                    DB::rollback();
                    $this->_errorCode = 7;
                    if (in_array($this->_env, ['local', 'development'])) {
                        $this->_response['message'] ="Echec dans l'enregistrement de image.";
                    }
                    $this->_response['error_code']  = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            } else {
                DB::rollback();
                $this->_errorCode = 8;
                $this->_response['message'][] = "Il manque l'image du Met!";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }*/
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
            'objet'=> $objCategorie
        ];
        $this->_response['message'] = 'Enregistrement reussi.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    // Afficher la liste des catégories
    public function allCategories()
    {
        $this->_errorCode  = 1;
        $toReturn = [
            'categories'=>Categorie::all(),
        ];
        $this->_response['message']    = 'Liste des catégories.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function update(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'categorie'=>'string|required',
            'name'=>'string|nullable'
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

        $objCategorie = Categorie::where('ref', '=', $request->get("categorie"))->first();
        if (empty($objCategorie)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "La Categorie n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        if($request->has("name")){
            try {
                $objCategorie->update(["name" => $request->get("name")]);
            }catch(Exception $objException){
                DB::rollback();
                $this->_errorCode = 6;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

            try {
                $objCategorie->update(["alias" => strtolower($request->get("name"))]);
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

        //----------------------------------------------------------------------------
        //Modification de l'image de la Catégorie
        //----------------------------------------------------------------------------
        /*if($request->has('image')){
            //On supprime l'ancienne image de la Catégorie
            DB::table('categorie_imgs')
            ->where('categorie_imgs.categorie_id', $objCategorie->id)
            ->delete();

            $image = $request->get('image');  // your base64 encoded
            $extension = explode('/', mime_content_type($request->get('image')))[1];

            $image = str_replace('data:image/'.$extension.';base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = $objCategorie->ref.'_'.str_random(10) . '.'.$extension;

            if (Storage::disk('categorie')->put($imageName, base64_decode($image))){
                $objCategorieImage = new Categorie_img();
                $objCategorieImage->name = 'api/edrugs-api2/storage/app/public/categorie/images/'.$imageName ;
                $objCategorieImage->generateAlias($objCategorie->name);
                $objCategorieImage->generateReference();
                $objCategorieImage->categorie()->associate($objCategorie);
                $objCategorieImage->save();
            } else{
                DB::rollback();
                $this->_errorCode = 8;
                if (in_array($this->_env, ['local', 'development'])){
                    $this->_response['message'] ="Echec de la modificaton de l'image.";
                }
                $this->_response['error_code']  = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }

        }*/

        DB::commit();

        $toReturn = [
            'objet'=> $objCategorie
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

        $objCategorie = Categorie::where('ref', '=', $request->get("categorie"))->first();
        if (empty($objCategorie)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "La catégorie n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        try{
            DB::table('categories')
            ->where('categories.id', $objCategorie->id)
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

        DB::commit();

        $toReturn = [
        ];

        $this->_response['message'] = "La catégorie a été supprimée!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
}

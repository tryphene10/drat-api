<?php

namespace App\Http\Controllers\Api;

use App\Notifications\AdminRegisteredUser;
use App\Role;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

use App\Produit;
use Illuminate\Support\Facades\Storage;
use App\Produit_img;

class AuthApiController extends Controller
{


    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] expires_at
     */

    public function login(Request $request)
    {

        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'email'=>'required|email|max:'.Config::get('constants.size.max.email').'|exists:users,email',
            'password'=>'required|min:'.Config::get('constants.size.min.password').'|max:'.Config::get('constants.size.max.password')

        ]);

        if ($validator->fails())
        {
            if (!empty($validator->errors()->all()))
            {
                foreach ($validator->errors()->all() as $error)
                {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        $objUser = User::where('email', $request->get('email'))
            ->first();
        if(empty($objUser) || !$objUser->isPublished())
        {
            $this->_errorCode               = 2;
            $this->_response['message'][]   = trans('auth.denied');
            $this->_response['error_code']   = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $arrPasswordValidation = $objUser->validatePassword($request->get('password'));
        if($arrPasswordValidation['success'] == false)
        {
            $this->_errorCode               = 5;
            $this->_response['message'][]   = trans('messages.login.fail.default');
            $this->_response['error_code']   = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try
        {
            $objToken = $objUser->createToken('PersonalAccessToken');
        }
        catch(Exception $objException)
        {

            $this->_errorCode             = 6;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message'][]   = $objException->getMessage();
            }
            $this->_response['message'][]   = trans('messages.token.fail.generate');
            $this->_response['error_code']   = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        $toReturn = [
            'token'=>$objToken->accessToken,
            'ref_connected_user'=>$objUser->ref,
            'token_type'=>'Bearer',
            'infos' => $objUser
        ];
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;

        return response()->json($this->_response);
    }





    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $objUser            = Auth::user();
        $this->_fnErrorCode = "02";

        if(empty($objUser))
        {
            $this->_errorCode = 6;
            $this->_response['error_code'] = $this->prepareErrorCode();
            $this->_response['message'][]   = Lang::get('messages.error-occured.default');
            return response()->json($this->_response);

        }

        $request->user()->token()->revoke();

        $arrResult[] = [
            'message'=>Lang::get('logged-out')
        ];
        $this->_response['success'] = true;
        $this->_response['data'] = [
            'result'=>$arrResult
        ];

        return response()->json($this->_response);
    }

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] expires_at
     */

    // Formulaire d'enregistrement des utilisateurs
    public function new(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'nom'=>'nullable',
            'prenom'=>'nullable',
            'phone'=>'required',
            'email'=>'required|email|max:255',
            'password'=>'required|min:'.Config::get('constants.size.min.password').'|max:'.Config::get('constants.size.max.password'),
            ]);

        if ($validator->fails())
        {
            if (!empty($validator->errors()->all()))
            {
                foreach ($validator->errors()->all() as $error)
                {
                    $this->_response['message'][] = $error;
                }
            }
            $this->_errorCode = 2;
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objUser = User::where('email', '=', $request->get('email'))->first();
        if(!empty($objUser))
        {
            $this->_errorCode               = 3;
            $this->_response['message'][]   = "Le mail est dèjà utilisé";
            $this->_response['error_code']   = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        $objRole = Role::where('alias', '=', 'utilisateur')->first();
        if(empty($objRole))
        {
            $this->_errorCode               = 4;
            $this->_response['message'][]   = "Le rôle n'exite pas";
            $this->_response['error_code']   = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        // Start transaction!
        DB::beginTransaction();

        try
        {
            $objUser = new User();
            $objUser->name = $request->get('nom');
            $objUser->surname = $request->get('prenom');
            $objUser->phone = $request->get('phone');
            $objUser->email = $request->get('email');
            $objUser->password = Hash::make($request->get('password'));
            $objUser->published = 1;
            $objUser->generateReference();
            $objUser->generateAlias($request->get('nom'));
            //
            $objUser->role()->associate($objRole);
            $objUser->save();
        }catch (Exception $objException){
            DB::rollback();
            $this->_errorCode               = 4;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = $objException->getMessage();
            }

            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

/*
        try
        {
            //send notification mail
            $objUser->notify(new AdminRegisteredUser($request->get('password')));
        }
        catch(Exception $objException)
        {
            DB::rollback();
            $this->_errorCode              = 7;
            if(in_array($this->_env, ['local', 'development']))
            {
                $this->_response['message']     = $objException->getMessage();
            }
            $this->_response['error_code']  = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }
        */

        // Commit the queries!
        DB::commit();
        //Format d'affichage de message
        $toReturn = [
            'message'=>'Enregistrement reussi. veillez consulter vos mails ',
        ];
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    // Afficher la liste des users
    public function ListeUser()
    {
        $this->_errorCode  = 5;
        $toReturn = [
            'users'=>User::all(),
        ];
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function updatePublishAccount(Request $request){
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'ref'=>'string|required',
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

        $objUser = User::where('ref', $request->get('ref'))->first();
        if(empty($objUser)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "utilisateur inconnu !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if(empty($objRole)) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        if(!$objUser->update(["published" => 1, 'activation_date' => Carbon::now()])) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le compte n'a pas pu être activé !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try{
            $objToken = $objUser->createToken('PersonalAccessToken');
        }
        catch(Exception $objException) {
            $this->_errorCode = 6;
            if(in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'][] = $objException->getMessage();
            }
            $this->_response['message'][] = trans('messages.token.fail.generate');
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $toReturn = [
            'token'=>$objToken->accessToken,
            'ref_connected_user'=>$objUser->ref,
            'objet' => $objUser
        ];

        $this->_response['message'] = "Le compte a été activé!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function addPictureProduct(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'ref_produit'=>'string|required',
            'name_image'=>'nullable'
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
        if(!in_array($objRole->alias,array('producteur','gestionnaire-cooperative'))) {
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'étes pas habilité à réaliser cette tâche.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objProduct = Produit::where('ref', '=', $request->get("ref_produit"))->first();
        if (empty($objProduct)) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le produit n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::beginTransaction();

        //----------------------------------------------------------------------------
        //Modification de l'image d'un produit'
        //----------------------------------------------------------------------------
        
        if($request->has('name_image')){

            $image = $request->get('name_image');  // your base64 encoded
            $extension = explode('/', mime_content_type($request->get('name_image')))[1];

            $image = str_replace('data:image/'.$extension.';base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = $objProduct->ref.'_'.str_random(10) . '.'.$extension;

            if (Storage::disk('produit')->put($imageName, base64_decode($image))){
                try {
                    $objProduitImg = new Produit_img();
                    $objProduitImg->name = 'api/drat-api/storage/app/public/images/produit/'.$imageName;
                    $objProduitImg->produit()->associate($objProduct);
                    $objProduitImg->published = 1;
                    $objProduitImg->generateAlias($objProduct->designation);
                    $objProduitImg->generateReference();
                    $objProduitImg->save();
                }catch (Exception $objException) {
                    DB::rollback();
                    $this->_errorCode = 6;
                    if (in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

            }else{
                DB::rollback();
                $this->_errorCode = 7;
                if (in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = "Echec de la modificaton de l'image.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }
        }

        DB::commit();

        $toReturn = [
            'objet'=> $objProduct,
            'picture'=> $objProduitImg
        ];

        $this->_response['message'] = 'Ajout d\'une image dans ce produit.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

}

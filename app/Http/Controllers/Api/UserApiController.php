<?php

namespace App\Http\Controllers\Api;

use App\Cooperative;
use App\Role;
use App\Surface_partage;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Exception;
class UserApiController extends Controller
{

    public function new(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'nom'=>'required',
            'prenom'=>'nullable',
            'phone'=>'required',
            'quartier'=>'nullable',
            'ville'=>'nullable',
            'email'=>'required|email|max:255',
            'cooperative'=>'nullable',
            'surface_partage'=>'nullable',
            'role'=>'nullable',
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
        $objUserPhone = User::where('phone', '=', $request->get('phone'))->first();
        if(!empty($objUserPhone)){
            $this->_errorCode = 4;
            $this->_response['message'][] = "Le numero de téléphone existe déjà.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $objUser = Auth::user();
        $reponse = "";
        $mail_reponse = "";
        // Start transaction!
        DB::beginTransaction();
        if (empty($objUser)) {
            if(!$request->has("role")){
                try {
                    $objRole = Role::where('alias', '=', 'client')->first();
                    if(empty($objRole)){
                        $this->_errorCode = 5;
                        $this->_response['message'][] = "Le rôle 'Client' n'exite pas";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }

                    $objUser = new User();
                    $objUser->name = $request->get('nom');
                    if($request->has("prenom")){$objUser->surname = $request->get('prenom');}
                    $objUser->phone = $request->get('phone');
                    $objUser->quartier = $request->get('quartier');
                    $objUser->ville = $request->get('ville');
                    $objUser->email = $request->get('email');
                    $objUser->password = Hash::make($request->get('password'));
                    $objUser->published = 0;
                    $objUser->generateReference();
                    $objUser->generateAlias($request->get('nom'));
            //                    $objUser->generateCode();
                    $objUser->role()->associate($objRole);
                    $objUser->save();
                }catch (Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 6;
                    if(in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();

                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }

                try {
                    $mail_reponse = $objUser->mailConfirmAcount($objUser);
                } catch (Exception $objException) {
                    DB::rollBack();
                    $this->_errorCode = 7;
                    if(in_array($this->_env, ['local', 'development'])) {
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }else {
                $this->_errorCode = 8;
                $this->_response['message'][] = "Ce champ ne doit pas prendre de valeur.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        }

        if(!empty($objUser)){
            $objAuthRole = Role::where("id", "=", $objUser->role_id)->first();
            if(empty($objAuthRole)){
                $this->_errorCode = 9;
                $this->_response['message'][] = "L'utilisateur n'a pas de rôle.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
            if($request->has("role")){
                $objRole = Role::where('ref', '=', $request->get("role"))->first();
                if(empty($objRole)){
                    $this->_errorCode = 10;
                    $this->_response['message'][] = "Aucun rôle n'a été renseigner.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
                $surfacePartage = Surface_partage::where('ref', '=', $request->get("surface_partage"))->first();
                if(empty($surfacePartage)){
                    $this->_errorCode = 11;
                    $this->_response['message'][] = "La surface de partage n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
                $objcoop = Cooperative::where('ref','=',$request->get("cooperative"))->first();
                if(empty($objcoop)){
                    $this->_errorCode = 12;
                    $this->_response['message'][] = "La cooperative n'existe pas.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                if(in_array($objAuthRole->alias, array("administrateur"))) {

                    if (in_array($objRole->alias, array("gestionnaire-surface","livreur","coursier"))) {
                        try{
                            $user_connect = $objUser;
                            $objUser = new User();
                            $objUser->name = $request->get('nom');
                            if ($request->has("prenom")) {
                                $objUser->surname = $request->get('prenom');
                            }
                            $objUser->phone = $request->get('phone');
                            $objUser->email = $request->get('email');
                            $objUser->password = Hash::make($request->get('password'));
                            $objUser->published = 1;
                            $objUser->generateReference();
                            $objUser->generateAlias($request->get('nom'));
                            //$objUser->generateCode();
                            $objUser->role()->associate($objRole);
                            $objUser->user()->associate($user_connect);
                            $objUser->surfacePartage()->associate($surfacePartage);
                            $objUser->save();

                        } catch (Exception $objException) {
                            DB::rollback();
                            $this->_errorCode = 13;
                            if (in_array($this->_env, ['local', 'development'])) {
                            }
                            $this->_response['message'] = $objException->getMessage();

                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json($this->_response);
                        }
                    }elseif(in_array($objRole->alias, array("gestionnaire-cooperative"))){
                        try {
                            $user_connect = $objUser;
                            $objUser = new User();
                            $objUser->name = $request->get('nom');
                            if($request->has("prenom")){$objUser->surname = $request->get('prenom');}
                            $objUser->phone = $request->get('phone');
                            $objUser->email = $request->get('email');
                            $objUser->password = Hash::make($request->get('password'));
                            $objUser->published = 1;
                            $objUser->generateReference();
                            $objUser->generateAlias($request->get('nom'));
                            //$objUser->generateCode();
                            $objUser->user()->associate($user_connect);
                            $objUser->cooperative()->associate($objcoop);
                            $objUser->role()->associate($objRole);
                            $objUser->save();
                        }catch (Exception $objException){
                            DB::rollback();
                            $this->_errorCode = 14;
                            if(in_array($this->_env, ['local', 'development'])){
                            }
                            $this->_response['message'] = $objException->getMessage();

                            $this->_response['error_code'] = $this->prepareErrorCode();
                            return response()->json( $this->_response );
                        }
                    }else{
                        $this->_errorCode = 15;
                        $this->_response['message'][] = "Aucun rôle ne correspond.";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }else{
                    $this->_errorCode = 16;
                    $this->_response['message'][] = "Vous n'ètes pas habilité.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }
        }
        //        dd($objUser);
        // Commit the queries!
        DB::commit();
        //Format d'affichage de message
        $toReturn = [
            //'sms_reponse' => $reponse,
            'mail_reponse' => $mail_reponse,
            'objet' => $objUser
        ];

        $this->_response['message'] = 'Enregistrement reussi!';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function createProdAccount(Request $request){

        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'nom'=>'required',
            'prenom'=>'nullable',
            'phone'=>'required',
            'email'=>'required|email|max:255',
            'cooperative'=>'nullable',
            'role'=>'nullable',
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
        $objUserPhone = User::where('phone', '=', $request->get('phone'))->first();
        if(!empty($objUserPhone)){
            $this->_errorCode = 4;
            $this->_response['message'][] = "Le numero de téléphone existe déjà.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //        $objUser = Auth::user();
        $reponse = "";
        // Start transaction!
        DB::beginTransaction();

        if (!$request->has('role')){
            try {
                $objprodRole = Role::where('alias', '=', 'producteur')->first();
                if(empty($objprodRole)){
                    $this->_errorCode = 5;
                    $this->_response['message'][] = "Le rôle 'Producteur' n'exite pas";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
                $objCooperative = null;
                if($request->has('cooperative')){
                    $objCooperative = Cooperative::where('ref', '=', $request->get('cooperative'))->first();
                    if(empty($objCooperative)){
                        $this->_errorCode = 6;
                        $this->_response['message'][] = "la cooperative n'existe pas !";
                        $this->_response['error_code'] = $this->prepareErrorCode();
                        return response()->json($this->_response);
                    }
                }
                $objUser = new User();
                $objUser->name = $request->get('nom');
                if($request->has("prenom")){$objUser->surname = $request->get('prenom');}
                $objUser->phone = $request->get('phone');
                $objUser->email = $request->get('email');
                $objUser->password = Hash::make($request->get('password'));
                $objUser->published = 1;
                $objUser->generateReference();
                $objUser->generateAlias($request->get('nom'));
                $objUser->cooperative()->associate($objCooperative);
                $objUser->role()->associate($objprodRole);
                $objUser->save();
            }catch (Exception $objException){
                DB::rollback();
                $this->_errorCode = 7;
                if(in_array($this->_env, ['local', 'development'])){
                }
                $this->_response['message'] = $objException->getMessage();

                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json( $this->_response );
            }
        }
        //        dd($objUser);
        // Commit the queries!
        DB::commit();
        //Format d'affichage de message
        $toReturn = [
            //'sms_reponse' => $reponse,
            'objet' => $objUser
        ];

        $this->_response['message'] = 'Enregistrement reussi!';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
    //detail user
    public function detailUser(Request $request){
        $this->_fnErrorCode = "01";

        $validator = Validator::make($request->all(), [
            'ref_user' => 'required'
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
            $objuser = User::where("ref", $request->get("ref_user"))->first();
            if (empty($objuser)) {
                DB::rollback();
                $this->_errorCode = 3;
                $this->_response['message'][] = "La cooperative n'exite pas.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
            try {
                $objDetailUser = DB::table('users')
                    ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                    ->select('roles.name as role',
                        'roles.ref as role_ref',
                        'users.id as user_id',
                        'users.name as user_name',
                        'users.surname as user_surname',
                        'users.phone as user_phone',
                        'users.email as user_email',
                        'users.ville as user_ville',
                        'users.quartier as user_quartier',
                        'users.ref as user_ref')
                    ->where("users.ref", "=", $objuser->ref)
                    ->get();
            } catch (Exception $objException) {
                $this->_errorCode = 4;
                if (in_array($this->_env, ['local', 'development'])) {
                    $this->_response['message'] = $objException->getMessage();
                }
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
        $toReturn = [
            'reponse' => $objDetailUser
        ];

        $this->_response['message'] = "Detail de l'utilisateur ";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);


        DB::commit();
        $toReturn = [
            'objet' => $client
        ];

        $this->_response['message'] = "details d'un client.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function listeClient()
    {
        $this->_fnErrorCode = "01";

        //On vérife que l'utilisateur est bien connecté
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if($objRole->alias == "gestionnaire-surface") {

            try {
                $allclients = User::where('role_id','=',2)->paginate(10);
            }catch (Exception $objException) {
                $this->_errorCode = 3;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollBack();
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habileté!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'objet' => $allclients
        ];

        $this->_response['message'] = "Liste des clients.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function getUserByCooperative(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'ref_cooperative' => 'required'
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

        $objCooperative = Cooperative::where("ref", $request->get("ref_cooperative"))->first();
        if(empty($objCooperative)){
            DB::rollback();
            $this->_errorCode = 3;
            $this->_response['message'][] = "La cooperative n'exite pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        try {
            $objAllUsers = DB::table('users')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->leftJoin('cooperatives', 'cooperatives.id', '=', 'users.cooperative_id')
                ->select('roles.name as role',
                    'roles.ref as role_ref',
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.surname as user_surname',
                    'users.phone as user_phone',
                    'users.email as user_email',
                    'users.ref as user_ref',
                    'cooperatives.name as cooperative',
                    'cooperatives.ref as cooperative_ref')
                ->where("users.cooperative_id", "=", $objCooperative->id)
                ->get();
        } catch (Exception $objException) {
            $this->_errorCode = 4;
            if (in_array($this->_env, ['local', 'development'])) {
                $this->_response['message'] = $objException->getMessage();
            }
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $toReturn = [
            'reponse' => $objAllUsers
        ];

        $this->_response['message'] = "Liste de tous les utilisateurs de la surface de partage : ".$objCooperative->name;
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
    public function ListeUser()
    {
        $this->_errorCode  = 1;
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if($objRole->alias == "administrateur") {
            try {
                $allUser = DB::table('users')
                    ->join('roles','roles.id','users.role_id')
                    ->select(
                        'users.id as id',
                        'users.name as name',
                        'users.surname as surname',
                        'users.email as email',
                        'users.phone as phone',
                        'users.ref as ref',
                        'roles.id as role_id',
                        'roles.name as role_name'
                    )
                    ->where('users.published','=',1)
                    ->get();
            }catch (Exception $objException) {
                $this->_errorCode = 3;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollBack();
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habileté!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $toReturn = [
            'users'=>$allUser
        ];
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    // liste des coursiers
    public function listeCoursier(){
        $this->_fnErrorCode = "01";
        //On vérife que l'utilisateur est bien connecté
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        $allCoursier = array();
        if($objRole->alias == "gestionnaire-surface") {
           $objSurface = Surface_partage::where('id','=',$objUser->surface_partage_id)->first();
            try {
                $allCoursier = User::where('role_id','=',6)
                    ->where('surface_partage_id','=',$objSurface->id)
                    ->get();
            }catch (Exception $objException) {
                $this->_errorCode = 3;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollBack();
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habileté!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'objet' => $allCoursier
        ];

        $this->_response['message'] = "Liste des coursiers.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    // liste des livreurs
    public function listeLivreurs(){
        $this->_fnErrorCode = "01";

        //On vérife que l'utilisateur est bien connecté
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien gestionnaire-surface
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if($objRole->alias == "gestionnaire-surface") {

            try {
                $allLivreurs = User::where('role_id','=',5)->get();
            }catch (Exception $objException) {
                $this->_errorCode = 3;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollBack();
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habileté!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'objet' => $allLivreurs
        ];

        $this->_response['message'] = "Liste des coursiers.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    //Liste des producteurs
    public function listeProducteurs(){
        $this->_fnErrorCode = "01";

        //On vérife que l'utilisateur est bien connecté
        $objUser = Auth::user();
        if (empty($objUser)) {
            $this->_errorCode = 2;
            $this->_response['message'][] = "Utilisateur non connecté";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        //On vérifie que l'utilisateur est bien admin
        $objRole = Role::where('id', '=', $objUser->role_id)->first();
        if($objRole->alias == "gestionnaire-surface") {

            try {

                $allProducteurs = DB::table('users')
                ->join('roles','roles.id','=','users.role_id')
                ->join('cooperatives','cooperatives.id','=','users.cooperative_id')
                ->select('users.ref as user_ref',
                    'users.name as user_name',
                    'users.surname as user_surname',
                    'users.phone as user_phone',
                    'users.email as user_email',
                    'roles.name as role',
                    'roles.ref as role_ref',
                    'cooperatives.name as cooperative',
                    'cooperatives.ref as cooperative_ref')
                ->where('users.role_id','=',7)
                ->get();

            }catch (Exception $objException) {
                $this->_errorCode = 3;
                if (in_array($this->_env, ['local', 'development'])) {
                }
                $this->_response['message'] = $objException->getMessage();
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else {
            DB::rollBack();
            $this->_errorCode = 4;
            $this->_response['message'][] = "Vous n'êtes pas habileté!";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        DB::commit();
        $toReturn = [
            'objet' => $allProducteurs
        ];

        $this->_response['message'] = "Liste des producteurs.";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
    // update user
    public function update(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'nom'=>'nullable',
            'prenom'=>'nullable',
            'ref_user'=>'string|required',
            'phone'=>'nullable',
            'quartier'=>'nullable',
            'ville'=>'nullable',
            'email'=>'nullable|email|max:255',
            'cooperative'=>'nullable',
            'surface_partage'=>'nullable',
            'role'=>'nullable',
            'password'=>'nullable|min:'.Config::get('constants.size.min.password').'|max:'.Config::get('constants.size.max.password'),
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

        //dd($id);

        $objUser = Auth::user();
        if(empty($objUser)){
            $this->_errorCode = 3;
            $this->_response['message'][] = "Cette action nécéssite une connexion.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objAuthRole = Role::where("id", $objUser->role_id)->first();
        if(empty($objAuthRole)){
            DB::rollback();
            $this->_errorCode = 4;
            $this->_response['message'][] = "Le user n'a pas de rôle.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        // Start transaction!
        DB::beginTransaction();

        try{
            $objUpdateUser = User::where('ref', '=', $request->get('ref_user'))->first();
            if(empty($objUpdateUser)){
                DB::rollback();
                $this->_errorCode = 5;
                $this->_response['message'][] = "L'utilisateur n'existe pas";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }
            if($request->has('nom') && $request->get('nom')!=""){
                if(!$objUpdateUser->update(["name" => $request->get('nom')])){
                    DB::rollback();
                    $this->_errorCode = 6;
                    $this->_response['message'][] = "La modification n'a pas été éffectué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }
            //dd($objUpdateUser);
            if($request->has('prenom') && $request->get('prenom')!=""){
                if(!$objUpdateUser->update(["surname" => $request->get('prenom')])){
                    DB::rollback();
                    $this->_errorCode = 7;
                    $this->_response['message'][] = "La modification n'a pas été éffectué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }
            if($request->has('phone') && $request->get('phone')!=""){
                if(!$objUpdateUser->update(["phone" => $request->get('phone')])){
                    DB::rollback();
                    $this->_errorCode = 8;
                    $this->_response['message'][] = "La modification n'a pas été éffectué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }
            if($request->has('ville') && $request->get('ville')!=""){
                if(!$objUpdateUser->update(["ville" => $request->get('ville')])){
                    DB::rollback();
                    $this->_errorCode = 9;
                    $this->_response['message'][] = "La modification n'a pas été éffectué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }
            if($request->has('quartier') && $request->get('quartier')!=""){
                if(!$objUpdateUser->update(["quartier" => $request->get('quartier')])){
                    DB::rollback();
                    $this->_errorCode = 10;
                    $this->_response['message'][] = "La modification n'a pas été éffectué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }


            if($request->has('password') && $request->get('password')!=""){
                if(!$objUpdateUser->update(["password" => Hash::make($request->get('password'))])){
                    DB::rollback();
                    $this->_errorCode = 10;
                    $this->_response['message'][] = "La modification n'a pas été éffectué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }

            if($request->has('role') && $request->get('role')!=""){
                $objUserRole = Role::where('ref', '=', $request->get('role'))->first();
                if(empty($objUserRole)){
                    DB::rollback();
                    $this->_errorCode = 10;
                    $this->_response['message'][] = "Le 'Rôle' n'existe pas";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try{
                    $objUpdateUser->update(["role_id" => $objUserRole->id]);
                }catch (Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 11;
                    if(in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }
            }

            if($request->has('surface_partage') && $request->get('surface_partage')!=""){
                $objSurface = Surface_partage::where('ref', '=', $request->get('surface_partage'))->first();
                if(empty($objSurface)){
                    DB::rollback();
                    $this->_errorCode = 12;
                    $this->_response['message'][] = "La 'Surface de partage' n'existe pas";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try{
                    $objUpdateUser->update(["surface_partage_id" => $objSurface->id]);
                }catch (Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 13;
                    if(in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }
            }

            if($request->has('cooperative') && $request->get('cooperative')!=""){
                $objCooperative = Cooperative::where('ref', '=', $request->get('cooperative'))->first();
                if(empty($objCooperative)){
                    DB::rollback();
                    $this->_errorCode = 14;
                    $this->_response['message'][] = "La cooperative n'existe pas";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }

                try{
                    $objUpdateUser->update(["cooperative_id" => $objCooperative->id]);
                }catch (Exception $objException){
                    DB::rollback();
                    $this->_errorCode = 15;
                    if(in_array($this->_env, ['local', 'development'])){
                    }
                    $this->_response['message'] = $objException->getMessage();
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json( $this->_response );
                }
            }

        }catch (Exception $objException){
            DB::rollback();
            $this->_errorCode = 16;
            if(in_array($this->_env, ['local', 'development'])){
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json( $this->_response );
        }

        // Commit the queries!
        DB::commit();

        //Format d'affichage de message
        $toReturn = [
            'objet' => $objUpdateUser
        ];

        $this->_response['message'] = 'Modification réussi!';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function delete(Request $request)
    {
        $this->_fnErrorCode = "01";
        $validator = Validator::make($request->all(), [
            'ref_user'=>'required'
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
            $this->_errorCode = 3;
            $this->_response['message'][] = "Cette action nécéssite une connexion.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $objAuthRole = Role::where('id', '=', $objUser->role_id)->first();
        if(empty($objAuthRole)){
            $this->_errorCode = 4;
            $this->_response['message'][] = "L'utilisateur n'a pas de rôle.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $auth_01 = array("administrateur","gestionnaire-surface","gestionnaire-cooperative");
        if($request->has("ref_user")){
            if(in_array($objAuthRole->alias, $auth_01)){
                $objDelUser = User::where("ref", $request->get("ref_user"));
                if(!$objDelUser->update(["published" => 0])){
                    DB::rollback();
                    $this->_errorCode = 5;
                    $this->_response['message'][] = "La suppression a échoué.";
                    $this->_response['error_code'] = $this->prepareErrorCode();
                    return response()->json($this->_response);
                }
            }else{
                DB::rollback();
                $this->_errorCode = 6;
                $this->_response['message'][] = "Vous n'étes pas habilié.";
                $this->_response['error_code'] = $this->prepareErrorCode();
                return response()->json($this->_response);
            }

        }else{
            $this->_errorCode = 7;
            $this->_response['message'][] = "User n'existe pas.";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }
        $toReturn = [
            'objet' => $objDelUser
        ];

        $this->_response['message'] = "L'utilisateur a été supprimé!";
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }

    public function forgotPassword(Request $request){
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'email'=>'email|required'
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
        $objUser = User::where('email', $request->get('email'))->first();
        if(empty($objUser)) {
            $this->_errorCode = 3;
            $this->_response['message'][] = "utilisateur inconnu !";
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        try {
            $mail_reponse = $objUser->mailChangePassword($objUser);
        } catch (Exception $objException) {
            DB::rollBack();
            $this->_errorCode = 7;
            if(in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }


        $toReturn = [
            //'sms_reponse' => $reponse,
            'mail_reponse' => $mail_reponse,
            'objet' => $objUser
        ];
        $this->_response['message'] = 'verifier votre boite mail';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);

    }

    public function changePassword(Request $request){
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'ref'=>'string|required',
            'password'=>'string|required'
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
        $password = Hash::make($request->get('password'));

        if(!$objUser->update(["password" => $password])) {
            $this->_errorCode = 5;
            $this->_response['message'][] = "Le mot de passe n'a pas pu être activé !";
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
}

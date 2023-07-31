<?php

namespace App\Http\Controllers\Api;

use App\Categorie;
use App\Role;
use App\Ville;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PartenaireApiController extends Controller
{
    public function devenirPartenaire(Request $request)
    {
        $this->_fnErrorCode = 1;
        $validator = Validator::make($request->all(), [
            'nom'=>'String|required',
            'prenom'=>'String|required',
            'phone'=>'String|required',
            'email'=>'email|required',
            'ville'=>'String|nullable',
            'message'=>'String|required',
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

        try {
            $mail_reponse = $this->mailToAdmin($request->get('nom'),$request->get('prenom'), $request->get('phone'), $request->get('ville'), $request->get('email'),$request->get('message'));
        } catch (Exception $objException) {
            DB::rollBack();
            $this->_errorCode = 3;
            if(in_array($this->_env, ['local', 'development'])) {
            }
            $this->_response['message'] = $objException->getMessage();
            $this->_response['error_code'] = $this->prepareErrorCode();
            return response()->json($this->_response);
        }

        $toReturn = [
            'mail_reponse'=> $mail_reponse
        ];
        $this->_response['message'] = 'envoie de mail reussi.';
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
    public function mailToAdmin($nom,$prenom,$phone,$ville,$email,$message) {
        $result = 'email envoyé!';
        if ($ville==" "){
            $ville=" ";
        }
        $data = [
            'nom'=>$nom,
            'prenom'=>$prenom,
            'email'=>$email,
            'phone'=>$phone,
            'ville'=>$ville,
            'message'=>$message
        ];
        $encodeData = json_encode($data,true);
        $user = json_decode($encodeData);
        $datas = array('nom'=>$user->nom, 'prenom'=>$user->prenom, 'email'=>$user->email,'phone'=>$user->phone,'ville'=>$user->ville,'message'=>$user->message);
        //dd($datas);
        Mail::send('mail_to_admin',$datas, function($message) use ($user) {
            $message->to('mail@team-solutions.net','DRAT')->subject
            //$message->to($user->email, $user->name)->subject
            ('demande de partenaria');
            $message->from($user->email, $user->nom);
        });

        return $result;
    }

}

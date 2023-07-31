<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Ville_surface;

class Ville_surfaceApiController extends Controller
{
    public function allVilleSurface()
    {
        $this->_errorCode  = 1;
        $toReturn = [
            'ville_surface'=>Ville_surface::all(),
        ];
        $this->_response['message']    = 'Liste des villes.';
        $this->_response['data']    = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
}

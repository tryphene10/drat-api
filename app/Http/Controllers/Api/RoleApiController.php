<?php

namespace App\Http\Controllers\Api;

use App\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RoleApiController extends Controller
{
    // Afficher la liste des users
    public function viewAll()
    {
        $this->_errorCode = 1;
        $toReturn = [
            'message' => Role::all(),
        ];
        $this->_response['data'] = $toReturn;
        $this->_response['success'] = true;
        return response()->json($this->_response);
    }
}

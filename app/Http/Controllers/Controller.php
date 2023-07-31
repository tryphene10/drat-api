<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    protected $fractal;
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $_response;
    protected $_baseErrorCode;
    protected $_fnErrorCode;
    protected $_errorCode;
    protected $_env;
    protected $_paginateQty = 20;

    public function __construct()
    {
        $this->_env = env("APP_ENV");
        $this->_response = array(
            'success'=>false,
            'error_code'=>0,
            'message'=>array(),
            'data'=>array(),
            'status_code'=>null
        );

    }

    protected function prepareErrorCode(){
        if($this->_errorCode < 10 ){
            $err = "0".$this->_errorCode;
        }
        else
        {
            $err = $this->_errorCode;
        }
        return (int)($this->_baseErrorCode . $this->_fnErrorCode . $err);
    }
}

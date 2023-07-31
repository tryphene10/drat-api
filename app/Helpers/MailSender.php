<?php
/**
 * Created by PhpStorm.
 * User: fulle
 * Date: 2018/09/27
 * Time: 16:49
 */

namespace App\Helpers;


use App\User;
use Exception;
use Illuminate\Support\Facades\Mail;

class MailSender
{
    protected $_response;
    public static function passwordChanged(User $objUser, array $data=[])
    {
        $toReturn = [
            'success'   => false,
            'message'   => "",
            'data'      => null
        ];
        try
        {
            Mail::send('email.password.changed', $data, function($message) use($objUser)
            {
                $message->to($objUser->email, $objUser->name)
                ->subject('mail.subject.password.changed');
            });
            $toReturn['success'] = true;
        }
        catch(Exception $objException)
        {
            $toReturn['message'] = $objException->getMessage();
            $toReturn['success'] = false;
        }
        return $toReturn;
    }

    public static function confirmMail(User $objUser, array $data)
    {
        $toReturn = [
            'success'   => false,
            'message'   => "",
            'data'      => null
        ];
        try
        {
            Mail::send('email.confirm', $data, function($message) use($objUser)
            {
                $message->to($objUser->email, $objUser->name)
                    ->subject('mail.confirm');
            });
            $toReturn['success'] = true;
        }
        catch(Exception $objException)
        {
            $toReturn['message'] = $objException->getMessage();
            $toReturn['success'] = false;
        }
        return $toReturn;
    }

    public static function requestOTP(User $objUser, $otp)
    {
        $toReturn = [
            'success'   => false,
            'message'   => "",
            'data'      => null
        ];
        try
        {
            Mail::send('email.otp.request', ['opt'  => $otp], function($message) use($objUser)
            {
                $message->to($objUser->email, $objUser->name)
                    ->subject('mail.subject.otp.request');
            });
            $toReturn['success'] = true;
        }
        catch(Exception $objException)
        {
            $toReturn['message'] = $objException->getMessage();
            $toReturn['success'] = false;
        }
        return $toReturn;
    }

    public function resetResponse()
    {
        return [
            'success'   => false,
            'data'      => []
        ];
    }
}
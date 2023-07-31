<?php
/**
 * Created by PhpStorm.
 * User: SDKPC2
 * Date: 8/26/2016
 * Time: 7:12 AM
 */

namespace App\Helpers;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;


class CustFunc
{

    public static function decodeBase64Image( $content )
    {

        $img = str_replace( 'data:image/jpeg;base64,', '', $content );
        $img = str_replace(' ', '+', $img );
        return base64_decode( $img );
    }

    public static function getNumKey($length)
    {
        $token = "";
        $codeAlphabet = "0123456789";



        for($i=0;$i<$length;$i++)
        {
            $token .= $codeAlphabet[mt_rand(0,strlen($codeAlphabet)-1)];
        }
        return $token;
    }

    public static function getToken($length)
    {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet.= "0123456789";



        for($i=0;$i<$length;$i++)
        {
            $token .= $codeAlphabet[mt_rand(0,strlen($codeAlphabet)-1)];
        }
        return $token;
    }


    public static function getBrowser()
    {
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
        $bname = 'Unknown';
        $platform = 'Unknown';
        $version= "";

        //First get the platform?
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'linux';
        }
        elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'mac';
        }
        elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'windows';
        }

        // Next get the name of the useragent yes seperately and for good reason
        if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent))
        {
            $bname = 'Internet Explorer';
            $ub = "MSIE";
        }
        elseif(preg_match('/Firefox/i',$u_agent))
        {
            $bname = 'Mozilla Firefox';
            $ub = "Firefox";
        }
        elseif(preg_match('/Chrome/i',$u_agent))
        {
            $bname = 'Google Chrome';
            $ub = "Chrome";
        }
        elseif(preg_match('/Safari/i',$u_agent))
        {
            $bname = 'Apple Safari';
            $ub = "Safari";
        }
        elseif(preg_match('/Opera/i',$u_agent))
        {
            $bname = 'Opera';
            $ub = "Opera";
        }
        elseif(preg_match('/Netscape/i',$u_agent))
        {
            $bname = 'Netscape';
            $ub = "Netscape";
        }

        // finally get the correct version number
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) .
            ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $u_agent, $matches)) {
            // we have no matching number just continue
        }

        // see how many we have
        $i = count($matches['browser']);
        if ($i != 1) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){
                $version= $matches['version'][0];
            }
            else {
                $version= $matches['version'][1];
            }
        }
        else {
            $version= $matches['version'][0];
        }

        // check if we have a number
        if ($version==null || $version=="") {$version="?";}

        return array(
            'userAgent'=>$u_agent,
            'name'=>$bname,
            'version'=>$version,
            'platform'=>$platform,
            'pattern'=>$pattern
        );
    }


    public static function toAscii($str, $replace=array(), $delimiter='-', $locale='en_US.UTF8') {
        setlocale(LC_ALL, $locale);
        if (!empty($replace))
        {
            $str = str_replace((array) $replace, ' ', $str);
        }
        $clean = preg_replace(array('/Ä/', '/Ö/', '/Ü/', '/ä/', '/ö/', '/ü/'), array('Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue'), $str);
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        return $clean;
    }

    public static function timestampToDate($timestamp)
    {
        $date =  new Carbon($timestamp);//->createFromTimestamp($timestamp)->toDateString();
        return $date->toDateString();
    }

    public static function paginate($items, $perPage = 15, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public static function getClientIp() {
        //whether ip is from share internet
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        }
        //whether ip is from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        //whether ip is from remote address
        else
        {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }

        return $ip_address;
    }

    public static function generatePassword($_len) {

        $_alphaSmall = 'abcdefghijklmnopqrstuvwxyz';            // small letters
        $_alphaCaps  = strtoupper($_alphaSmall);                // CAPITAL LETTERS
        $_numerics   = '1234567890';                            // numerics
        $_specialChars = '!@#$%^&*()-_=+]}[{;:,<.>/?\'"\|';   // Special Characters

        $_container = $_alphaSmall.$_alphaCaps.$_numerics.$_specialChars;   // Contains all characters
        $password = '';         // will contain the desired pass

        for($i = 0; $i < $_len; $i++) {                                 // Loop till the length mentioned
            $_rand = rand(0, strlen($_container) - 1);                  // Get Randomized Length
            $password .= substr($_container, $_rand, 1);                // returns part of the string [ high tensile strength ;) ]
        }

        return $password;       // Returns the generated Pass
    }

}

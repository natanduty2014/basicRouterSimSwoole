<?php

namespace Functions\jwt;


use Firebase\JWT\JWT as JWTTOKEN;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use DomainException;
use InvalidArgumentException;
use UnexpectedValueException;
use Functions\token\token;
use Functions\cryptography\encode;



class jwt
{


    private static $token;

    //gerar o token
    private static function generatorprivate($data, $user_permissions = null)
    {
        // 7 dias
        $time = time() + (7 * 24 * 60 * 60);
        date_default_timezone_set('America/Sao_Paulo');
        $HTTP_ORIGIN = $_SERVER['HTTP_ORIGIN'];
        $HTTP_X_FORWARDED_FOR = $_SERVER['HTTP_X_FORWARDED_FOR'];
        if(empty($HTTP_ORIGIN)){
            $HTTP_ORIGIN = 'http://localhost';
        }
        if(empty($HTTP_X_FORWARDED_FOR)){
            $HTTP_X_FORWARDED_FOR = 'http://localhost';
        }
        $ip = array(
            $HTTP_ORIGIN,
            encode::encode($HTTP_X_FORWARDED_FOR)
        );
        $payload = [
            'iss' => "https://localhost",
            'aud' => $ip,
            'iat' => time(),
            'browser' => self::getBrowser($_SERVER['HTTP_USER_AGENT']),
            'platform' => $_SERVER['HTTP_SEC_CH_UA_PLATFORM'],
            "cli_id" => $data['cli_id'],
            "cli_full_name" => $data['cli_full_name'],
            "cli_email" => $data['cli_email'],
            "cli_avatar" => $data['cli_avatar'],
            'exp' => $time
            // 'user_permissions' => $user_permissions ?? null,
        ];

        $jwt = JWTTOKEN::encode($payload, token::privatekey(), 'RS256');
        return ($jwt);
    }
    //retonrar o token geradp
    public static function generator($data, $user_permissions = null)
    {
        return self::generatorprivate($data, $user_permissions);
    }


    //abrir o token
    private static function decodetokenprivate($jwt)
    {
        $jwt = $jwt ? str_replace(array('Bearer '),'', $jwt): '';
        try {
            $decoded = JWTTOKEN::decode($jwt, new Key(token::publickey(), 'RS256'));
            //echo "time: ".$time-time().'<br>';
            return $decoded;
        } catch (InvalidArgumentException $e) {
            // provided key/key-array is empty or malformed.
            return 'invalidargument';
        } catch (DomainException $e) {
            // provided algorithm is unsupported OR
            // provided key is invalid OR
            // unknown error thrown in openSSL or libsodium OR
            // libsodium is required but not available.
            return 'DomainException';
        } catch (SignatureInvalidException $e) {
            // provided JWT signature verification failed.
            return "signature";
        } catch (BeforeValidException $e) {
            // provided JWT is trying to be used before "nbf" claim OR
            // provided JWT is trying to be used before "iat" claim.
            return 'BeforeValidException';
        } catch (ExpiredException $e) {
            // provided JWT is trying to be used after "exp" claim.
            return 'exp';
        } catch (UnexpectedValueException $e) {
            // provided JWT is malformed OR
            // provided JWT is missing an algorithm / using an unsupported algorithm OR
            // provided JWT algorithm does not match provided key OR
            // provided key ID in key/key-array is empty or invalid.
            return 'UnexpectedValueException';
        }
    }
    //retornar o token aberto
    public static function decodetoken($token)
    {
        return self::decodetokenprivate($token);
    }

    static public function verifyToken($token)
    {
        //remove Bearer
        $token = $token ? str_replace(array('Bearer '),'', $token): '';
        if($token == null){
            throw new \Exception ('token null');
        }
        if(empty($token)){
            throw new \Exception ('empty token');
        }
        if (jwt::decodetoken($token) == 'invalidargument') {
            throw new \Exception ('invalid argument');
        }
        if (jwt::decodetoken($token) == 'signature') {
            throw new \Exception ('signature invalid');
        }
        if (jwt::decodetoken($token) == 'BeforeValidException') {
            throw new \Exception ('BeforeValid Exception');
        }
        if (jwt::decodetoken($token) == 'exp') {
            throw new \Exception ('expired token');
        }
        if (jwt::decodetoken($token) == 'DomainException') {
            throw new \Exception ('Domain Exception');
        }
        if (jwt::decodetoken($token) == 'UnexpectedValueException') {
            throw new \Exception ('Unexpected Value Exception');
        }
        return true;
    }

    static public function getBrowser($userAgent) {
        // Padrões comuns de user agents
        $patterns = array(
            'Firefox' => '/Firefox/i',
            'Chrome' => '/Chrome|CriOS/i',
            'Safari' => '/Safari/i',
            'Edge' => '/Edg/i',
            'Opera' => '/Opera|OPR/i',
            'IE' => '/MSIE/i',
            'Brave' => '/Brave/i',
            'Vivaldi' => '/Vivaldi/i',
            'Yandex' => '/YaBrowser/i',
            'UC Browser' => '/UCBrowser/i',
            'Samsung Internet' => '/SamsungBrowser/i',
            'Nokia Browser' => '/NokiaBrowser/i',
            'Maxthon' => '/Maxthon/i',
            'Konqueror' => '/Konqueror/i',
            'Pale Moon' => '/PaleMoon/i',
            'SeaMonkey' => '/SeaMonkey/i',
            'Avant Browser' => '/Avant Browser/i',
            'Epic Privacy Browser' => '/Epic/i',
            'Waterfox' => '/Waterfox/i',
            'DuckDuckGo Browser' => '/DuckDuckGo/i',
            'Midori' => '/Midori/i',
            'qutebrowser' => '/qutebrowser/i',
            'Sleipnir' => '/Sleipnir/i',
            'GNU IceCat' => '/IceCat/i',
            'GNU IceWeasel' => '/Iceweasel/i',
            'QupZilla' => '/QupZilla/i',
            'Falkon' => '/Falkon/i',
            'Min Browser' => '/Min/i',
            'Dooble' => '/Dooble/i',
            'Elinks' => '/ELinks/i',
            'Links' => '/Links/i',
            'Lynx' => '/Lynx/i',
            'w3m' => '/w3m/i',
            'NetSurf' => '/NetSurf/i',
            'Surf' => '/Surf/i',
            'Dillo' => '/Dillo/i',
            'Amaya' => '/Amaya/i',
            'EWW' => '/w3m/i', // Emacs Web Wowser
            'Emacs w3' => '/w3m/i',
            'MicroEmacs' => '/w3m/i',
            'w3' => '/w3m/i',
            'ELinks' => '/ELinks/i'
        );
        // Verifica cada padrão para determinar o navegador
        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $browser;
            }
        }
        // Se nenhum navegador for encontrado, retorna desconhecido
        return 'Desconhecido';
    }
}

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



class refreshtoken
{


    private static $token;

    //gerar o token
    private static function generatorprivate($data)
    {
        // 30 dias
        $time = time() + (30 * 24 * 60 * 60);
        date_default_timezone_set('America/Sao_Paulo');

        $payload = [
            'iss' => 'http://localhost:9502',
            'aud' => 'http://localhost:9502',
            'iat' => time(),
            'sub' => $data['ser_login'],
            'id'  => $data['ser_id'],
            'per' => $data['ser_est_id'],
            /*
            'level' => $data[0]['ser_est_id'],
            'ippublic' => $data[1]['ipPublico'],
            'iplocal' =>  $data[1]['iplocal'],
            'navegador' => $data[1]['navegador'],*/
            'exp' => $time
        ];

        $jwt = JWTTOKEN::encode($payload, token::privatekey(), 'RS256');
        return ($jwt);
    }
    //retonrar o token geradp
    public static function generator($data)
    {
        return self::generatorprivate($data);
    }


    //abrir o token
    private static function decodetokenprivate($jwt)
    {
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
}

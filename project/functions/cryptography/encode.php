<?php

namespace Functions\cryptography;

class encode
{
    static public function encode($data)
    {
        //random_bytes
        // $iv = \random_bytes(16);
        // $key = \random_bytes(16);
        // var_dump(bin2hex($iv));
        // var_dump(bin2hex($key));
        //openssl_encrypt
        $data = $data;
        $method = "AES-256-CBC";
        $key = \hex2bin('1d9ef798ab9040123b2715568a2482b7');
        $iv = \hex2bin('d9bbcc24db14b1890361ac61bae1659a');
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        $encoded = base64_encode($encrypted);
        return $encoded;
    }

    static public function libsodium($data){
        $key = \hex2bin('1d9ef798ab9040123b2715568a2482b7');
        $nonce = \hex2bin('d9bbcc24db14b1890361ac61bae1659a');
        $encrypted = \sodium_crypto_secretbox($data, $nonce, $key);
        $encoded = base64_encode($encrypted);
        return $encoded;
    }
}

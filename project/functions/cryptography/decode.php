<?php

namespace Functions\cryptography;

class decode{
    static public function decode($data)
    {
        //openssl_dncrypt
        $data = $data;
        $method = "AES-256-CBC";
        $key = \hex2bin('1d9ef798ab9040123b2715568a2482b7');
        $iv = \hex2bin('d9bbcc24db14b1890361ac61bae1659a');
        $decoded = base64_decode($data);
        $decrypted = openssl_decrypt($decoded, $method, $key, 0, $iv);
        return $decrypted;
    }

    static public function libsodium($data){
        $key = \hex2bin('1d9ef798ab9040123b2715568a2482b7');
        $nonce = \hex2bin('d9bbcc24db14b1890361ac61bae1659a');
        $decoded = base64_decode($data);
        $decrypted = \sodium_crypto_secretbox_open($decoded, $nonce, $key);
        return $decrypted;
    }
}
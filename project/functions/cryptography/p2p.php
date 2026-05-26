<?php

namespace Functions\cryptography;

class p2p{
    static public function libsodium($data, $type){
        $key =  \hex2bin('7cdd83bbfbe45f058d31736618f12c16c6ab0c1d8089c09be9aa5731b53825b6');
        $privateKey = \hex2bin('4ef9caa9cb23cd4ceb2ac3cdd205e8145f908713782c869af6a6fe6d62e411f213ef3b6ee898a0f706c4d528c42f22c132715847092e7903f0ffa3d759925308');
        $publicKey = \hex2bin('13ef3b6ee898a0f706c4d528c42f22c132715847092e7903f0ffa3d759925308');

        $nonce = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);

        if($type == 'encrypt'){
            $encrypted = \sodium_crypto_box($data, $nonce, $key);
            $encoded = base64_encode($nonce.$encrypted);
            return $encoded;
        }
        if($type == 'decrypt'){
            $nonceLength = SODIUM_CRYPTO_BOX_NONCEBYTES;
            $nonceReceived = substr(base64_decode($data), 0, $nonceLength);
            $encryptedDataReceived = substr(base64_decode($data), $nonceLength);
            $description = sodium_crypto_box_open($encryptedDataReceived, $nonceReceived, $key);
            return $description;
        }
    }

    static public function margeKeypair($screretKey, $publicKey){
       $keypairMarge = sodium_crypto_box_keypair_from_secretkey_and_publickey($screretKey, $publicKey);
       return $keypairMarge;
    }
}